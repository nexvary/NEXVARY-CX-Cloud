<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Override default (not installed) so dashboard routes pass EnsureInstalled
    config(['nexvary.installed' => true]);
});

it('redirects unauthenticated users to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('shows dashboard for authenticated admin', function () {
    $user = User::factory()->create();
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    $user->assignRole('superadmin');

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

it('includes call records from the migrated schema', function () {
    // User and CallRecord must share a tenant so TenantScope returns the record.
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);

    // Create the CallRecord with the same tenant so TenantScope sees it.
    $call = \App\Models\CallRecord::withoutGlobalScopes()->create([
        'uuid'               => (string) \Illuminate\Support\Str::uuid(),
        'tenant_id'          => $tenant->id,
        'caller_id_number'   => '1001',
        'destination_number' => '1002',
        'direction'          => 'internal',
        'duration_seconds'   => 25,
        'started_at'         => now(),
    ]);

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->component('Dashboard/Index')
        ->where('stats.calls_today', 1)
        ->where('recent_calls.0.id', $call->id)
        ->where('recent_calls.0.destination_number', '1002')
        ->where('recent_calls.0.duration_seconds', 25));
});

it('enforces administrator permissions on configuration pages and writes', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/admin')->assertForbidden();
    $this->post('/admin/contacts', ['first_name' => 'Restricted'])->assertForbidden();
    $role = \Spatie\Permission\Models\Role::findOrCreate('superadmin', 'web');
    $user->assignRole($role);
    $this->get('/admin')->assertOk();

    // Superadmin must select a tenant before saving tenant-scoped records.
    $this->post('/admin/contacts', ['first_name' => 'Customer', 'email' => 'ok@example.test'])
        ->assertSessionHasErrors('tenant');
    $this->assertDatabaseMissing('contacts', ['email' => 'ok@example.test']);

    // After selecting a tenant the save succeeds and uses the correct tenant_id.
    $tenant = \App\Models\Tenant::factory()->create();
    $this->post('/admin/select-tenant', ['tenant_id' => $tenant->id]);
    $this->post('/admin/contacts', ['first_name' => 'Customer', 'email' => 'bad'])->assertSessionHasErrors('email');
    $this->post('/admin/contacts', ['first_name' => 'Customer', 'email' => 'ok@example.test'])
        ->assertRedirect('/admin/contacts');
    $this->assertDatabaseHas('contacts', ['email' => 'ok@example.test', 'tenant_id' => $tenant->id]);
});

it('does not allow a pending second factor on the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['mfa_pending' => true])->get('/dashboard')->assertRedirect('/mfa');
});

it('creates and invalidates login sessions and records audit events', function () {
    $user = User::factory()->create();
    $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
    $this->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
    $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'login']);
    $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'logout']);
});

it('saves trunk and extension drafts without enabling SIP', function () {
    // Tenant admin users need a valid tenant_id; without one the controller aborts 403.
    $tenant = \App\Models\Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));
    $this->actingAs($user)->post('/admin/trunks/draft', [
        'name' => 'Local trunk', 'sip_host' => '127.0.0.1', 'sip_port' => 5060,
    ])->assertRedirect('/admin/trunks');
    $this->post('/admin/extensions/draft', ['number' => '1001', 'description' => 'Reception'])
        ->assertRedirect('/admin/extensions');
    $this->assertDatabaseHas('trunks', ['name' => 'Local trunk', 'register' => false, 'enabled' => false, 'tenant_id' => $tenant->id]);
    $this->assertDatabaseHas('extensions', ['number' => '1001', 'enabled' => false, 'tenant_id' => $tenant->id]);
    $this->post('/admin/extensions/draft', ['number' => '1001'])->assertSessionHasErrors('number');
});
