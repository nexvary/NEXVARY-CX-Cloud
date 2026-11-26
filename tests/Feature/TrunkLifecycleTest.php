<?php

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

function makeTrunkAdmin(): array
{
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole(Role::findOrCreate('admin', 'web'));
    return [$tenant, $user];
}

function mockPbxServices(): array
{
    $pbx    = Mockery::mock(FreeSwitchService::class);
    $config = Mockery::mock(FusionPbxConfigService::class);
    app()->instance(FreeSwitchService::class, $pbx);
    app()->instance(FusionPbxConfigService::class, $config);
    return [$pbx, $config];
}

// ── Create ────────────────────────────────────────────────────────────────────

it('creates a trunk and writes the FreeSWITCH gateway config', function () {
    [$tenant, $user] = makeTrunkAdmin();
    [$pbx, $config]  = mockPbxServices();

    $config->shouldReceive('writeTrunkConfig')
           ->once()
           ->withArgs(fn ($trunk) => $trunk instanceof Trunk && $trunk->name === 'Test Trunk');
    $pbx->shouldReceive('reloadSipProfiles')->once()->andReturn(true);

    $response = $this->actingAs($user)
        ->post('/pbx/trunks', [
            'name'      => 'Test Trunk',
            'auth_mode' => 'credential',
            'sip_host'  => 'sip.example.com',
            'username'  => 'testuser',
        ]);

    $response->assertRedirect('/pbx/trunks');
    $this->assertDatabaseHas('trunks', [
        'name'     => 'Test Trunk',
        'sip_host' => 'sip.example.com',
        'enabled'  => false, // new gateways default to disabled
    ]);
});

it('derives SignalWire sip_host from space_name', function () {
    [$tenant, $user] = makeTrunkAdmin();
    [$pbx, $config]  = mockPbxServices();

    $config->shouldReceive('writeTrunkConfig')
           ->once()
           ->withArgs(fn ($trunk) => $trunk->sip_host === 'my-space.sip.signalwire.com');
    $pbx->shouldReceive('reloadSipProfiles')->once()->andReturn(true);

    $this->actingAs($user)
        ->post('/pbx/trunks', [
            'name'       => 'SW Trunk',
            'auth_mode'  => 'credential',
            'space_name' => 'my-space',
            'username'   => 'swuser',
            'preset_key' => 'signalwire',
        ]);

    $this->assertDatabaseHas('trunks', ['sip_host' => 'my-space.sip.signalwire.com']);
});

// ── Update ────────────────────────────────────────────────────────────────────

it('updates a trunk and rewrites the config when a new password is supplied', function () {
    [$tenant, $user] = makeTrunkAdmin();
    [$pbx, $config]  = mockPbxServices();

    $trunk = Trunk::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Old Trunk',
        'auth_mode' => 'credential',
        'sip_host'  => 'old.example.com',
        'enabled'   => false,
    ]);

    $config->shouldReceive('writeTrunkConfig')
           ->once()
           ->withArgs(fn ($t, $pass) => $t->id === $trunk->id && $pass === 'new-secret');
    $pbx->shouldReceive('reloadSipProfiles')->once()->andReturn(true);

    $this->actingAs($user)
        ->patch("/pbx/trunks/{$trunk->id}", [
            'name'         => 'Updated Trunk',
            'auth_mode'    => 'credential',
            'sip_host'     => 'new.example.com',
            'username'     => 'updateduser',
            'sip_password' => 'new-secret',
        ]);

    $this->assertDatabaseHas('trunks', ['id' => $trunk->id, 'sip_host' => 'new.example.com']);
});

it('does not rewrite config on update when no password is provided', function () {
    [$tenant, $user] = makeTrunkAdmin();
    [$pbx, $config]  = mockPbxServices();

    $trunk = Trunk::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Stable Trunk',
        'auth_mode' => 'ip_acl',
        'sip_host'  => 'stable.example.com',
        'enabled'   => true,
    ]);

    $config->shouldNotReceive('writeTrunkConfig');
    $pbx->shouldReceive('reloadSipProfiles')->once()->andReturn(true);

    $this->actingAs($user)
        ->patch("/pbx/trunks/{$trunk->id}", [
            'name'      => 'Stable Trunk Renamed',
            'auth_mode' => 'ip_acl',
            'sip_host'  => 'stable.example.com',
        ]);

    $this->assertDatabaseHas('trunks', ['id' => $trunk->id, 'name' => 'Stable Trunk Renamed']);
});

// ── Destroy ───────────────────────────────────────────────────────────────────

it('deletes a trunk and removes its FreeSWITCH gateway config', function () {
    [$tenant, $user] = makeTrunkAdmin();
    [$pbx, $config]  = mockPbxServices();

    $trunk = Trunk::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Doomed Trunk',
        'auth_mode' => 'credential',
        'sip_host'  => 'gone.example.com',
        'enabled'   => false,
    ]);

    $config->shouldReceive('deleteTrunk')->once()->with(Mockery::on(fn ($t) => $t->id === $trunk->id));
    $pbx->shouldReceive('reloadSipProfiles')->once()->andReturn(true);

    $this->actingAs($user)
        ->delete("/pbx/trunks/{$trunk->id}")
        ->assertRedirect('/pbx/trunks');

    $this->assertDatabaseMissing('trunks', ['id' => $trunk->id]);
});

// ── Reload ────────────────────────────────────────────────────────────────────

it('reload uses the correct FreeSWITCH gateway name nexvary-{uuid}', function () {
    [$tenant, $user] = makeTrunkAdmin();
    [$pbx, $config]  = mockPbxServices();

    $trunk = Trunk::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Reload Test',
        'auth_mode' => 'credential',
        'sip_host'  => 'reload.example.com',
        'enabled'   => true,
    ]);

    $pbx->shouldReceive('reloadGateway')
        ->once()
        ->with("nexvary-{$trunk->id}");

    $this->actingAs($user)
        ->post("/pbx/trunks/{$trunk->id}/reload")
        ->assertOk()
        ->assertJson(['ok' => true]);
});

// ── Activate / Deactivate (CX02) ─────────────────────────────────────────────

it('activate writes config, reloads gateway, marks trunk enabled, returns status', function () {
    [$tenant, $user] = makeTrunkAdmin();
    [$pbx, $config]  = mockPbxServices();

    $trunk = Trunk::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Activate Me',
        'auth_mode' => 'credential',
        'sip_host'  => 'activate.example.com',
        'enabled'   => false,
    ]);

    $config->shouldReceive('writeTrunkConfig')
           ->once()
           ->withArgs(fn ($t) => $t->id === $trunk->id);
    $pbx->shouldReceive('reloadGateway')
        ->once()
        ->with("nexvary-{$trunk->id}");
    $pbx->shouldReceive('api')
        ->once()
        ->with("sofia status gateway nexvary-{$trunk->id}")
        ->andReturn("nexvary-{$trunk->id}\tREGED\t");

    $this->actingAs($user)
        ->post("/pbx/trunks/{$trunk->id}/activate")
        ->assertOk()
        ->assertJsonFragment(['ok' => true, 'status' => 'REGED']);

    $this->assertDatabaseHas('trunks', ['id' => $trunk->id, 'enabled' => true]);
});

it('activate returns UNKNOWN when FreeSWITCH gives no recognizable status token', function () {
    [$tenant, $user] = makeTrunkAdmin();
    [$pbx, $config]  = mockPbxServices();

    $trunk = Trunk::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Unknown Status',
        'auth_mode' => 'credential',
        'sip_host'  => 'unknown.example.com',
        'enabled'   => false,
    ]);

    $config->shouldReceive('writeTrunkConfig')->once();
    $pbx->shouldReceive('reloadGateway')->once();
    $pbx->shouldReceive('api')->once()->andReturn(false);

    $this->actingAs($user)
        ->post("/pbx/trunks/{$trunk->id}/activate")
        ->assertOk()
        ->assertJsonFragment(['status' => 'UNKNOWN']);
});

it('deactivate kills gateway, marks trunk disabled, returns DISABLED', function () {
    [$tenant, $user] = makeTrunkAdmin();
    [$pbx, $config]  = mockPbxServices();

    $trunk = Trunk::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Kill Me',
        'auth_mode' => 'credential',
        'sip_host'  => 'kill.example.com',
        'enabled'   => true,
    ]);

    $pbx->shouldReceive('api')
        ->once()
        ->with("sofia profile external killgw nexvary-{$trunk->id}")
        ->andReturn('+OK');

    $this->actingAs($user)
        ->post("/pbx/trunks/{$trunk->id}/deactivate")
        ->assertOk()
        ->assertJson(['ok' => true, 'status' => 'DISABLED']);

    $this->assertDatabaseHas('trunks', ['id' => $trunk->id, 'enabled' => false]);
});

// ── Health ────────────────────────────────────────────────────────────────────

it('health uses the correct FreeSWITCH gateway name nexvary-{uuid}', function () {
    [$tenant, $user] = makeTrunkAdmin();
    [$pbx, $config]  = mockPbxServices();

    $trunk = Trunk::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Health Test',
        'auth_mode' => 'credential',
        'sip_host'  => 'health.example.com',
        'enabled'   => true,
    ]);

    $pbx->shouldReceive('api')
        ->once()
        ->with("sofia status gateway nexvary-{$trunk->id}")
        ->andReturn('REGED');

    $this->actingAs($user)
        ->get("/pbx/trunks/{$trunk->id}/health")
        ->assertOk()
        ->assertJsonFragment(['raw' => 'REGED']);
});
