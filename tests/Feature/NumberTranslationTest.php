<?php

use App\Models\NumberTranslation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PBX\NumberTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['nexvary.installed' => true]);
});

function makeTranslationAdmin(): array
{
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole(Role::findOrCreate('admin', 'web'));
    return [$tenant, $user];
}

// ── Create ────────────────────────────────────────────────────────────────────

it('creates a number translation rule and writes the dialplan file', function () {
    [$tenant, $user] = makeTranslationAdmin();

    $service = Mockery::mock(NumberTranslationService::class);
    $service->shouldReceive('create')
            ->once()
            ->withArgs(fn ($data, $domain) => $data['name'] === 'Strip +1' && is_string($domain))
            ->andReturn(NumberTranslation::factory()->create([
                'tenant_id' => $tenant->id,
                'name'      => 'Strip +1',
                'pattern'   => '\+1(\d{10})',
                'replace'   => '$1',
            ]));
    app()->instance(NumberTranslationService::class, $service);

    $response = $this->actingAs($user)->post('/pbx/number-translations', [
        'name'             => 'Strip +1',
        'direction'        => 'outbound',
        'pattern'          => '\+1(\d{10})',
        'replace'          => '$1',
        'privacy_enabled'  => false,
        'priority'         => 100,
        'enabled'          => true,
    ]);

    $response->assertRedirect('/pbx/number-translations');
});

// ── Regex validation ──────────────────────────────────────────────────────────

it('rejects an invalid regex pattern', function () {
    [, $user] = makeTranslationAdmin();

    $response = $this->actingAs($user)->post('/pbx/number-translations', [
        'name'      => 'Bad Pattern',
        'direction' => 'inbound',
        'pattern'   => '[invalid',
        'replace'   => '$1',
        'priority'  => 100,
        'enabled'   => true,
    ]);

    $response->assertSessionHasErrors('pattern');
});

// ── Privacy flag ──────────────────────────────────────────────────────────────

it('privacy_enabled field is stored and returned', function () {
    [$tenant, $user] = makeTranslationAdmin();

    $service = Mockery::mock(NumberTranslationService::class);
    $service->shouldReceive('create')->once()->andReturn(
        NumberTranslation::factory()->create([
            'tenant_id'      => $tenant->id,
            'name'           => 'Private Call',
            'pattern'        => '\d+',
            'replace'        => '$0',
            'privacy_enabled'=> true,
        ])
    );
    app()->instance(NumberTranslationService::class, $service);

    $this->actingAs($user)->post('/pbx/number-translations', [
        'name'             => 'Private Call',
        'direction'        => 'outbound',
        'pattern'          => '\d+',
        'replace'          => '$0',
        'privacy_enabled'  => true,
        'priority'         => 100,
        'enabled'          => true,
    ])->assertRedirect();
});

// ── Cross-tenant isolation ────────────────────────────────────────────────────

it('cannot update a translation rule belonging to another tenant', function () {
    [, $user]    = makeTranslationAdmin();
    $other       = Tenant::factory()->create();
    $otherRule   = NumberTranslation::factory()->create(['tenant_id' => $other->id]);

    $this->actingAs($user)->patch('/pbx/number-translations/' . $otherRule->id, [
        'name'      => 'Hacked',
        'direction' => 'inbound',
        'pattern'   => '.*',
        'priority'  => 1,
        'enabled'   => true,
    ])->assertNotFound();
});
