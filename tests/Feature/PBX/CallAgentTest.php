<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

use App\Models\CallAgent;
use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user   = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('admin can create a call agent', function () {
    actingAs($this->user)
        ->post(route('pbx.agents.store'), [
            'name'        => 'Alice Support',
            'status'      => 'available',
            'skill_level' => 3,
            'enabled'     => true,
        ])
        ->assertRedirect(route('pbx.agents.index'));

    expect(CallAgent::where('name', 'Alice Support')->exists())->toBeTrue();
});

it('agent belongs to creating tenant', function () {
    actingAs($this->user)
        ->post(route('pbx.agents.store'), [
            'name'   => 'Bob Agent',
            'status' => 'available',
        ]);

    expect(CallAgent::where('tenant_id', $this->tenant->id)->exists())->toBeTrue();
});

it('cross-tenant admin cannot access another tenant agent', function () {
    $other = Tenant::factory()->create();
    $agent = CallAgent::factory()->create(['tenant_id' => $other->id]);

    actingAs($this->user)
        ->delete(route('pbx.agents.destroy', $agent))
        ->assertStatus(404);
});

it('agent status can be updated via JSON endpoint', function () {
    $agent = CallAgent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status'    => 'available',
    ]);

    actingAs($this->user)
        ->postJson(route('pbx.agents.set-status', $agent), ['status' => 'on_break'])
        ->assertJson(['ok' => true, 'status' => 'on_break']);

    expect($agent->fresh()->status)->toBe('on_break');
});
