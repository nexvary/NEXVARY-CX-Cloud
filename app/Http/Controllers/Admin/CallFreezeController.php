<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallFreeze;
use App\Models\Tenant;
use App\Models\Trunk;
use App\Services\PBX\CallFreezeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CallFreezeController extends Controller
{
    public function __construct(private readonly CallFreezeService $freezes) {}

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        return Inertia::render('Admin/CallFreeze', [
            'active_freezes' => $this->freezes->activeFreezes($tenantId),
            'history'        => CallFreeze::with(['tenant', 'trunk', 'activatedByUser', 'deactivatedByUser'])
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->latest('activated_at')
                ->paginate(20),
            'tenants'        => Tenant::select('id', 'name')->orderBy('name')->get(),
            'trunks'         => Trunk::enabled()->select('id', 'name', 'sip_host', 'tenant_id')->get(),
        ]);
    }

    public function activateTenant(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'reason'    => ['required', 'string', 'max:500'],
        ]);

        $this->freezes->activateTenantFreeze(
            tenantId:    $request->input('tenant_id'),
            activatedBy: $request->user()->id,
            reason:      $request->input('reason'),
        );

        $this->auditFreeze($request, 'freeze.tenant.activated', $request->input('tenant_id'), null);

        return back()->with('success', __('messages.freeze_activated'));
    }

    public function activateGateway(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'trunk_id' => ['required', 'uuid', 'exists:trunks,id'],
            'reason'   => ['required', 'string', 'max:500'],
        ]);

        $this->freezes->activateGatewayFreeze(
            trunkId:     $request->input('trunk_id'),
            activatedBy: $request->user()->id,
            reason:      $request->input('reason'),
        );

        $this->auditFreeze($request, 'freeze.gateway.activated', null, $request->input('trunk_id'));

        return back()->with('success', __('messages.freeze_activated'));
    }

    public function deactivate(Request $request, CallFreeze $freeze): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        // MFA users must supply a fresh TOTP code to lift a freeze.
        // Users without MFA enrolled proceed without a code.
        if ($user->mfa_enabled) {
            $request->validate([
                'code' => ['required', 'digits:6'],
            ]);

            $secret = \Illuminate\Support\Facades\Crypt::decryptString($user->mfa_secret);
            $valid  = (new \PragmaRX\Google2FA\Google2FA)->verifyKey($secret, $request->input('code'));

            if (! $valid) {
                return back()->withErrors(['code' => __('auth.mfa_invalid')]);
            }
        }

        $this->freezes->deactivate($freeze->id, $request->user()->id);
        $this->auditFreeze($request, 'freeze.deactivated', $freeze->tenant_id, $freeze->trunk_id);

        return back()->with('success', __('messages.freeze_deactivated'));
    }

    private function auditFreeze(Request $request, string $action, ?string $tenantId, ?string $trunkId): void
    {
        DB::table('audit_logs')->insert([
            'id'         => \Illuminate\Support\Str::uuid(),
            'user_id'    => $request->user()->id,
            'action'     => $action,
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
