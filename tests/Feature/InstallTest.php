<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('redirects to setup when not installed', function () {
    // Test via /login which only carries the 'installed' middleware (no 'auth'),
    // avoiding Laravel's default middleware priority that runs Authenticate before
    // custom route middlewares, which would redirect to /login before EnsureInstalled runs.
    $this->get('/login')->assertRedirect('/setup');
});

it('renders setup wizard page', function () {
    // X-Inertia header gets JSON response — do NOT send X-Inertia-Version
    // because withoutVite() means server version=null and any value causes 409
    $response = $this->withHeaders(['X-Inertia' => 'true'])
                     ->get('/setup');
    $response->assertOk();
});

it('validates admin step', function () {
    $this->post('/setup/step', [
        'step'        => 1,
        'admin_email' => 'not-an-email',
    ])->assertSessionHasErrors(['admin_name', 'admin_email']);
});
