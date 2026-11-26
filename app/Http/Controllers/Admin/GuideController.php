<?php

namespace App\Http\Controllers\Admin;

use App\Services\PBX\PbxMode;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PBX\FreeSwitchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GuideController extends Controller
{
    private const MODULES = [
        'trunks'      => ['trunks',            ['name', 'sip_host', 'sip_port', 'enabled'],          true],
        'extensions'  => ['extensions',         ['number', 'description', 'enabled'],                 true],
        'inbound'     => ['inbound_routes',      ['name', 'did', 'destination_type', 'enabled'],       false],
        'outbound'    => ['outbound_routes',     ['name', 'dial_pattern', 'enabled'],                  false],
        'ivr'         => ['ivr_menus',           ['name', 'description', 'enabled'],                   false],
        'queues'      => ['call_queues',         ['name', 'extension', 'strategy', 'enabled'],         false],
        'sms'         => ['sms_conversations',   ['did_number', 'contact_number', 'status'],           false],
        'contacts'    => ['contacts',            ['first_name', 'last_name', 'email', 'phone'],        true],
        'billing'     => ['rate_tables',         ['name', 'currency', 'enabled'],                      false],
        'voice'       => ['ai_sessions',         ['status', 'created_at'],                             false],
        'security'    => ['audit_logs',          ['action', 'created_at'],                             false],
        'maintenance' => null,
    ];

    // Modules whose underlying table has a tenant_id column
    private const TENANT_SCOPED = [
        'trunks', 'extensions', 'inbound', 'outbound', 'ivr', 'queues', 'sms', 'contacts',
    ];

    // Modules whose underlying table has no tenant_id; accessible to superadmin only
    private const SUPERADMIN_ONLY = ['voice'];

    public function index(): Response
    {
        $user = auth()->user();
        $availableTenants = $user->hasRole('superadmin')
            ? Tenant::get(['id', 'name'])->toArray()
            : [];

        return Inertia::render('Admin/Guide', [
            'modules'          => array_keys(self::MODULES),
            'availableTenants' => $availableTenants,
            'selectedTenant'   => session('admin_selected_tenant_id'),
        ]);
    }

    public function show(string $module, FreeSwitchService $pbx): Response
    {
        abort_unless(array_key_exists($module, self::MODULES), 404);

        $user       = auth()->user();
        $definition = self::MODULES[$module];
        $checks     = [];
        $records    = null;
        $columns    = [];
        $noTenantSelected = false;

        if (in_array($module, self::SUPERADMIN_ONLY, true)) {
            abort_unless($user->hasRole('superadmin'), 403);
        }

        $availableTenants = $user->hasRole('superadmin')
            ? Tenant::get(['id', 'name'])->toArray()
            : [];

        if ($definition !== null) {
            [$table, $columns, $hasDeletedAt] = $definition;

            if (in_array($module, self::TENANT_SCOPED, true)) {
                $effectiveTenantId = $this->resolveEffectiveTenantId();

                if ($effectiveTenantId === null) {
                    // Superadmin has not yet chosen a working tenant
                    $noTenantSelected = true;
                } else {
                    $query = DB::table($table)->where('tenant_id', $effectiveTenantId);
                    if ($hasDeletedAt) {
                        $query->whereNull('deleted_at');
                    }
                    $records = $query->orderByDesc('created_at')
                        ->paginate(20, $columns)
                        ->withQueryString();
                }
            } elseif ($module === 'security') {
                // audit_logs has no tenant_id; filter by users belonging to this tenant
                $query = DB::table('audit_logs');
                if ($user->tenant_id !== null) {
                    $tenantUserIds = DB::table('users')
                        ->where('tenant_id', $user->tenant_id)
                        ->pluck('id');
                    $query->whereIn('user_id', $tenantUserIds);
                }
                $records = $query->orderByDesc('created_at')
                    ->paginate(20, $columns)
                    ->withQueryString();
            } else {
                // billing (rate_tables) and voice (ai_sessions): shared/system tables
                $query = DB::table($table);
                if ($hasDeletedAt) {
                    $query->whereNull('deleted_at');
                }
                $records = $query->orderByDesc('created_at')
                    ->paginate(20, $columns)
                    ->withQueryString();
            }
        } else {
            $checks = [
                'database'    => $this->healthy(fn () => (int) DB::scalar('select 1') === 1),
                'redis'       => $this->healthy(fn () => (string) Redis::connection()->ping() === 'PONG'),
                'freeswitch'  => $this->healthy(fn () => $pbx->probe()),
            ];
            $pbx->disconnect();
        }

        // Read selectedTenantId after resolveEffectiveTenantId() may have auto-selected one tenant.
        $selectedTenantId = session('admin_selected_tenant_id');

        return Inertia::render('Admin/Guide', [
            'modules'          => array_keys(self::MODULES),
            'module'           => $module,
            'columns'          => $columns,
            'draftFields'      => PbxMode::fusionPbx() && $module !== 'billing'
                ? [] : (ConfigurationController::FIELDS[$module] ?? []),
            'records'          => $records,
            'checks'           => $checks,
            'noTenantSelected' => $noTenantSelected,
            'availableTenants' => $availableTenants,
            'selectedTenant'   => $selectedTenantId,
        ]);
    }

    public function selectTenant(Request $request): \Illuminate\Http\RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('superadmin'), 403);

        $request->validate([
            'tenant_id' => ['required', 'uuid', Rule::exists('tenants', 'id')],
        ]);

        session(['admin_selected_tenant_id' => $request->input('tenant_id')]);

        return redirect()->back()->with('success', 'tenant_selected');
    }

    public function storeContact(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'email'      => ['nullable', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'regex:/^\\+?[0-9][0-9 ()-]{3,29}$/D'],
        ]);

        $tenantId = $this->resolveEffectiveTenantId();

        if ($tenantId === null) {
            return redirect()->back()->withErrors(['tenant' => 'Select a tenant before saving records.']);
        }

        DB::table('contacts')->insert([
            'id'         => Str::uuid(),
            'tenant_id'  => $tenantId,
            'created_at' => now(),
            'updated_at' => now(),
            ...$data,
        ]);

        return to_route('admin.show', ['module' => 'contacts'])->with('success', 'contact_saved');
    }

    /**
     * Returns the tenant_id the current request should operate on.
     *
     * - Tenant user: their own tenant_id (always set, never null)
     * - Superadmin:  the session-selected tenant, or the only tenant when
     *   exactly one exists (fresh-install convenience), or null when unresolvable
     * - Null-tenant, non-superadmin: abort 403 (misconfigured account)
     */
    private function resolveEffectiveTenantId(): ?string
    {
        $user = auth()->user();

        if ($user->tenant_id !== null) {
            return $user->tenant_id;
        }

        if ($user->hasRole('superadmin')) {
            $selected = session('admin_selected_tenant_id');
            if ($selected !== null) {
                return $selected;
            }

            // Fresh-install convenience: if exactly one tenant exists in the system,
            // auto-select it so the Guide is immediately usable after first setup.
            // With 0 or 2+ tenants, explicit selection is required.
            $tenants = Tenant::pluck('id');
            if ($tenants->count() === 1) {
                $only = $tenants->first();
                session(['admin_selected_tenant_id' => $only]);
                return $only;
            }

            return null;
        }

        abort(403);
    }

    private function healthy(callable $probe): bool
    {
        try {
            return $probe();
        } catch (\Throwable) {
            return false;
        }
    }
}
