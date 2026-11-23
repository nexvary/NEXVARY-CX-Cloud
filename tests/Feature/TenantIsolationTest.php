<?php

use App\Models\Extension;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['nexvary.installed' => true]);
});

// ── Helper ────────────────────────────────────────────────────────────────────

function makeAdminUser(string $tenantId): User
{
    $user = User::factory()->create(['tenant_id' => $tenantId]);
    $user->assignRole(Role::findOrCreate('admin', 'web'));
    return $user;
}

// ── Stage 4: multi-tenant extension uniqueness ────────────────────────────────

it('allows two tenants to each create extension 1001 without conflict', function () {
    $tenantA = (string) \Illuminate\Support\Str::uuid();
    $tenantB = (string) \Illuminate\Support\Str::uuid();

    $userA = makeAdminUser($tenantA);
    $userB = makeAdminUser($tenantB);

    // Tenant A creates extension 1001
    $this->actingAs($userA)
        ->post('/admin/extensions/draft', ['number' => '1001', 'description' => 'Tenant A Reception'])
        ->assertRedirect('/admin/extensions');

    $this->assertDatabaseHas('extensions', ['number' => '1001', 'tenant_id' => $tenantA]);

    // Tenant B creates extension 1001 — must succeed (different tenant scope)
    $this->actingAs($userB)
        ->post('/admin/extensions/draft', ['number' => '1001', 'description' => 'Tenant B Reception'])
        ->assertRedirect('/admin/extensions');

    $this->assertDatabaseHas('extensions', ['number' => '1001', 'tenant_id' => $tenantB]);

    // Two rows exist — one per tenant
    expect(Extension::withoutGlobalScopes()->where('number', '1001')->count())->toBe(2);
});

it('rejects duplicate extension number within the same tenant', function () {
    $tenantA = (string) \Illuminate\Support\Str::uuid();
    $userA = makeAdminUser($tenantA);

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
    $tenantA = (string) \Illuminate\Support\Str::uuid();
    $tenantB = (string) \Illuminate\Support\Str::uuid();

    $userA = makeAdminUser($tenantA);
    $userB = makeAdminUser($tenantB);

    // Seed extension for tenant B directly (bypass global scope)
    Extension::withoutGlobalScopes()->create([
        'id'          => (string) \Illuminate\Support\Str::uuid(),
        'tenant_id'   => $tenantB,
        'number'      => '3001',
        'description' => 'Tenant B private ext',
        'enabled'     => false,
        'sip_password'=> \Illuminate\Support\Facades\Hash::make('secret'),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    // Tenant A query must return nothing for Tenant B's extension
    $this->actingAs($userA);
    $visible = Extension::where('number', '3001')->get();
    expect($visible)->toBeEmpty();
});

it('returns no results when unauthenticated query hits TenantScope (fail-safe)', function () {
    // Seed extension without auth context
    Extension::withoutGlobalScopes()->create([
        'id'          => (string) \Illuminate\Support\Str::uuid(),
        'tenant_id'   => (string) \Illuminate\Support\Str::uuid(),
        'number'      => '4001',
        'description' => 'Orphan',
        'enabled'     => false,
        'sip_password'=> \Illuminate\Support\Facades\Hash::make('secret'),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    // No actingAs — TenantScope should block everything (whereRaw('1 = 0'))
    $visible = Extension::where('number', '4001')->get();
    expect($visible)->toBeEmpty();
});
