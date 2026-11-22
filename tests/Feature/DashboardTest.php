<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Mark as installed so EnsureInstalled middleware passes through
    config(['nexvary.installed' => true]);
});

it('redirects unauthenticated users to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('shows dashboard for authenticated admin', function () {
    $user = User::factory()->create();
    $user->assignRole('superadmin');

    // Send as Inertia request to get JSON instead of full HTML (skips Vite manifest)
    $this->actingAs($user)
         ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
         ->get('/dashboard')
         ->assertOk();
});
