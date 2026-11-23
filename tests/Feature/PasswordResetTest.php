<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['nexvary.installed' => true]);
});

// ── Forgot password ───────────────────────────────────────────────────────────

it('POST /forgot-password always returns sent status (prevents user enumeration)', function () {
    Notification::fake();

    $this->post('/forgot-password', ['email' => 'nonexistent@example.test'])
         ->assertRedirect()
         ->assertSessionHas('status');
});

it('sends a password reset notification to a registered user', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'alice@example.test']);

    $this->post('/forgot-password', ['email' => 'alice@example.test'])
         ->assertRedirect();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('POST /forgot-password rejects an invalid email format', function () {
    $this->post('/forgot-password', ['email' => 'not-an-email'])
         ->assertSessionHasErrors('email');
});

// ── Reset password ────────────────────────────────────────────────────────────

it('valid reset token changes the password and redirects to login', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'bob@example.test']);

    $token = Password::createToken($user);

    $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => 'bob@example.test',
            'password'              => 'NewStrongPass1',
            'password_confirmation' => 'NewStrongPass1',
        ])
        ->assertRedirect('/login');

    // Password was updated
    $user->refresh();
    expect(Hash::check('NewStrongPass1', $user->password))->toBeTrue();
});

it('invalid reset token returns an error', function () {
    $user = User::factory()->create(['email' => 'carol@example.test']);

    $this->post('/reset-password', [
            'token'                 => 'this-is-not-a-valid-token',
            'email'                 => 'carol@example.test',
            'password'              => 'NewStrongPass1',
            'password_confirmation' => 'NewStrongPass1',
        ])
        ->assertSessionHasErrors('email');
});

it('reset token is single-use — a second attempt with the same token fails', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'dave@example.test']);
    $token = Password::createToken($user);

    // First use — success
    $this->post('/reset-password', [
        'token'                 => $token,
        'email'                 => 'dave@example.test',
        'password'              => 'FirstNewPass1',
        'password_confirmation' => 'FirstNewPass1',
    ])->assertRedirect('/login');

    // Second use — same token now invalid
    $this->post('/reset-password', [
        'token'                 => $token,
        'email'                 => 'dave@example.test',
        'password'              => 'SecondNewPass1',
        'password_confirmation' => 'SecondNewPass1',
    ])->assertSessionHasErrors('email');
});

it('password must be at least 10 characters', function () {
    $user = User::factory()->create(['email' => 'eve@example.test']);
    $token = Password::createToken($user);

    $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => 'eve@example.test',
            'password'              => 'Short1',
            'password_confirmation' => 'Short1',
        ])
        ->assertSessionHasErrors('password');
});

it('password confirmation mismatch fails', function () {
    $user = User::factory()->create(['email' => 'fay@example.test']);
    $token = Password::createToken($user);

    $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => 'fay@example.test',
            'password'              => 'NewStrongPass1',
            'password_confirmation' => 'DifferentPass1',
        ])
        ->assertSessionHasErrors('password');
});

it('reset regenerates remember_token to invalidate remember-me sessions', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'grace@example.test']);
    $originalRemember = $user->remember_token;
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token'                 => $token,
        'email'                 => 'grace@example.test',
        'password'              => 'NewStrongPass1',
        'password_confirmation' => 'NewStrongPass1',
    ]);

    $user->refresh();
    expect($user->remember_token)->not->toBe($originalRemember);
});
