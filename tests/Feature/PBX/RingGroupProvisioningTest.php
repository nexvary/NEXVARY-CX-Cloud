<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

use App\Models\RingGroup;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\FusionPbxConfigService;
use App\Services\PBX\RingGroupProvisioningService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['sip_domain' => 'rg-test.example.com']);
    $this->user   = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->config = Mockery::mock(FusionPbxConfigService::class)->makePartial();
    $this->config->shouldReceive('writeDialplanFile')->andReturn(null)->byDefault();
    $this->config->shouldReceive('removeDialplanFile')->andReturn(null)->byDefault();
    $this->fs     = Mockery::mock(FreeSwitchService::class);
    $this->fs->shouldReceive('connect')->andReturn(false)->byDefault();
    $this->service = new RingGroupProvisioningService($this->config, $this->fs);
});

it('writes ring group dialplan on provision', function () {
    $this->config->shouldReceive('writeDialplanFile')->once()->andReturn(null);

    $rg = RingGroup::factory()->create([
        'tenant_id' => $this->tenant->id,
        'extension' => '5001',
        'strategy'  => 'simultaneous',
        'members'   => ['1001', '1002'],
    ]);

    $this->service->provision($rg);
});

it('removes ring group dialplan on deprovision', function () {
    $this->config->shouldReceive('removeDialplanFile')->once()->andReturn(null);

    $rg = RingGroup::factory()->create([
        'tenant_id' => $this->tenant->id,
        'extension' => '5002',
    ]);

    $this->service->deprovision($rg);
});

it('renders simultaneous bridge dial string', function () {
    $rg = RingGroup::factory()->make([
        'tenant_id' => $this->tenant->id,
        'extension' => '5003',
        'strategy'  => 'simultaneous',
        'members'   => ['1001', '1002'],
    ]);

    $this->config->shouldReceive('writeDialplanFile')->once()
        ->withArgs(function ($domain, $file, $xml) {
            return str_contains($xml, 'user/1001@rg-test.example.com,user/1002@rg-test.example.com');
        })
        ->andReturn(null);

    $rg->save();
    $this->service->provision($rg);
});

it('cross-tenant admin cannot modify another tenant ring group', function () {
    $other = Tenant::factory()->create(['sip_domain' => 'rg-other.example.com']);
    $rg    = RingGroup::factory()->create(['tenant_id' => $other->id]);

    actingAs($this->user)
        ->delete(route('pbx.ring-groups.destroy', $rg))
        ->assertStatus(404);
});
