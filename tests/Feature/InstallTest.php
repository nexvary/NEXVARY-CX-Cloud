<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('redirects to setup when not installed', function () {
    $this->get('/')->assertRedirect('/setup');
});

it('renders setup wizard page', function () {
    $this->get('/setup')->assertStatus(200);
});

it('validates admin step', function () {
    $this->post('/setup/step', [
        'step'        => 1,
        'admin_email' => 'not-an-email',
    ])->assertSessionHasErrors(['admin_name', 'admin_email']);
});
