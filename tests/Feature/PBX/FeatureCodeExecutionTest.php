<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PBX\ExtensionService;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\FusionPbxConfigService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['sip_domain' => 'fcode-test.example.com']);
    $this->user   = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->fs     = Mockery::mock(FreeSwitchService::class);
    $this->config = Mockery::mock(FusionPbxConfigService::class)->makePartial();
    $this->config->shouldReceive('writeExtensionConfig')->andReturn(null)->byDefault();
    $this->config->shouldReceive('updateExtensionVariable')->andReturn(null)->byDefault();
    $this->config->shouldReceive('disableExtensionInFreeSWITCH')->andReturn(null)->byDefault();
    $this->config->shouldReceive('ensureDialplanContext')->andReturn(null)->byDefault();
    $this->service = new ExtensionService($this->config, $this->fs);
});

it('setDnd writes dnd_enabled to database', function () {
    $ext = Extension::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'dnd_enabled'=> false,
    ]);

    $this->service->setDnd($ext, true);

    expect($ext->fresh()->dnd_enabled)->toBeTrue();
});

it('setForward enables forwarding and stores destination', function () {
    $ext = Extension::factory()->create([
        'tenant_id'       => $this->tenant->id,
        'forward_enabled' => false,
    ]);

    $this->service->setForward($ext, '+15551234567');

    $ext->refresh();
    expect($ext->forward_enabled)->toBeTrue();
    expect($ext->forward_destination)->toBe('+15551234567');
});

it('setForward with null disables forwarding', function () {
    $ext = Extension::factory()->create([
        'tenant_id'          => $this->tenant->id,
        'forward_enabled'    => true,
        'forward_destination'=> '1002',
    ]);

    $this->service->setForward($ext, null);

    $ext->refresh();
    expect($ext->forward_enabled)->toBeFalse();
    expect($ext->forward_destination)->toBeNull();
});
