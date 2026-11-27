<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

use App\Models\Tenant;
use App\Models\User;
use App\Services\PBX\ActiveCallService;
use App\Services\PBX\FreeSwitchService;

beforeEach(function () {
    $this->tenant  = Tenant::factory()->create();
    $this->user    = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->fs = Mockery::mock(FreeSwitchService::class);
    $this->fs->shouldReceive('connect')->andReturn(true)->byDefault();
    $this->fs->shouldReceive('disconnect')->andReturn(null)->byDefault();
    // Default: uuid belongs to our tenant
    $this->fs->shouldReceive('api')
        ->with(Mockery::pattern('/^uuid_getvar .+ domain_name$/'))
        ->andReturnUsing(fn() => $this->tenant->sipDomain())
        ->byDefault();

    $this->service = new ActiveCallService($this->fs);
});

it('holds a call and applies MoH class', function () {
    $uuid = 'test-uuid-hold';

    $this->fs->shouldReceive('setMoh')
        ->once()->with($uuid, 'default')
        ->andReturn(true);
    $this->fs->shouldReceive('holdCall')
        ->once()->with($uuid)
        ->andReturn(true);

    $result = $this->service->hold($uuid, $this->tenant->id, 'default');
    expect($result)->toBeTrue();
});

it('resumes a held call', function () {
    $uuid = 'test-uuid-resume';

    $this->fs->shouldReceive('resumeCall')
        ->once()->with($uuid)
        ->andReturn(true);

    $result = $this->service->resume($uuid, $this->tenant->id);
    expect($result)->toBeTrue();
});

it('performs blind transfer', function () {
    $uuid = 'test-uuid-xfer';

    $this->fs->shouldReceive('transferCall')
        ->once()->with($uuid, '1003', Mockery::type('string'))
        ->andReturn(true);

    $result = $this->service->blindTransfer($uuid, '1003', $this->tenant->id);
    expect($result)->toBeTrue();
});

it('denies hold from a different tenant', function () {
    $other = Tenant::factory()->create();
    $uuid  = 'test-uuid-denied';

    // ESL reports call belongs to other tenant
    $this->fs->shouldReceive('api')
        ->with("uuid_getvar {$uuid} domain_name")
        ->andReturn('other-tenant-' . uniqid() . '.pbx');

    $this->fs->shouldNotReceive('holdCall');

    $result = $this->service->hold($uuid, $this->tenant->id);
    expect($result)->toBeFalse();
});

it('parks a call and returns slot number', function () {
    $uuid = 'test-uuid-park';

    $this->fs->shouldReceive('parkCall')
        ->once()->with($uuid)
        ->andReturn('101');

    $slot = $this->service->park($uuid, $this->tenant->id);
    expect($slot)->toBe('101');
});

it('performed attended transfer bridges two legs', function () {
    $orig    = 'test-uuid-orig';
    $consult = 'test-uuid-consult';

    // Both legs verified as belonging to our tenant
    $this->fs->shouldReceive('api')
        ->with("uuid_getvar {$orig} domain_name")
        ->andReturnUsing(fn() => $this->tenant->sipDomain());
    $this->fs->shouldReceive('api')
        ->with("uuid_getvar {$consult} domain_name")
        ->andReturnUsing(fn() => $this->tenant->sipDomain());
    $this->fs->shouldReceive('attendedTransfer')
        ->once()->with($orig, $consult)
        ->andReturn(true);

    $result = $this->service->attendedTransfer($orig, $consult, $this->tenant->id);
    expect($result)->toBeTrue();
});
