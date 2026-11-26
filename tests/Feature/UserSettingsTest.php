<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(fn () => config(['nexvary.installed' => true]));

it('lists accounts and creates a tenant agent through user settings', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::findOrCreate('superadmin', 'web'));
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $this->actingAs($owner)->get('/settings/users')->assertInertia(fn ($page) => $page
        ->component('Settings/Users')->where('canCreate', true)->where('users.data.0.id', $owner->id)
        ->missing('users.data.0.password'));

    $this->post('/settings/users', [
        'name' => 'New Agent',
        'email' => 'Agent@example.test',
        'password' => 'Local-test-password-12',
        'password_confirmation' => 'Local-test-password-12',
        'tenant_id' => $tenant->id,
        'role' => 'superadmin',
    ])->assertRedirect('/settings/users');

    $agent = User::where('email', 'agent@example.test')->sole();
    expect($agent->tenant_id)->toBe($tenant->id)
        ->and($agent->getRoleNames()->all())->toBe(['agent'])
        ->and(Hash::check('Local-test-password-12', $agent->password))->toBeTrue();
});

it('limits tenant administrators to their own accounts and blocks creation', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->assignRole(Role::findOrCreate('admin', 'web'));
    User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->actingAs($admin)->get('/settings/users')->assertInertia(fn ($page) => $page
        ->where('canCreate', false)->has('users.data', 1)->where('users.data.0.id', $admin->id));
    $this->post('/settings/users')->assertForbidden();
});
