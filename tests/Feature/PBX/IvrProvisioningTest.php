<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

use App\Models\IvrMenu;
use App\Models\IvrEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\FusionPbxConfigService;
use App\Services\PBX\IvrProvisioningService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['sip_domain' => 'ivr-test.example.com']);
    $this->user   = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->config = Mockery::mock(FusionPbxConfigService::class)->makePartial();
    $this->config->shouldReceive('writeIvrMenu')->andReturn(null)->byDefault();
    $this->config->shouldReceive('removeIvrMenu')->andReturn(null)->byDefault();
    $this->fs     = Mockery::mock(FreeSwitchService::class);
    $this->fs->shouldReceive('connect')->andReturn(false)->byDefault();
    $this->service = new IvrProvisioningService($this->config, $this->fs);
});

it('writes IVR config on provision', function () {
    $this->config->shouldReceive('writeIvrMenu')->once()->andReturn(null);

    $menu = IvrMenu::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service->provision($menu);
});

it('removes IVR config on deprovision', function () {
    $this->config->shouldReceive('removeIvrMenu')->once()->andReturn(null);

    $menu = IvrMenu::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service->deprovision($menu);
});

it('cross-tenant admin cannot modify another tenant IVR', function () {
    $other = Tenant::factory()->create(['sip_domain' => 'ivr-other.example.com']);
    $menu  = IvrMenu::factory()->create(['tenant_id' => $other->id]);

    actingAs($this->user)
        ->delete(route('pbx.ivr.destroy', $menu))
        ->assertStatus(404);
});
