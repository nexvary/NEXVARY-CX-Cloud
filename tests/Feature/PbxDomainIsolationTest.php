<?php

/**
 * Stage 1 PBX Foundation — Domain-Based Multi-Tenancy Tests
 *
 * Verifies the four Stage 1 capabilities:
 *   1A. Domain-based multi-tenancy (isolated SIP contexts per tenant)
 *   1B. Working SIP extensions (create, update, disable, delete)
 *   1C. SIP trunks and DID routing
 *   1D. Real inbound and outbound routing (RouteController persistence)
 *
 * Tests use mocked FreeSWITCH services to avoid requiring a live PBX.
 * The domain isolation assertions verify that FreeSWITCH config paths
 * use the per-tenant SIP domain, not the shared "default" context.
 */

use App\Models\Did;
use App\Models\Domain;
use App\Models\Extension;
use App\Models\InboundRoute;
use App\Models\OutboundRoute;
use App\Models\Tenant;
use App\Models\Trunk;
use App\Models\User;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\FusionPbxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['nexvary.installed' => true]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Create a tenant with a named SIP domain and an admin user.
 * Returns [$tenant, $user, $domain].
 */
function makeIsolatedTenant(string $sipDomain): array
{
    $tenant = Tenant::factory()->create();
    $domain = Domain::create([
        'tenant_id' => $tenant->id,
        'name'      => $sipDomain,
        'type'      => 'sip',
        'enabled'   => true,
        'verified'  => true,
    ]);
    $tenant->update(['primary_domain_id' => $domain->id]);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    return [$tenant, $user, $domain];
}

function mockExtServices(): array
{
    $pbx    = Mockery::mock(FreeSwitchService::class);
    $config = Mockery::mock(FusionPbxConfigService::class);
    app()->instance(FreeSwitchService::class, $pbx);
    app()->instance(FusionPbxConfigService::class, $config);
    return [$pbx, $config];
}

// ═══════════════════════════════════════════════════════════════════════════════
// 1A — Domain-based multi-tenancy
// ═══════════════════════════════════════════════════════════════════════════════

it('1A: Tenant::sipDomain() returns the primary SIP domain name', function () {
    [$tenant,, $domain] = makeIsolatedTenant('company-a.nexvary.cloud');
    expect($tenant->sipDomain())->toBe('company-a.nexvary.cloud');
});

it('1A: Tenant::sipDomain() falls back to slug.pbx when no domain is configured', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme-test']);
    expect($tenant->sipDomain())->toBe('acme-test.pbx');
});

it('1A: two tenants can each create extension 1001 without DB conflict', function () {
    [$tenantA, $userA] = makeIsolatedTenant('company-a.pbx.test');
    [$tenantB, $userB] = makeIsolatedTenant('company-b.pbx.test');

    [$pbx, $config] = mockExtServices();
    $pbx->shouldReceive('reloadDialplan')->twice()->andReturn(true);
    $config->shouldReceive('ensureDomainDialplanDir')->twice()->andReturn(true);

    $config->shouldReceive('writeExtensionConfig')
        ->once()
        ->withArgs(fn ($ext, $pass, $dom) => $dom === 'company-a.pbx.test');

    $config->shouldReceive('writeExtensionConfig')
        ->once()
        ->withArgs(fn ($ext, $pass, $dom) => $dom === 'company-b.pbx.test');

    $this->actingAs($userA)->post('/pbx/extensions', [
        'number'      => '1001',
        'description' => 'Company A Reception',
    ])->assertRedirect('/pbx/extensions');

    $this->actingAs($userB)->post('/pbx/extensions', [
        'number'      => '1001',
        'description' => 'Company B Reception',
    ])->assertRedirect('/pbx/extensions');

    expect(Extension::withoutGlobalScopes()->where('number', '1001')->count())->toBe(2);
});

it('1A: extension config is written to the per-domain directory path', function () {
    [$tenant, $user] = makeIsolatedTenant('acme.sip.local');
    [$pbx, $config]  = mockExtServices();

    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);
    $config->shouldReceive('ensureDomainDialplanDir')->once()->with('acme.sip.local');
    $config->shouldReceive('writeExtensionConfig')
        ->once()
        ->withArgs(fn ($ext, $pass, $dom) => $dom === 'acme.sip.local');

    $this->actingAs($user)->post('/pbx/extensions', [
        'number'      => '2001',
        'description' => 'Acme Sales',
    ])->assertRedirect('/pbx/extensions');
});

it('1A: tenant A cannot see tenant B extensions via Eloquent (TenantScope)', function () {
    [$tenantA, $userA] = makeIsolatedTenant('a.sip.test');
    [$tenantB,]        = makeIsolatedTenant('b.sip.test');

    Extension::withoutGlobalScopes()->create([
        'id'          => \Illuminate\Support\Str::uuid(),
        'tenant_id'   => $tenantB->id,
        'number'      => '5555',
        'description' => 'Tenant B private',
        'enabled'     => true,
        'sip_password'=> hash('sha256', 'secret'),
    ]);

    $this->actingAs($userA);
    expect(Extension::where('number', '5555')->get())->toBeEmpty();
});

it('1A: cross-tenant route model binding returns 404', function () {
    [$tenantA, $userA] = makeIsolatedTenant('a.pbx.test');
    [$tenantB, $userB] = makeIsolatedTenant('b.pbx.test');

    // Create extension in tenant B's scope
    $extB = Extension::withoutGlobalScopes()->create([
        'id'          => \Illuminate\Support\Str::uuid(),
        'tenant_id'   => $tenantB->id,
        'number'      => '9999',
        'sip_password'=> hash('sha256', 'pass'),
        'enabled'     => true,
    ]);

    // Tenant A trying to edit tenant B's extension → 404
    $this->actingAs($userA)
        ->get("/pbx/extensions/{$extB->id}/edit")
        ->assertNotFound();
});

// ═══════════════════════════════════════════════════════════════════════════════
// 1B — Working SIP extensions
// ═══════════════════════════════════════════════════════════════════════════════

it('1B: creates extension with tenant-scoped unique validation', function () {
    [$tenant, $user] = makeIsolatedTenant('ext.test.local');
    [$pbx, $config]  = mockExtServices();
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);
    $config->shouldReceive('ensureDomainDialplanDir')->once();
    $config->shouldReceive('writeExtensionConfig')->once();

    $this->actingAs($user)->post('/pbx/extensions', [
        'number' => '1001',
    ])->assertRedirect('/pbx/extensions');

    $this->assertDatabaseHas('extensions', [
        'number'    => '1001',
        'tenant_id' => $tenant->id,
    ]);
});

it('1B: rejects duplicate extension number within same tenant', function () {
    [$tenant, $user] = makeIsolatedTenant('dup.test.local');
    [$pbx, $config]  = mockExtServices();
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);
    $config->shouldReceive('ensureDomainDialplanDir')->once();
    $config->shouldReceive('writeExtensionConfig')->once();

    $this->actingAs($user)->post('/pbx/extensions', ['number' => '2002'])
        ->assertRedirect('/pbx/extensions');

    $this->actingAs($user)->post('/pbx/extensions', ['number' => '2002'])
        ->assertSessionHasErrors('number');
});

it('1B: deletes extension config from per-domain path', function () {
    [$tenant, $user] = makeIsolatedTenant('del.test.local');
    [$pbx, $config]  = mockExtServices();

    $ext = Extension::withoutGlobalScopes()->create([
        'id'          => \Illuminate\Support\Str::uuid(),
        'tenant_id'   => $tenant->id,
        'number'      => '3003',
        'sip_password'=> hash('sha256', 'pw'),
        'enabled'     => true,
    ]);

    $config->shouldReceive('deleteExtensionConfig')
        ->once()
        ->withArgs(fn ($e, $dom) => $e->id === $ext->id && $dom === 'del.test.local');
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)->delete("/pbx/extensions/{$ext->id}")
        ->assertRedirect('/pbx/extensions');

    $this->assertSoftDeleted('extensions', ['id' => $ext->id]);
});

it('1B: disabling an extension removes its FreeSWITCH directory entry', function () {
    [$tenant, $user] = makeIsolatedTenant('disable.test.local');
    [$pbx, $config]  = mockExtServices();

    $ext = Extension::withoutGlobalScopes()->create([
        'id'          => \Illuminate\Support\Str::uuid(),
        'tenant_id'   => $tenant->id,
        'number'      => '4004',
        'sip_password'=> hash('sha256', 'pw'),
        'enabled'     => true,
    ]);

    $config->shouldReceive('deleteExtensionConfig')
        ->once()
        ->withArgs(fn ($e, $dom) => $dom === 'disable.test.local');
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)
        ->post("/pbx/extensions/{$ext->id}/enabled", ['enabled' => false])
        ->assertOk()
        ->assertJson(['ok' => true, 'enabled' => false]);
});

// ═══════════════════════════════════════════════════════════════════════════════
// 1C — SIP trunks and DIDs
// ═══════════════════════════════════════════════════════════════════════════════

it('1C: trunk name must be unique within tenant', function () {
    [$tenant, $user] = makeIsolatedTenant('trunk.test.local');
    [$pbx, $config]  = mockExtServices();

    $config->shouldReceive('writeTrunkConfig')->once();
    $pbx->shouldReceive('reloadSipProfiles')->once()->andReturn(true);

    $this->actingAs($user)->post('/pbx/trunks', [
        'name'      => 'Primary SIP',
        'auth_mode' => 'credential',
        'sip_host'  => 'sip.provider.com',
        'username'  => 'user1',
    ])->assertRedirect('/pbx/trunks');

    $this->actingAs($user)->post('/pbx/trunks', [
        'name'      => 'Primary SIP',
        'auth_mode' => 'credential',
        'sip_host'  => 'sip.provider.com',
        'username'  => 'user2',
    ])->assertSessionHasErrors('name');
});

it('1C: DID inbound route is written with tenant domain context', function () {
    [$tenant, $user] = makeIsolatedTenant('did.test.local');
    [$pbx, $config]  = mockExtServices();

    $extId = (string) \Illuminate\Support\Str::uuid();

    $config->shouldReceive('writeInboundRoute')
        ->once()
        ->withArgs(fn ($route) =>
            $route['domain'] === 'did.test.local'
            && $route['did'] === '12125551234'
            && $route['destination'] === $extId
        );
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)->post('/pbx/dids', [
        'number'           => '+1 (212) 555-1234',
        'destination_type' => 'extension',
        'destination_id'   => $extId,
        'enabled'          => true,
    ])->assertRedirect('/pbx/dids');
});

it('1C: tenant B DID route is not visible to tenant A', function () {
    [$tenantA, $userA] = makeIsolatedTenant('a.did.test');
    [$tenantB,]        = makeIsolatedTenant('b.did.test');

    Did::withoutGlobalScopes()->create([
        'id'        => \Illuminate\Support\Str::uuid(),
        'tenant_id' => $tenantB->id,
        'number'    => '+19995550000',
        'enabled'   => true,
    ]);

    $this->actingAs($userA);
    expect(Did::where('number', '+19995550000')->get())->toBeEmpty();
});

// ═══════════════════════════════════════════════════════════════════════════════
// 1D — Real inbound and outbound routing
// ═══════════════════════════════════════════════════════════════════════════════

it('1D: creates inbound route and writes FS config', function () {
    [$tenant, $user] = makeIsolatedTenant('routes.test.local');
    [$pbx, $config]  = mockExtServices();

    $trunk = Trunk::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Provider',
        'auth_mode' => 'credential',
        'sip_host'  => 'sip.prov.com',
        'enabled'   => true,
    ]);

    $config->shouldReceive('writeInboundRoute')
        ->once()
        ->withArgs(fn ($r) =>
            $r['domain'] === 'routes.test.local'
            && $r['destination'] === '1001'
        );
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)->post('/pbx/inbound-routes', [
        'name'             => 'Main Line',
        'did'              => '+15551234567',
        'destination_type' => 'extension',
        'destination'      => '1001',
        'trunk_id'         => $trunk->id,
        'priority'         => 10,
        'enabled'          => true,
    ])->assertRedirect('/pbx/inbound-routes');

    $this->assertDatabaseHas('inbound_routes', [
        'name'        => 'Main Line',
        'destination' => '1001',
        'tenant_id'   => $tenant->id,
    ]);
});

it('1D: creates outbound route scoped to tenant domain', function () {
    [$tenant, $user] = makeIsolatedTenant('outbound.test.local');
    [$pbx, $config]  = mockExtServices();

    $trunk = Trunk::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Outbound Provider',
        'auth_mode' => 'credential',
        'sip_host'  => 'out.prov.com',
        'enabled'   => true,
    ]);

    $config->shouldReceive('writeOutboundRoute')
        ->once()
        ->withArgs(fn ($r) =>
            $r['domain'] === 'outbound.test.local'
            && $r['pattern'] === '^1[2-9]\d{9}$'
            && str_contains($r['gateway'], $trunk->id)
        );
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)->post('/pbx/outbound-routes', [
        'name'         => 'US Local',
        'dial_pattern' => '^1[2-9]\d{9}$',
        'trunk_id'     => $trunk->id,
        'priority'     => 10,
        'enabled'      => true,
    ])->assertRedirect('/pbx/outbound-routes');

    $this->assertDatabaseHas('outbound_routes', [
        'name'       => 'US Local',
        'tenant_id'  => $tenant->id,
    ]);
});

it('1D: inbound route index returns only own tenant routes', function () {
    [$tenantA, $userA] = makeIsolatedTenant('a.routes.test');
    [$tenantB,]        = makeIsolatedTenant('b.routes.test');

    InboundRoute::withoutGlobalScopes()->create([
        'id'               => \Illuminate\Support\Str::uuid(),
        'tenant_id'        => $tenantA->id,
        'name'             => 'Route A',
        'destination_type' => 'extension',
        'destination'      => '1001',
        'enabled'          => true,
    ]);
    InboundRoute::withoutGlobalScopes()->create([
        'id'               => \Illuminate\Support\Str::uuid(),
        'tenant_id'        => $tenantB->id,
        'name'             => 'Route B',
        'destination_type' => 'extension',
        'destination'      => '2001',
        'enabled'          => true,
    ]);

    $this->actingAs($userA)
        ->get('/pbx/inbound-routes')
        ->assertOk()
        ->assertInertia(fn ($page) =>
            $page->has('routes', 1)
                 ->where('routes.0.name', 'Route A')
        );
});

it('1D: deletes inbound route and removes FS config', function () {
    [$tenant, $user] = makeIsolatedTenant('del-route.test.local');
    [$pbx, $config]  = mockExtServices();

    $route = InboundRoute::withoutGlobalScopes()->create([
        'id'               => \Illuminate\Support\Str::uuid(),
        'tenant_id'        => $tenant->id,
        'name'             => 'To Delete',
        'destination_type' => 'extension',
        'destination'      => '1001',
        'enabled'          => true,
    ]);

    $config->shouldReceive('deleteInboundRoute')
        ->once()
        ->withArgs(fn ($r) => $r['id'] === $route->id);
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)
        ->delete("/pbx/inbound-routes/{$route->id}")
        ->assertRedirect('/pbx/inbound-routes');

    $this->assertDatabaseMissing('inbound_routes', ['id' => $route->id]);
});

it('1D: outbound route name must be unique within tenant', function () {
    [$tenant, $user] = makeIsolatedTenant('unique-route.test.local');
    [$pbx, $config]  = mockExtServices();

    $config->shouldReceive('writeOutboundRoute')->once()->andReturn(true);
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)->post('/pbx/outbound-routes', [
        'name'         => 'Primary',
        'dial_pattern' => '.*',
        'enabled'      => true,
    ])->assertRedirect('/pbx/outbound-routes');

    $this->actingAs($user)->post('/pbx/outbound-routes', [
        'name'         => 'Primary',
        'dial_pattern' => '[0-9]+',
        'enabled'      => true,
    ])->assertSessionHasErrors('name');
});

it('1D: cross-tenant route access returns 404', function () {
    [$tenantA, $userA] = makeIsolatedTenant('a.rt.test');
    [$tenantB,]        = makeIsolatedTenant('b.rt.test');

    $routeB = OutboundRoute::withoutGlobalScopes()->create([
        'id'           => \Illuminate\Support\Str::uuid(),
        'tenant_id'    => $tenantB->id,
        'name'         => 'B Route',
        'dial_pattern' => '.*',
        'enabled'      => true,
    ]);

    $this->actingAs($userA)
        ->get("/pbx/outbound-routes/{$routeB->id}/edit")
        ->assertNotFound();
});
