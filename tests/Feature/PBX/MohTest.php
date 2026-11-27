<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

use App\Models\MohClass;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PBX\MohService;

beforeEach(function () {
    $this->tenant  = Tenant::factory()->create();
    $this->user    = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = new MohService();
});

it('creates a file-based MoH class', function () {
    $moh = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'name'      => 'Default',
        'source'    => 'files',
        'shuffle'   => true,
        'enabled'   => true,
    ]);

    expect($moh->name)->toBe('Default');
    expect(MohClass::count())->toBe(1);
});

it('renders stream MoH config', function () {
    $this->service->create([
        'tenant_id'  => $this->tenant->id,
        'name'       => 'Jazz',
        'source'     => 'stream_url',
        'stream_url' => 'example.com/jazz.mp3',
        'enabled'    => true,
    ]);

    $xml = $this->service->renderConfig($this->tenant->id);

    expect($xml)->toContain('shout://example.com/jazz.mp3');
    expect($xml)->toContain('name="Jazz"');
});

it('denies cross-tenant moh delete', function () {
    $other = Tenant::factory()->create();
    $moh   = MohClass::factory()->create(['tenant_id' => $other->id]);

    actingAs($this->user)
        ->delete(route('pbx.moh.destroy', $moh))
        ->assertStatus(404);
});
