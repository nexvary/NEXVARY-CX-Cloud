<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PBX\FreeSwitchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GuideController extends Controller
{
    private const MODULES = [
        'trunks' => ['trunks', ['name', 'sip_host', 'sip_port', 'enabled'], true],
        'extensions' => ['extensions', ['number', 'description', 'enabled'], true],
        'inbound' => ['inbound_routes', ['name', 'did', 'destination_type', 'enabled'], false],
        'outbound' => ['outbound_routes', ['name', 'dial_pattern', 'enabled'], false],
        'ivr' => ['ivr_menus', ['name', 'description', 'enabled'], false],
        'queues' => ['call_queues', ['name', 'extension', 'strategy', 'enabled'], false],
        'sms' => ['sms_conversations', ['did_number', 'contact_number', 'status'], false],
        'contacts' => ['contacts', ['first_name', 'last_name', 'email', 'phone'], true],
        'billing' => ['rate_tables', ['name', 'currency', 'enabled'], false],
        'voice' => ['ai_sessions', ['status', 'created_at'], false],
        'security' => ['audit_logs', ['action', 'created_at'], false],
        'maintenance' => null,
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Guide', ['modules' => array_keys(self::MODULES)]);
    }

    public function show(string $module, FreeSwitchService $pbx): Response
    {
        abort_unless(array_key_exists($module, self::MODULES), 404);
        $definition = self::MODULES[$module];
        $checks = [];
        $records = null;
        $columns = [];

        if ($definition) {
            [$table, $columns, $hasDeletedAt] = $definition;
            $query = DB::table($table);
            if ($hasDeletedAt) {
                $query->whereNull('deleted_at');
            }
            $records = $query->orderByDesc('created_at')->paginate(20, $columns)->withQueryString();
        } else {
            $checks = [
                'database' => $this->healthy(fn () => (int) DB::scalar('select 1') === 1),
                'redis' => $this->healthy(fn () => (string) Redis::connection()->ping() === 'PONG'),
                'freeswitch' => $this->healthy(fn () => $pbx->probe()),
            ];
            $pbx->disconnect();
        }

        return Inertia::render('Admin/Guide', [
            'modules' => array_keys(self::MODULES),
            'module' => $module,
            'columns' => $columns,
            'draftFields' => ConfigurationController::FIELDS[$module] ?? [],
            'records' => $records,
            'checks' => $checks,
        ]);
    }

    public function storeContact(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\\+?[0-9][0-9 ()-]{3,29}$/D'],
        ]);
        DB::table('contacts')->insert(['id' => Str::uuid(), 'created_at' => now(), 'updated_at' => now(), ...$data]);

        return to_route('admin.show', ['module' => 'contacts'])->with('success', 'contact_saved');
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
