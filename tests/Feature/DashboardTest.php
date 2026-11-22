<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Override default (not installed) so dashboard routes pass EnsureInstalled
    config(['nexvary.installed' => true]);
});

it('redirects unauthenticated users to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('shows dashboard for authenticated admin', function () {
    $user = User::factory()->create();
    $user->assignRole('superadmin');

    // Send as Inertia request — no X-Inertia-Version to avoid 409 version mismatch
    $this->actingAs($user)
         ->withHeaders(['X-Inertia' => 'true'])
         ->get('/dashboard')
         ->assertOk();
});
