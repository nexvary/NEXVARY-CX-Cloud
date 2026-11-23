<?php

use App\Models\CallFreeze;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PBX\CallFreezeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['nexvary.installed' => true]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeAdminWithTenant(): array
{
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole(Role::findOrCreate('admin', 'web'));
    return [$tenant, $user];
}

function seedFreeze(Tenant $tenant, User $activatedBy): CallFreeze
{
    return CallFreeze::create([
        'scope'        => 'tenant',
        'tenant_id'    => $tenant->id,
        'trunk_id'     => null,
        'reason'       => 'Test freeze',
        'activated_by' => $activatedBy->id,
        'activated_at' => now(),
        'active'       => true,
    ]);
}

// ── Users without MFA ─────────────────────────────────────────────────────────

it('allows a non-MFA user to deactivate a freeze without a code', function () {
    [$tenant, $user] = makeAdminWithTenant();
    // Confirm no MFA
    expect($user->mfa_enabled)->toBeFalse();

    $freeze = seedFreeze($tenant, $user);

    $this->actingAs($user)
        ->post("/admin/call-freeze/{$freeze->id}/deactivate")
        ->assertRedirect();

    $this->assertDatabaseHas('call_freezes', [
        'id'     => $freeze->id,
        'active' => false,
    ]);
});

// ── Users with MFA ────────────────────────────────────────────────────────────

it('blocks a MFA user from deactivating a freeze without a code', function () {
    [$tenant, $user] = makeAdminWithTenant();

    $g2fa   = new Google2FA;
    $secret = $g2fa->generateSecretKey();
    $user->update([
        'mfa_enabled' => true,
        'mfa_secret'  => Crypt::encryptString($secret),
    ]);

    $freeze = seedFreeze($tenant, $user);

    $this->actingAs($user)
        ->post("/admin/call-freeze/{$freeze->id}/deactivate")
        ->assertSessionHasErrors('code');

    // Freeze must remain active
    $this->assertDatabaseHas('call_freezes', ['id' => $freeze->id, 'active' => true]);
});

it('blocks a MFA user from deactivating with an invalid code', function () {
    [$tenant, $user] = makeAdminWithTenant();

    $g2fa   = new Google2FA;
    $secret = $g2fa->generateSecretKey();
    $user->update([
        'mfa_enabled' => true,
        'mfa_secret'  => Crypt::encryptString($secret),
    ]);

    $freeze = seedFreeze($tenant, $user);

    $this->actingAs($user)
        ->post("/admin/call-freeze/{$freeze->id}/deactivate", ['code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->assertDatabaseHas('call_freezes', ['id' => $freeze->id, 'active' => true]);
});

it('allows a MFA user to deactivate a freeze with a valid TOTP code', function () {
    [$tenant, $user] = makeAdminWithTenant();

    $g2fa   = new Google2FA;
    $secret = $g2fa->generateSecretKey();
    $user->update([
        'mfa_enabled' => true,
        'mfa_secret'  => Crypt::encryptString($secret),
    ]);

    $freeze = seedFreeze($tenant, $user);

    $validCode = $g2fa->getCurrentOtp($secret);

    $this->actingAs($user)
        ->post("/admin/call-freeze/{$freeze->id}/deactivate", ['code' => $validCode])
        ->assertRedirect();

    $this->assertDatabaseHas('call_freezes', ['id' => $freeze->id, 'active' => false]);
});
