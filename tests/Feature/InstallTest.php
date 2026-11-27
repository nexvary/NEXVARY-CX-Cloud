<?php

use App\Services\PBX\FreeSwitchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['nexvary.install_secret' => str_repeat('x', 32), 'session.secure' => false]);
});

it('redirects the login page to setup when not installed', function () {
    $this->get('/login')->assertRedirect('/setup');
});

it('renders the wizard without exposing provisioned passwords', function () {
    config(['database.connections.pgsql.password' => 'secret-database-value']);

    $this->get('/setup')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Install/Wizard')
        ->where('setupEnabled', true)
        ->missing('database.password')
        ->missing('redis.password'));
});

it('requires the provisioned setup key for actions', function () {
    $this->postJson('/setup/readiness')->assertForbidden();
    $this->postJson('/setup/complete', [], ['X-Setup-Token' => 'incorrect'])->assertForbidden();
});

it('blocks account creation when connection or account fields are invalid', function () {
    $this->postJson('/setup/complete', [], ['X-Setup-Token' => str_repeat('x', 32)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['database', 'redis', 'admin.name', 'admin.email', 'admin.password', 'locale']);
    $this->assertDatabaseCount('users', 0);
});

it('restricts setup permanently after initialization', function () {
    config(['nexvary.installed' => true]);

    $this->get('/setup')->assertNotFound();
    $this->postJson('/setup/complete', [], ['X-Setup-Token' => str_repeat('x', 32)])->assertNotFound();
});

it('returns a failed FreeSWITCH result from the injected service', function () {
    $this->mock(FreeSwitchService::class, function ($mock) {
        $mock->shouldReceive('probe')->once()->andReturn(false);
        $mock->shouldReceive('disconnect')->once();
    });

    $this->postJson('/setup/connection/freeswitch', [], ['X-Setup-Token' => str_repeat('x', 32)])
        ->assertOk()->assertExactJson(['ok' => false]);
});

it('rejects invalid service ports before trying to connect', function () {
    $this->postJson('/setup/connection/redis', ['redis' => ['host' => '127.0.0.1', 'port' => 70000]],
        ['X-Setup-Token' => str_repeat('x', 32)])
        ->assertUnprocessable()->assertJsonValidationErrors('redis.port');
});
