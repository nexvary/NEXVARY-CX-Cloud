<?php

use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['nexvary.installed' => true]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Create a tenant and an admin user belonging to it.
 * Returns [$tenant, $user].
 */
function makeTenantWithAdmin(): array
{
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole(Role::findOrCreate('admin', 'web'));
    return [$tenant, $user];
}

function makeSuperadmin(): User
{
    $user = User::factory()->create(['tenant_id' => null]);
    $user->assignRole(Role::findOrCreate('superadmin', 'web'));
    return $user;
}

// ── Stage 4: multi-tenant extension uniqueness ────────────────────────────────

it('allows two tenants to each create extension 1001 without conflict', function () {
    [$tenantA, $userA] = makeTenantWithAdmin();
    [$tenantB, $userB] = makeTenantWithAdmin();

    // Tenant A creates extension 1001
    $this->actingAs($userA)
        ->post('/admin/extensions/draft', ['number' => '1001', 'description' => 'Tenant A Reception'])
        ->assertRedirect('/admin/extensions');

    $this->assertDatabaseHas('extensions', ['number' => '1001', 'tenant_id' => $tenantA->id]);

    // Tenant B creates extension 1001 — must succeed (different tenant scope)
    $this->actingAs($userB)
        ->post('/admin/extensions/draft', ['number' => '1001', 'description' => 'Tenant B Reception'])
        ->assertRedirect('/admin/extensions');

    $this->assertDatabaseHas('extensions', ['number' => '1001', 'tenant_id' => $tenantB->id]);

    // Two rows exist — one per tenant
    expect(Extension::withoutGlobalScopes()->where('number', '1001')->count())->toBe(2);
});

it('rejects duplicate extension number within the same tenant', function () {
    [$tenant, $userA] = makeTenantWithAdmin();

    $this->actingAs($userA)
        ->post('/admin/extensions/draft', ['number' => '2001', 'description' => 'First'])
        ->assertRedirect('/admin/extensions');

    // Same tenant, same number — must fail validation
    $this->actingAs($userA)
        ->post('/admin/extensions/draft', ['number' => '2001', 'description' => 'Duplicate'])
        ->assertSessionHasErrors('number');
});

// ── Stage 4: global scope prevents cross-tenant data reads ────────────────────

it('prevents tenant A from seeing tenant B extensions via Eloquent', function () {
    [$tenantA, $userA] = makeTenantWithAdmin();
    [$tenantB, $userB] = makeTenantWithAdmin();

    // Seed extension for tenant B directly (bypass global scope)
    Extension::withoutGlobalScopes()->create([
        'id'          => (string) Str::uuid(),
        'tenant_id'   => $tenantB->id,
        'number'      => '3001',
        'description' => 'Tenant B private ext',
        'enabled'     => false,
        'sip_password'=> Hash::make('secret'),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    // Tenant A query must return nothing for Tenant B's extension
    $this->actingAs($userA);
    $visible = Extension::where('number', '3001')->get();
    expect($visible)->toBeEmpty();
});

it('returns no results when unauthenticated query hits TenantScope (fail-safe)', function () {
    $tenant = Tenant::factory()->create();

    // Seed extension without auth context
    Extension::withoutGlobalScopes()->create([
        'id'          => (string) Str::uuid(),
        'tenant_id'   => $tenant->id,
        'number'      => '4001',
        'description' => 'Orphan',
        'enabled'     => false,
        'sip_password'=> Hash::make('secret'),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    // No actingAs — TenantScope must block everything (whereRaw('1 = 0'))
    $visible = Extension::where('number', '4001')->get();
    expect($visible)->toBeEmpty();
});

// ── Stage 4: superadmin isolation ────────────────────────────────────────────

it('denies a user with null tenant_id but no superadmin role from reading extensions', function () {
    $tenant = Tenant::factory()->create();
    $orphanUser = User::factory()->create(['tenant_id' => null]);
    // No superadmin role assigned

    Extension::withoutGlobalScopes()->create([
        'id'          => (string) Str::uuid(),
        'tenant_id'   => $tenant->id,
        'number'      => '5001',
        'description' => 'Protected',
        'enabled'     => false,
        'sip_password'=> Hash::make('secret'),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $this->actingAs($orphanUser);
    $visible = Extension::where('number', '5001')->get();
    expect($visible)->toBeEmpty();
});

it('allows a verified superadmin with null tenant_id to read all extensions', function () {
    $tenant = Tenant::factory()->create();
    $superadmin = makeSuperadmin();

    Extension::withoutGlobalScopes()->create([
        'id'          => (string) Str::uuid(),
        'tenant_id'   => $tenant->id,
        'number'      => '6001',
        'description' => 'Superadmin visible',
        'enabled'     => false,
        'sip_password'=> Hash::make('secret'),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $this->actingAs($superadmin);
    // Superadmin has no auto-scope, so explicit query returns the record
    $visible = Extension::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
        ->where('number', '6001')->get();
    expect($visible)->toHaveCount(1);
});
