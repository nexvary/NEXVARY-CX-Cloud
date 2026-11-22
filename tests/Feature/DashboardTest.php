<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Mark as installed so the middleware passes
    file_put_contents(storage_path('installed'), '1');
});

afterEach(function () {
    @unlink(storage_path('installed'));
});

it('redirects unauthenticated users to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('shows dashboard for authenticated admin', function () {
    $user = User::factory()->create();
    $user->assignRole('superadmin');

    $this->actingAs($user)
         ->get('/dashboard')
         ->assertOk();
});
