<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['nexvary.installed' => true]);
    Role::findOrCreate('admin', 'web');
});

// ── Enrollment ────────────────────────────────────────────────────────────────

it('enrollment page generates a pending secret in the session', function () {
    $user = User::factory()->create(['mfa_enabled' => false]);

    $this->actingAs($user)
         ->get('/settings/mfa/enroll')
         ->assertOk()
         ->assertSessionHas('mfa_pending_secret');
});

it('enabling MFA with an invalid code returns an error', function () {
    $user   = User::factory()->create(['mfa_enabled' => false]);
    $secret = (new Google2FA)->generateSecretKey(32);

    $this->actingAs($user)
         ->withSession(['mfa_pending_secret' => $secret])
         ->post('/settings/mfa/enroll', ['code' => '000000'])
         ->assertSessionHasErrors('code');

    // Secret must NOT be persisted on failure
    expect($user->fresh()->mfa_enabled)->toBeFalse();
});

it('enabling MFA with a valid code stores encrypted secret and recovery codes', function () {
    $user   = User::factory()->create(['mfa_enabled' => false]);
    $totp   = new Google2FA;
    $secret = $totp->generateSecretKey(32);
    $code   = $totp->getCurrentOtp($secret);

    $this->actingAs($user)
         ->withSession(['mfa_pending_secret' => $secret])
         ->post('/settings/mfa/enroll', ['code' => $code])
         ->assertRedirect();

    $user->refresh();
    expect($user->mfa_enabled)->toBeTrue();
    expect($user->mfa_secret)->not->toBeNull();
    expect(Crypt::decryptString($user->mfa_secret))->toBe($secret);
    expect($user->mfa_recovery_codes)->toBeArray();
    expect(count($user->mfa_recovery_codes))->toBe(10);
});

// ── Recovery codes — single use ───────────────────────────────────────────────

it('a recovery code can only be used once', function () {
    $user   = User::factory()->create(['mfa_enabled' => false]);
    $totp   = new Google2FA;
    $secret = $totp->generateSecretKey(32);
    $code   = $totp->getCurrentOtp($secret);

    // Enroll
    $this->actingAs($user)
         ->withSession(['mfa_pending_secret' => $secret])
         ->post('/settings/mfa/enroll', ['code' => $code]);

    $user->refresh();
    $rawCodes = [];
    // Regenerate so we know the plaintext
    foreach (range(1, 10) as $_) {
        $rawCodes[] = strtoupper(implode('-', str_split(\Illuminate\Support\Str::random(12), 4)));
    }
    $user->update([
        'mfa_recovery_codes' => array_map(fn ($c) => Hash::make($c), $rawCodes),
    ]);

    $firstCode = $rawCodes[0];

    // First use: succeed
    $response = $this->actingAs($user)
        ->withSession(['mfa_pending' => true])
        ->post('/mfa', ['code' => $firstCode]);
    $response->assertRedirect();

    // Second use: fail — code was consumed
    $user->refresh();
    $this->actingAs($user)
         ->withSession(['mfa_pending' => true])
         ->post('/mfa', ['code' => $firstCode])
         ->assertSessionHasErrors('code');

    // Remaining 9 codes still available
    $user->refresh();
    expect(count($user->mfa_recovery_codes))->toBe(9);
});

// ── MFA login gate ────────────────────────────────────────────────────────────

it('a user with MFA enabled is redirected to /mfa after login', function () {
    $totp   = new Google2FA;
    $secret = $totp->generateSecretKey(32);
    $user   = User::factory()->create([
        'mfa_enabled'        => true,
        'mfa_secret'         => Crypt::encryptString($secret),
        'mfa_recovery_codes' => [],
    ]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
         ->assertRedirect('/mfa');
});

it('a user without MFA proceeds directly to dashboard after login', function () {
    $user = User::factory()->create(['mfa_enabled' => false]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
         ->assertRedirect('/dashboard');
});

it('/mfa is not accessible without an mfa_pending session flag', function () {
    // The /mfa route has auth middleware, so unauthenticated requests redirect to
    // login. This test verifies the mfa_pending guard: an authenticated user who
    // navigated directly to /mfa without going through the MFA login flow gets 404.
    $user = User::factory()->create(['mfa_enabled' => false]);
    $this->actingAs($user)->get('/mfa')->assertNotFound();
});

// ── Disable ───────────────────────────────────────────────────────────────────

it('disabling MFA requires both correct password and valid TOTP code', function () {
    $totp   = new Google2FA;
    $secret = $totp->generateSecretKey(32);
    $user   = User::factory()->create([
        'mfa_enabled'        => true,
        'mfa_secret'         => Crypt::encryptString($secret),
        'mfa_recovery_codes' => [],
        'password'           => Hash::make('correct-password!'),
    ]);

    // Wrong password
    $this->actingAs($user)
         ->post('/settings/mfa/disable', [
             'password' => 'wrong-password!!',
             'code'     => $totp->getCurrentOtp($secret),
         ])
         ->assertSessionHasErrors('password');

    // Right password, wrong code
    $this->actingAs($user)
         ->post('/settings/mfa/disable', [
             'password' => 'correct-password!',
             'code'     => '000000',
         ])
         ->assertSessionHasErrors('code');

    // Both correct
    $this->actingAs($user)
         ->post('/settings/mfa/disable', [
             'password' => 'correct-password!',
             'code'     => $totp->getCurrentOtp($secret),
         ])
         ->assertRedirect();

    expect($user->fresh()->mfa_enabled)->toBeFalse();
    expect($user->fresh()->mfa_secret)->toBeNull();
});

// ── Recovery code regeneration ────────────────────────────────────────────────

it('regenerating recovery codes requires the current password', function () {
    $totp   = new Google2FA;
    $secret = $totp->generateSecretKey(32);
    $user   = User::factory()->create([
        'mfa_enabled'        => true,
        'mfa_secret'         => Crypt::encryptString($secret),
        'mfa_recovery_codes' => [Hash::make('OLD-CODE-1234')],
        'password'           => Hash::make('correct-password!'),
    ]);

    $this->actingAs($user)
         ->post('/settings/mfa/recovery/regenerate', ['password' => 'wrong-password!!'])
         ->assertSessionHasErrors('password');

    $this->actingAs($user)
         ->post('/settings/mfa/recovery/regenerate', ['password' => 'correct-password!'])
         ->assertRedirect();

    // 10 fresh codes generated
    $user->refresh();
    expect(count($user->mfa_recovery_codes))->toBe(10);
});
