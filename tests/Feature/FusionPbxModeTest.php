<?php

use App\Models\User;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\FusionPbxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config([
        'nexvary.installed' => true,
        'nexvary.freeswitch.fusionpbx' => true,
    ]);
});

function fusionPbxAdministrator(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin', 'web'));

    return $user;
}

it('restricts engine status to verified administrators', function () {
    $this->mock(FreeSwitchService::class)->shouldNotReceive('getStatus');

    $this->get('/settings/server')->assertRedirect('/login');
    $this->getJson('/api/pbx/status')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/settings/server')->assertForbidden();
    $this->actingAs(fusionPbxAdministrator())->withSession(['mfa_pending' => true])
        ->get('/settings/server')->assertRedirect('/mfa');
});

it('blocks direct management requests before controllers can change the PBX', function () {
    $this->mock(FreeSwitchService::class)->shouldNotReceive('bgapi');
    $this->actingAs(fusionPbxAdministrator());

    $this->post('/settings/server/freeswitch/restart')->assertForbidden();
    $this->post('/pbx/extensions')->assertForbidden();
    $this->post('/pbx/inbound-routes')->assertForbidden();
    $this->post('/admin/extensions/draft')->assertForbidden();
    $this->post('/admin/call-freeze/tenant')->assertForbidden();
    $this->delete('/call-center/live/test-call')->assertForbidden();
});

it('blocks direct XML writes, reload commands and restart commands', function () {
    expect(fn () => app(FusionPbxConfigService::class)->writeCdrConfig('https://example.test', 'test'))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    $service = app(FreeSwitchService::class);
    expect($service->api('reloadxml'))->toBeFalse()
        ->and($service->bgapi('fsctl restart'))->toBeFalse();
});

it('shows the live call count then refreshes it after hangup', function () {
    $service = $this->mock(FreeSwitchService::class);
    $service->shouldReceive('getStatus')->twice()->andReturn([
        'connected' => true, 'version' => '1.11.4', 'raw' => 'internal server details',
    ]);
    $service->shouldReceive('readActiveCallCount')->twice()->andReturn(1, 0);
    $service->shouldReceive('disconnect')->twice();

    $this->actingAs(fusionPbxAdministrator());
    foreach ([1, 0] as $count) {
        $this->get('/settings/server')->assertInertia(fn ($page) => $page
            ->component('Settings/Server')
            ->where('fusionPbx', true)
            ->where('pbx.connected', true)
            ->where('metrics.freeswitch', true)
            ->where('pbx.version', '1.11.4')
            ->where('pbx.active_calls', $count)
            ->missing('pbx.raw'));
    }
});

it('shows unavailable instead of zero when ESL cannot be reached', function () {
    $service = $this->mock(FreeSwitchService::class);
    $service->shouldReceive('getStatus')->once()->andReturn(['connected' => false, 'status' => 'unreachable']);
    $service->shouldNotReceive('readActiveCallCount');
    $service->shouldReceive('disconnect')->once();

    $this->actingAs(fusionPbxAdministrator())->get('/settings/server')->assertInertia(fn ($page) => $page
        ->where('pbx.connected', false)
        ->where('pbx.status', 'unreachable')
        ->where('pbx.active_calls', null));
});

it('preserves the existing restart path in the default mode', function () {
    config(['nexvary.freeswitch.fusionpbx' => false]);
    $this->mock(FreeSwitchService::class)->shouldReceive('bgapi')->with('fsctl restart')->once()->andReturn('+OK');

    $this->actingAs(fusionPbxAdministrator())->post('/settings/server/freeswitch/restart')
        ->assertRedirect('/settings/server');
});
