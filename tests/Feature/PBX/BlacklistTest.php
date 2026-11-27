<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

use App\Models\CallBlacklist;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PBX\BlacklistService;
use App\Services\PBX\FusionPbxConfigService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user   = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->config = Mockery::mock(FusionPbxConfigService::class)->makePartial();
    $this->config->shouldReceive('writeDialplanFile')->andReturn(null)->byDefault();
    $this->config->shouldReceive('removeDialplanFile')->andReturn(null)->byDefault();
    $this->service = new BlacklistService($this->config);
});

it('creates a blacklist entry and writes dialplan', function () {
    $this->config->shouldReceive('writeDialplanFile')->once()->andReturn(null);

    $entry = $this->service->create([
        'tenant_id'    => $this->tenant->id,
        'pattern'      => '+15550001234',
        'pattern_type' => 'exact',
        'direction'    => 'inbound',
        'enabled'      => true,
    ]);

    expect($entry->pattern)->toBe('+15550001234');
    expect(CallBlacklist::count())->toBe(1);
});

it('removes dialplan when disabled', function () {
    $this->config->shouldReceive('writeDialplanFile')->andReturn(null);
    $this->config->shouldReceive('removeDialplanFile')->once()->andReturn(null);

    $entry = $this->service->create([
        'tenant_id'    => $this->tenant->id,
        'pattern'      => '+15550000000',
        'pattern_type' => 'exact',
        'direction'    => 'inbound',
        'enabled'      => true,
    ]);

    $this->service->update($entry, ['enabled' => false]);
});

it('deletes entry and removes dialplan', function () {
    $this->config->shouldReceive('writeDialplanFile')->andReturn(null);
    $this->config->shouldReceive('removeDialplanFile')->once()->andReturn(null);

    $entry = $this->service->create([
        'tenant_id'    => $this->tenant->id,
        'pattern'      => '+15550000001',
        'pattern_type' => 'exact',
        'direction'    => 'inbound',
        'enabled'      => true,
    ]);

    $this->service->delete($entry);
    expect(CallBlacklist::count())->toBe(0);
});

it('denies cross-tenant access', function () {
    $other = Tenant::factory()->create();
    $entry = CallBlacklist::factory()->create(['tenant_id' => $other->id]);

    actingAs($this->user)
        ->delete(route('pbx.blacklist.destroy', $entry))
        ->assertStatus(404);
});
