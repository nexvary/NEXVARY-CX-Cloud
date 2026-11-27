<?php

use App\Models\SipProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\SipProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['nexvary.installed' => true]);
});

function makeSipProfileAdmin(): array
{
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole(Role::findOrCreate('admin', 'web'));
    return [$tenant, $user];
}

// ── CRUD ──────────────────────────────────────────────────────────────────────

it('creates a SIP profile record', function () {
    [, $user] = makeSipProfileAdmin();

    $response = $this->actingAs($user)->post('/pbx/sip-profiles', [
        'name'         => 'Internal Profile',
        'type'         => 'internal',
        'sip_port'     => 5060,
        'codecs'       => ['PCMU', 'PCMA', 'G722'],
        'nat_enabled'  => false,
        'rtp_timeout'  => 300,
        'rtp_hold_timeout' => 1800,
        'tls_enabled'  => false,
        'srtp_mode'    => 'disabled',
    ]);

    $response->assertRedirect('/pbx/sip-profiles');
    $this->assertDatabaseHas('sip_profiles', [
        'name'     => 'Internal Profile',
        'type'     => 'internal',
        'sip_port' => 5060,
    ]);
});

// ── Activate ──────────────────────────────────────────────────────────────────

it('activate writes config and returns activated status on ESL success', function () {
    [$tenant, $user] = makeSipProfileAdmin();

    $profile = SipProfile::factory()->create([
        'tenant_id' => $tenant->id,
        'type'      => 'internal',
        'enabled'   => false,
    ]);

    $service = Mockery::mock(SipProfileService::class);
    $service->shouldReceive('activate')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $profile->id))
            ->andReturn(['activated' => true, 'status' => 'activated', 'profile' => 'nexvary-internal-' . $profile->id]);
    app()->instance(SipProfileService::class, $service);

    $response = $this->actingAs($user)->post('/pbx/sip-profiles/' . $profile->id . '/activate');
    $response->assertOk()->assertJsonPath('activated', true);
});

it('activate returns 503 when ESL is unavailable', function () {
    [$tenant, $user] = makeSipProfileAdmin();

    $profile = SipProfile::factory()->create([
        'tenant_id' => $tenant->id,
        'type'      => 'internal',
        'enabled'   => false,
    ]);

    $service = Mockery::mock(SipProfileService::class);
    $service->shouldReceive('activate')
            ->once()
            ->andReturn(['activated' => false, 'status' => 'esl_unavailable']);
    app()->instance(SipProfileService::class, $service);

    $response = $this->actingAs($user)->post('/pbx/sip-profiles/' . $profile->id . '/activate');
    $response->assertStatus(503)->assertJsonPath('activated', false);
});

// ── Deactivate ────────────────────────────────────────────────────────────────

it('deactivate stops the profile and removes the config file', function () {
    [$tenant, $user] = makeSipProfileAdmin();

    $profile = SipProfile::factory()->create([
        'tenant_id' => $tenant->id,
        'type'      => 'internal',
        'enabled'   => true,
    ]);

    $service = Mockery::mock(SipProfileService::class);
    $service->shouldReceive('deactivate')
            ->once()
            ->andReturn(['status' => 'deactivated', 'profile' => 'nexvary-internal-' . $profile->id]);
    app()->instance(SipProfileService::class, $service);

    $response = $this->actingAs($user)->post('/pbx/sip-profiles/' . $profile->id . '/deactivate');
    $response->assertOk()->assertJsonPath('status', 'deactivated');
});

// ── Tenant isolation ──────────────────────────────────────────────────────────

it('cannot activate a SIP profile belonging to another tenant', function () {
    [, $user]        = makeSipProfileAdmin();
    $otherTenant     = Tenant::factory()->create();

    $profile = SipProfile::factory()->create([
        'tenant_id' => $otherTenant->id,
        'type'      => 'internal',
    ]);

    $response = $this->actingAs($user)->post('/pbx/sip-profiles/' . $profile->id . '/activate');
    $response->assertNotFound();
});
