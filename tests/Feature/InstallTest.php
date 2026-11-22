<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('redirects to setup when not installed', function () {
    // Ensure config treats app as not installed (overrides any env value)
    config(['nexvary.installed' => false]);

    $this->get('/')->assertRedirect('/setup');
});

it('renders setup wizard page', function () {
    // X-Inertia header gets JSON response instead of full HTML (no Vite manifest needed)
    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
                     ->get('/setup');
    $response->assertOk();
});

it('validates admin step', function () {
    $this->post('/setup/step', [
        'step'        => 1,
        'admin_email' => 'not-an-email',
    ])->assertSessionHasErrors(['admin_name', 'admin_email']);
});
