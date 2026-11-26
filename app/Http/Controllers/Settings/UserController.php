<?php

namespace App\Http\Controllers\Settings;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController
{
    public function index(Request $request): Response
    {
        $owner = $request->user()->hasRole('superadmin');
        $tenantId = $request->user()->tenant_id;
        abort_unless($owner || $tenantId !== null, 403);

        $users = User::query()
            ->when(!$owner, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('name')->orderBy('id')->paginate(25, ['id', 'name', 'email', 'active']);

        return Inertia::render('Settings/Users', [
            'users' => $users,
            'canCreate' => $owner,
            'tenants' => $owner ? Tenant::where('status', 'active')->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('superadmin'), 403);

        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower($request->input('email'))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed', function ($attribute, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('The password must be at most 72 bytes.');
                }
            }],
            'tenant_id' => ['required', 'uuid', Rule::exists('tenants', 'id')->where('status', 'active')->whereNull('deleted_at')],
        ]);

        DB::transaction(function () use ($data) {
            $user = User::create($data);
            $user->assignRole(Role::findOrCreate('agent', 'web'));
        });

        return to_route('settings.users')->with('success', __('User created.'));
    }
}
