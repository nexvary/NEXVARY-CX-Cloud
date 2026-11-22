<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PBX\FreeSwitchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class WizardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Install/Wizard', [
            'step'  => session('install_step', 1),
            'data'  => session('install_data', []),
        ]);
    }

    public function step(Request $request): \Illuminate\Http\RedirectResponse
    {
        $step = (int) $request->input('step', 1);
        $data = session('install_data', []);

        match ($step) {
            1 => $this->validateAdminStep($request),
            2 => $this->validateOrganizationStep($request),
            3 => $this->validateServerStep($request),
            4 => $this->validateProviderStep($request),
            default => null,
        };

        $data = array_merge($data, $request->except(['_token', 'step', 'password', 'password_confirmation', 'sip_password']));
        session(['install_data' => $data, 'install_step' => $step + 1]);

        return redirect()->route('install.index');
    }

    public function testSip(Request $request): \Illuminate\Http\JsonResponse
    {
        $pbx = app(FreeSwitchService::class);
        $healthy = $pbx->healthCheck();

        return response()->json(['ok' => $healthy['connected'] ?? false, 'detail' => $healthy]);
    }

    public function complete(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = session('install_data', []);

        DB::transaction(function () use ($request, $data) {
            $user = User::create([
                'name'     => $data['admin_name'] ?? 'Administrator',
                'email'    => $data['admin_email'],
                'password' => Hash::make($request->input('password')),
                'locale'   => $data['locale'] ?? 'en',
            ]);
            $user->assignRole('superadmin');

            // Write installed marker
            file_put_contents(storage_path('installed'), now()->toIso8601String());
        });

        session()->forget(['install_data', 'install_step']);

        return redirect()->route('login')->with('success', __('messages.install_complete'));
    }

    public function verify(): Response
    {
        $pbx = app(FreeSwitchService::class);

        return Inertia::render('Install/Wizard', [
            'step'       => 8,
            'data'       => session('install_data', []),
            'pbx_status' => $pbx->healthCheck(),
        ]);
    }

    private function validateAdminStep(Request $request): void
    {
        $request->validate([
            'admin_name'  => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email'],
        ]);
    }

    private function validateOrganizationStep(Request $request): void
    {
        $request->validate([
            'org_name'    => ['required', 'string', 'max:255'],
            'org_country' => ['required', 'string', 'size:2'],
        ]);
    }

    private function validateServerStep(Request $request): void
    {
        $request->validate([
            'server_domain' => ['required', 'string'],
            'server_ip'     => ['required', 'ip'],
        ]);
    }

    private function validateProviderStep(Request $request): void
    {
        $request->validate([
            'provider' => ['required', 'string', 'in:telnyx,twilio,vonage,custom'],
        ]);
    }
}
