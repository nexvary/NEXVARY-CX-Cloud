<?php

use App\Models\DeviceProvision;
use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PBX\DeviceProvisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(fn() => config(['nexvary.installed' => true]));

function makeProvAdmin(): array
{
    $t = Tenant::factory()->create();
    $u = User::factory()->create(['tenant_id' => $t->id]);
    $u->assignRole(Role::findOrCreate('admin', 'web'));
    return [$t, $u];
}

it('admin can create a device provision and receives a token', function () {
    [, $user] = makeProvAdmin();
    $ext = Extension::factory()->create(['tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user)->post('/pbx/device-provisions', [
        'extension_id' => $ext->id,
        'device_type'  => 'yealink',
        'enabled'      => true,
    ]);

    $response->assertRedirect('/pbx/device-provisions');
    $this->assertDatabaseHas('device_provisions', [
        'extension_id' => $ext->id,
        'device_type'  => 'yealink',
    ]);
});

it('provision endpoint returns 404 for unknown token', function () {
    $this->get('/provision/' . str_repeat('a', 64))->assertNotFound();
});

it('provision endpoint returns device XML for valid token', function () {
    $tenant = Tenant::factory()->create();
    $ext    = Extension::factory()->create(['tenant_id' => $tenant->id, 'number' => '1001']);
    ['raw' => $raw, 'hash' => $hash] = DeviceProvision::generateToken();

    $prov = DeviceProvision::create([
        'tenant_id'            => $tenant->id,
        'extension_id'         => $ext->id,
        'device_type'          => 'yealink',
        'provision_token_hash' => $hash,
        'enabled'              => true,
    ]);

    $service = Mockery::mock(DeviceProvisionService::class);
    $service->shouldReceive('render')->once()->andReturn('<xml/>');
    $service->shouldReceive('contentType')->once()->andReturn('text/xml');
    $service->shouldReceive('filename')->once()->andReturn('test.cfg');
    app()->instance(DeviceProvisionService::class, $service);

    $this->get('/provision/' . $raw)->assertOk()->assertHeader('Content-Type', 'text/xml; charset=utf-8');
});

it('provision endpoint is disabled for a disabled record', function () {
    ['raw' => $raw, 'hash' => $hash] = DeviceProvision::generateToken();
    DeviceProvision::create([
        'device_type'          => 'generic',
        'provision_token_hash' => $hash,
        'enabled'              => false,
    ]);
    $this->get('/provision/' . $raw)->assertNotFound();
});

it('cross-tenant admin cannot access another tenant provision', function () {
    [, $userA] = makeProvAdmin();
    [$tenantB,] = makeProvAdmin();
    ['hash' => $hash] = DeviceProvision::generateToken();
    $prov = DeviceProvision::create([
        'tenant_id'            => $tenantB->id,
        'device_type'          => 'generic',
        'provision_token_hash' => $hash,
        'enabled'              => true,
    ]);
    $this->actingAs($userA)->delete('/pbx/device-provisions/' . $prov->id)->assertNotFound();
});
