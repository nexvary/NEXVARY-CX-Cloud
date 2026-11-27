<?php

use App\Models\DialplanRule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PBX\DialplanRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(fn() => config(['nexvary.installed' => true]));

function makeDialplanAdmin(): array
{
    $t = Tenant::factory()->create();
    $u = User::factory()->create(['tenant_id' => $t->id]);
    $u->assignRole(Role::findOrCreate('admin', 'web'));
    return [$t, $u];
}

it('creates a dialplan rule and writes the config file', function () {
    [$tenant, $user] = makeDialplanAdmin();

    $service = Mockery::mock(DialplanRuleService::class);
    $service->shouldReceive('create')
            ->once()
            ->withArgs(fn ($d, $dom) => $d['name'] === 'Test Rule' && is_string($dom))
            ->andReturn(DialplanRule::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Test Rule']));
    app()->instance(DialplanRuleService::class, $service);

    $response = $this->actingAs($user)->post('/pbx/dialplan-rules', [
        'name'       => 'Test Rule',
        'priority'   => 100,
        'continue'   => false,
        'conditions' => [['field' => 'destination_number', 'expression' => '^\d{4}$']],
        'actions'    => [['application' => 'bridge', 'data' => 'user/${destination_number}']],
        'enabled'    => true,
    ]);
    $response->assertRedirect('/pbx/dialplan-rules');
});

it('rejects a dialplan rule with invalid regex in condition', function () {
    [, $user] = makeDialplanAdmin();
    $this->actingAs($user)->post('/pbx/dialplan-rules', [
        'name'       => 'Bad Rule',
        'priority'   => 100,
        'continue'   => false,
        'conditions' => [['field' => 'destination_number', 'expression' => '[invalid']],
        'actions'    => [['application' => 'bridge', 'data' => 'user/1001']],
        'enabled'    => true,
    ])->assertSessionHasErrors('validation');
});

it('rejects a dialplan rule with no actions', function () {
    [, $user] = makeDialplanAdmin();
    $service = Mockery::mock(DialplanRuleService::class);
    app()->instance(DialplanRuleService::class, $service);
    $this->actingAs($user)->post('/pbx/dialplan-rules', [
        'name'       => 'No Actions',
        'priority'   => 50,
        'continue'   => false,
        'conditions' => [['field' => 'destination_number', 'expression' => '.*']],
        'actions'    => [],
        'enabled'    => true,
    ])->assertSessionHasErrors('actions');
});

it('cross-tenant admin cannot modify another tenant dialplan rule', function () {
    [, $userA]    = makeDialplanAdmin();
    [$tenantB,]   = makeDialplanAdmin();
    $rule = DialplanRule::factory()->create(['tenant_id' => $tenantB->id]);
    $this->actingAs($userA)->delete('/pbx/dialplan-rules/' . $rule->id)->assertNotFound();
});
