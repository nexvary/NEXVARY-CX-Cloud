<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

use App\Models\BusinessHours;
use App\Models\DayNightMode;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PBX\BusinessHoursService;
use App\Services\PBX\FusionPbxConfigService;

beforeEach(function () {
    $this->tenant  = Tenant::factory()->create();
    $this->user    = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->config  = Mockery::mock(FusionPbxConfigService::class)->makePartial();
    $this->config->shouldReceive('writeDialplanFile')->andReturn(null)->byDefault();
    $this->config->shouldReceive('removeDialplanFile')->andReturn(null)->byDefault();
    $this->service = new BusinessHoursService($this->config);
});

it('creates a business hours schedule and writes dialplan', function () {
    $this->config->shouldReceive('writeDialplanFile')->once()->andReturn(null);

    $bh = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'name'      => 'Standard',
        'schedule'  => [
            ['day' => '2-6', 'open_time' => '09:00:00', 'close_time' => '17:00:00'],
        ],
        'timezone' => 'America/New_York',
        'enabled'  => true,
    ]);

    expect($bh->name)->toBe('Standard');
    expect(BusinessHours::count())->toBe(1);
});

it('toggles day/night mode', function () {
    $dnm = $this->service->createDayNight([
        'tenant_id'       => $this->tenant->id,
        'name'            => 'Main',
        'mode'            => 'day',
        'day_destination' => '1001',
        'enabled'         => true,
    ]);

    $dnm = $this->service->toggleMode($dnm);
    expect($dnm->mode)->toBe('night');

    $dnm = $this->service->toggleMode($dnm);
    expect($dnm->mode)->toBe('day');
});

it('removes dialplan when schedule disabled', function () {
    $this->config->shouldReceive('writeDialplanFile')->andReturn(null);
    $this->config->shouldReceive('removeDialplanFile')->once()->andReturn(null);

    $bh = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'name'      => 'Test',
        'schedule'  => [],
        'timezone'  => 'UTC',
        'enabled'   => true,
    ]);

    $this->service->update($bh, ['enabled' => false]);
});

it('denies cross-tenant access to day/night toggle', function () {
    $other = Tenant::factory()->create();
    $dnm   = DayNightMode::factory()->create(['tenant_id' => $other->id, 'mode' => 'day']);

    actingAs($this->user)
        ->post(route('pbx.business-hours.toggle-day-night', $dnm))
        ->assertStatus(404);
});
