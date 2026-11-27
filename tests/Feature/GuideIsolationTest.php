<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['nexvary.installed' => true]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeGuideAdmin(): array
{
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole(Role::findOrCreate('admin', 'web'));
    return [$tenant, $user];
}

function makeGuideSuperadmin(): User
{
    $user = User::factory()->create(['tenant_id' => null]);
    $user->assignRole(Role::findOrCreate('superadmin', 'web'));
    return $user;
}

// ── Tenant-scoped guide reads ─────────────────────────────────────────────────

it('shows only own tenant extension records in guide', function () {
    [$tenantA, $userA] = makeGuideAdmin();
    [$tenantB,]        = makeGuideAdmin();

    // Seed extension for tenant B directly (bypass scope)
    DB::table('extensions')->insert([
        'id' => Str::uuid(), 'tenant_id' => $tenantB->id, 'number' => '9001',
        'description' => 'Tenant B private', 'sip_password' => bcrypt('x'),
        'enabled' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('extensions')->insert([
        'id' => Str::uuid(), 'tenant_id' => $tenantA->id, 'number' => '9001',
        'description' => 'Tenant A ext', 'sip_password' => bcrypt('x'),
        'enabled' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->actingAs($userA)->get('/admin/extensions');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Guide')
        ->where('records.total', 1)
        ->where('records.data.0.number', '9001')
    );

    // The tenant_id visible in records must belong to tenantA only (server side)
    $ids = DB::table('extensions')
        ->where('number', '9001')
        ->where('tenant_id', $tenantA->id)
        ->pluck('id');
    expect($ids)->toHaveCount(1);
});

it('prevents tenant A admin from seeing tenant B contacts via guide', function () {
    [$tenantA, $userA] = makeGuideAdmin();
    [$tenantB,]        = makeGuideAdmin();

    DB::table('contacts')->insert([
        'id' => Str::uuid(), 'tenant_id' => $tenantB->id,
        'first_name' => 'Secret', 'email' => 'secret@b.test',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->actingAs($userA)->get('/admin/contacts');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('records.total', 0)
    );
});

// ── Superadmin tenant selection gate ─────────────────────────────────────────

it('blocks superadmin from saving contact without prior tenant selection', function () {
    $sa = makeGuideSuperadmin();

    $this->actingAs($sa)
        ->post('/admin/contacts', ['first_name' => 'Ghost', 'email' => 'ghost@test.test'])
        ->assertSessionHasErrors('tenant');

    $this->assertDatabaseMissing('contacts', ['email' => 'ghost@test.test']);
});

it('blocks superadmin from saving extension draft without prior tenant selection', function () {
    $sa = makeGuideSuperadmin();

    $this->actingAs($sa)
        ->post('/admin/extensions/draft', ['number' => '8001', 'description' => 'Ghost'])
        ->assertSessionHasErrors('tenant');

    $this->assertDatabaseMissing('extensions', ['number' => '8001']);
});

it('saves contact with the selected tenant_id after superadmin picks a tenant', function () {
    $sa     = makeGuideSuperadmin();
    $tenant = Tenant::factory()->create();

    // Select tenant via the new route
    $this->actingAs($sa)
        ->post('/admin/select-tenant', ['tenant_id' => $tenant->id])
        ->assertRedirect();

    // Now saving succeeds and uses the correct tenant_id
    $this->post('/admin/contacts', ['first_name' => 'Alice', 'email' => 'alice@cx.test'])
        ->assertRedirect('/admin/contacts');

    $this->assertDatabaseHas('contacts', [
        'email'     => 'alice@cx.test',
        'tenant_id' => $tenant->id,
    ]);
});

it('saves extension draft with the selected tenant_id after superadmin picks a tenant', function () {
    $sa     = makeGuideSuperadmin();
    $tenant = Tenant::factory()->create();

    $this->actingAs($sa)->post('/admin/select-tenant', ['tenant_id' => $tenant->id]);
    $this->post('/admin/extensions/draft', ['number' => '7001', 'description' => 'SA draft'])
        ->assertRedirect('/admin/extensions');

    $this->assertDatabaseHas('extensions', ['number' => '7001', 'tenant_id' => $tenant->id, 'enabled' => false]);
});

it('rejects an invalid tenant_id in select-tenant', function () {
    $sa = makeGuideSuperadmin();

    $this->actingAs($sa)
        ->post('/admin/select-tenant', ['tenant_id' => Str::uuid()])
        ->assertSessionHasErrors('tenant_id');
});

it('returns noTenantSelected=true for superadmin without selection on tenant-scoped module', function () {
    $sa = makeGuideSuperadmin();

    $this->actingAs($sa)
        ->get('/admin/extensions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('noTenantSelected', true));
});

// ── Superadmin-only module access ─────────────────────────────────────────────

it('denies tenant admin access to voice (superadmin-only) module', function () {
    [, $userA] = makeGuideAdmin();

    $this->actingAs($userA)->get('/admin/voice')->assertForbidden();
});

it('allows superadmin to access voice module', function () {
    $sa = makeGuideSuperadmin();

    $this->actingAs($sa)->get('/admin/voice')->assertOk();
});

// ── Cross-tenant write rejection ──────────────────────────────────────────────

it('rejects tenant A admin creating extension with another tenant B id via request forgery', function () {
    [$tenantA, $userA] = makeGuideAdmin();
    [$tenantB,]        = makeGuideAdmin();

    // Even if the HTTP request tries to include another tenant_id, the controller
    // always uses the authenticated user's own tenant_id.
    $this->actingAs($userA)->post('/admin/extensions/draft', [
        'number'      => '6001',
        'description' => 'Forge attempt',
        'tenant_id'   => $tenantB->id,  // ignored by controller
    ])->assertRedirect('/admin/extensions');

    // The record must carry tenantA's id, not tenantB's
    $this->assertDatabaseHas('extensions', ['number' => '6001', 'tenant_id' => $tenantA->id]);
    $this->assertDatabaseMissing('extensions', ['number' => '6001', 'tenant_id' => $tenantB->id]);
});

// ── Null-tenant non-superadmin user is blocked ────────────────────────────────

it('blocks a null-tenant non-superadmin user from accessing guide routes', function () {
    $orphan = User::factory()->create(['tenant_id' => null]);
    $orphan->assignRole(Role::findOrCreate('admin', 'web'));

    $this->actingAs($orphan)->get('/admin/extensions')->assertForbidden();
    $this->post('/admin/contacts', ['first_name' => 'Ghost'])->assertForbidden();
});

// ── Shared/system tables are accessible without tenant selection ───────────────

it('shows rate_tables (billing) to tenant admin without tenant selection issues', function () {
    [, $userA] = makeGuideAdmin();

    // Rate tables have no tenant_id — accessible to all admins
    $this->actingAs($userA)->get('/admin/billing')->assertOk();
});

it('filters audit_logs (security) to only the same-tenant users for tenant admin', function () {
    [$tenantA, $userA] = makeGuideAdmin();
    [$tenantB, $userB] = makeGuideAdmin();

    // Create an audit log entry for each tenant's user
    DB::table('audit_logs')->insert([
        'id' => Str::uuid(), 'user_id' => $userA->id, 'action' => 'login',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('audit_logs')->insert([
        'id' => Str::uuid(), 'user_id' => $userB->id, 'action' => 'login',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($userA)->get('/admin/security')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('records.total', 1));
});
