<?php

use App\Models\Did;
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

function makeDidAdmin(): array
{
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole(Role::findOrCreate('admin', 'web'));
    return [$tenant, $user];
}

function mockDidServices(): array
{
    $pbx    = Mockery::mock(FreeSwitchService::class);
    $config = Mockery::mock(FusionPbxConfigService::class);
    app()->instance(FreeSwitchService::class, $pbx);
    app()->instance(FusionPbxConfigService::class, $config);
    return [$pbx, $config];
}

// ── Create with active routing ────────────────────────────────────────────────

it('writes inbound route when DID is created with enabled + destination_type', function () {
    [$tenant, $user] = makeDidAdmin();
    [$pbx, $config]  = mockDidServices();

    $extId = (string) \Illuminate\Support\Str::uuid();

    $config->shouldReceive('writeInboundRoute')
           ->once()
           ->withArgs(fn ($route) =>
               isset($route['id'], $route['did'], $route['destination'])
               && $route['did'] === '12125551234'
               && $route['destination'] === $extId
           );
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)
        ->post('/pbx/dids', [
            'number'           => '+1 (212) 555-1234',
            'destination_type' => 'extension',
            'destination_id'   => $extId,
            'enabled'          => true,
        ])
        ->assertRedirect('/pbx/dids');

    $this->assertDatabaseHas('dids', [
        'number'           => '+1 (212) 555-1234',
        'destination_type' => 'extension',
        'destination_id'   => $extId,
    ]);
});

// ── Create without destination ────────────────────────────────────────────────

it('removes stale route when DID is created without destination_type', function () {
    [$tenant, $user] = makeDidAdmin();
    [$pbx, $config]  = mockDidServices();

    $config->shouldReceive('deleteInboundRoute')->once();
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)
        ->post('/pbx/dids', [
            'number'  => '+19175550000',
            'enabled' => false,
        ])
        ->assertRedirect('/pbx/dids');

    $this->assertDatabaseHas('dids', ['number' => '+19175550000']);
});

// ── Update routing ────────────────────────────────────────────────────────────

it('rewrites inbound route when DID destination is updated', function () {
    [$tenant, $user] = makeDidAdmin();
    [$pbx, $config]  = mockDidServices();

    $did = Did::create([
        'tenant_id' => $tenant->id,
        'number'    => '+18005551111',
        'enabled'   => false,
    ]);

    $newExtId = (string) \Illuminate\Support\Str::uuid();

    $config->shouldReceive('writeInboundRoute')
           ->once()
           ->withArgs(fn ($route) => $route['destination'] === $newExtId);
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)
        ->patch("/pbx/dids/{$did->id}", [
            'number'           => '+18005551111',
            'destination_type' => 'extension',
            'destination_id'   => $newExtId,
            'enabled'          => true,
        ])
        ->assertRedirect('/pbx/dids');
});

// ── Destroy ───────────────────────────────────────────────────────────────────

it('removes the inbound route when a DID is deleted', function () {
    [$tenant, $user] = makeDidAdmin();
    [$pbx, $config]  = mockDidServices();

    $did = Did::create([
        'tenant_id' => $tenant->id,
        'number'    => '+13125559999',
        'enabled'   => true,
    ]);

    $config->shouldReceive('deleteInboundRoute')
           ->once()
           ->withArgs(fn ($route) => $route['id'] === $did->id);
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)
        ->delete("/pbx/dids/{$did->id}")
        ->assertRedirect('/pbx/dids');

    $this->assertDatabaseMissing('dids', ['id' => $did->id]);
});
