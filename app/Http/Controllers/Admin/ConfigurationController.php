<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ConfigurationController extends Controller
{
    public const FIELDS = [
        'trunks' => ['name', 'sip_host', 'sip_port'],
        'extensions' => ['number', 'description'],
        'inbound' => ['name', 'did', 'destination'],
        'outbound' => ['name', 'dial_pattern'],
        'ivr' => ['name', 'description'],
        'queues' => ['name', 'extension'],
        'billing' => ['name', 'currency'],
    ];

    public function store(Request $request, string $module): RedirectResponse
    {
        abort_unless(isset(self::FIELDS[$module]), 404);
        $name = ['required', 'string', 'max:255'];
        $description = ['nullable', 'string', 'max:255'];
        $digits = ['required', 'regex:/^[0-9]{2,20}$/D'];

        $tenantId = auth()->user()->tenant_id ?? null;

        [$table, $rules, $defaults] = match ($module) {
            'trunks' => ['trunks', [
                'name' => $name,
                'sip_host' => ['required', 'string', 'max:253', 'regex:/^[a-z0-9][a-z0-9.-]*$/iD'],
                'sip_port' => ['required', 'integer', 'between:1,65535'],
            ], ['register' => false]],
            'extensions' => ['extensions', [
                'number' => [...$digits, Rule::unique('extensions', 'number')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))],
                'description' => $description,
            ], ['sip_password' => Hash::make(Str::random(32))]],
            'inbound' => ['inbound_routes', [
                'name' => $name,
                'did' => ['required', 'regex:/^\+?[0-9]{3,15}$/D'],
                'destination' => [...$digits, Rule::exists('extensions', 'number')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            ], ['destination_type' => 'extension']],
            'outbound' => ['outbound_routes', [
                'name' => $name,
                'dial_pattern' => ['required', 'regex:/^\+?[0-9]{1,15}$/D'],
            ], []],
            'ivr' => ['ivr_menus', ['name' => $name, 'description' => $description], []],
            'queues' => ['call_queues', ['name' => $name, 'extension' => [...$digits,
                Rule::unique('call_queues', 'extension')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))]], []],
            'billing' => ['rate_tables', ['name' => $name, 'currency' => ['required', 'regex:/^[A-Z]{3}$/D']], []],
        };

        DB::table($table)->insert([
            'id' => Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
            'tenant_id' => $tenantId, 'enabled' => false,
            ...$defaults, ...$request->validate($rules),
        ]);

        return to_route('admin.show', ['module' => $module])->with('success', 'draft_saved');
    }
}
