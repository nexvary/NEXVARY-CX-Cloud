<?php

use App\Models\CallQueue;
use App\Models\CallRecord;
use App\Models\IvrMenu;
use App\Models\RingGroup;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\FusionPbxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['nexvary.installed' => true]);
});

// ── Shared helpers ────────────────────────────────────────────────────────────

function pbxAdmin(): array
{
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole(Role::findOrCreate('admin', 'web'));
    return [$tenant, $user];
}

function mockPbx(): object
{
    $pbx = Mockery::mock(FreeSwitchService::class);
    app()->instance(FreeSwitchService::class, $pbx);
    return $pbx;
}

function mockPbxAndConfig(): array
{
    $pbx    = Mockery::mock(FreeSwitchService::class);
    $config = Mockery::mock(FusionPbxConfigService::class);
    app()->instance(FreeSwitchService::class, $pbx);
    app()->instance(FusionPbxConfigService::class, $config);
    return [$pbx, $config];
}

// ── Call Queues ───────────────────────────────────────────────────────────────

it('admin can create a call queue', function () {
    [, $user] = pbxAdmin();
    $pbx = mockPbx();
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)
        ->post('/pbx/queues', [
            'name'     => 'Support Queue',
            'strategy' => 'ring-all',
            'timeout'  => 60,
        ])
        ->assertRedirect('/pbx/queues');

    $this->assertDatabaseHas('call_queues', ['name' => 'Support Queue', 'strategy' => 'ring-all']);
});

it('queue name must be unique within the tenant scope', function () {
    [$tenant, $user] = pbxAdmin();
    $pbx = mockPbx();
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    // First queue succeeds
    $this->actingAs($user)
        ->post('/pbx/queues', ['name' => 'Sales', 'strategy' => 'ring-all'])
        ->assertRedirect('/pbx/queues');

    // Duplicate name should fail validation
    $this->actingAs($user)
        ->post('/pbx/queues', ['name' => 'Sales', 'strategy' => 'ring-all'])
        ->assertSessionHasErrors('name');
});

it('admin can update a call queue and dialplan reloads', function () {
    [$tenant, $user] = pbxAdmin();
    $pbx = mockPbx();
    $pbx->shouldReceive('reloadDialplan')->twice()->andReturn(true);

    $this->actingAs($user)
        ->post('/pbx/queues', ['name' => 'OldQueue', 'strategy' => 'ring-all'])
        ->assertRedirect();

    $queue = CallQueue::first();

    $this->actingAs($user)
        ->patch("/pbx/queues/{$queue->id}", ['name' => 'NewQueue', 'strategy' => 'sequential', 'timeout' => 120])
        ->assertRedirect('/pbx/queues');

    expect($queue->fresh()->name)->toBe('NewQueue');
});

it('admin can delete a call queue', function () {
    [$tenant, $user] = pbxAdmin();
    $pbx = mockPbx();
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    // Create directly so the delete request is the only reloadDialplan trigger.
    $queue = CallQueue::create([
        'tenant_id' => $tenant->id,
        'name'      => 'ToDelete',
        'strategy'  => 'ring-all',
    ]);

    $this->actingAs($user)
        ->delete("/pbx/queues/{$queue->id}")
        ->assertRedirect('/pbx/queues');

    $this->assertDatabaseMissing('call_queues', ['id' => $queue->id]);
});

it('non-admin cannot access queue routes', function () {
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole(Role::findOrCreate('agent', 'web'));

    $this->actingAs($user)
        ->get('/pbx/queues')
        ->assertForbidden();
});

it('queue from another tenant is not resolvable via route model binding', function () {
    [, $userA] = pbxAdmin();
    [$tenantB, $userB] = pbxAdmin();
    $pbx = mockPbx();
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    // Create queue as tenant B
    $this->actingAs($userB)
        ->post('/pbx/queues', ['name' => 'B-Queue', 'strategy' => 'ring-all']);

    $bQueue = CallQueue::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->first();

    // Tenant A cannot edit tenant B's queue — route model binding applies TenantScope
    $this->actingAs($userA)
        ->get("/pbx/queues/{$bQueue->id}/edit")
        ->assertNotFound();
});

// ── IVR Menus ─────────────────────────────────────────────────────────────────

it('admin can create an IVR menu', function () {
    [, $user] = pbxAdmin();
    $pbx = mockPbx();
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)
        ->post('/pbx/ivr', [
            'name'         => 'Main Menu',
            'greeting_tts' => 'Press 1 for sales',
            'timeout'      => 10,
            'max_failures' => 3,
        ])
        ->assertRedirect('/pbx/ivr');

    $this->assertDatabaseHas('ivr_menus', ['name' => 'Main Menu']);
});

it('admin can delete an IVR menu and dialplan reloads', function () {
    [, $user] = pbxAdmin();
    $pbx = mockPbx();
    $pbx->shouldReceive('reloadDialplan')->twice()->andReturn(true);

    $this->actingAs($user)
        ->post('/pbx/ivr', ['name' => 'OldIVR', 'timeout' => 5, 'max_failures' => 2]);

    $ivr = IvrMenu::first();

    $this->actingAs($user)
        ->delete("/pbx/ivr/{$ivr->id}")
        ->assertRedirect('/pbx/ivr');

    $this->assertDatabaseMissing('ivr_menus', ['id' => $ivr->id]);
});

it('IVR name must be unique', function () {
    [, $user] = pbxAdmin();
    $pbx = mockPbx();
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)
        ->post('/pbx/ivr', ['name' => 'Main Menu', 'timeout' => 10, 'max_failures' => 3]);

    $this->actingAs($user)
        ->post('/pbx/ivr', ['name' => 'Main Menu', 'timeout' => 10, 'max_failures' => 3])
        ->assertSessionHasErrors('name');
});

// ── Ring Groups ───────────────────────────────────────────────────────────────

it('admin can create a ring group with members', function () {
    [, $user] = pbxAdmin();
    $pbx = mockPbx();
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    $this->actingAs($user)
        ->post('/pbx/ring-groups', [
            'name'      => 'Sales Team',
            'extension' => '2000',
            'strategy'  => 'simultaneous',
            'timeout'   => 30,
            'members'   => ['1001', '1002', '1003'],
        ])
        ->assertRedirect('/pbx/ring-groups');

    $rg = RingGroup::first();
    expect($rg)->not->toBeNull();
    expect($rg->members()->count())->toBe(3);
});

it('admin can update a ring group and members are replaced', function () {
    [, $user] = pbxAdmin();
    $pbx = mockPbx();
    $pbx->shouldReceive('reloadDialplan')->twice()->andReturn(true);

    $this->actingAs($user)
        ->post('/pbx/ring-groups', [
            'name'      => 'Old Group',
            'extension' => '2001',
            'strategy'  => 'sequential',
            'members'   => ['1001', '1002'],
        ]);

    $rg = RingGroup::first();

    $this->actingAs($user)
        ->patch("/pbx/ring-groups/{$rg->id}", [
            'name'      => 'New Group',
            'strategy'  => 'simultaneous',
            'members'   => ['1003'],
        ])
        ->assertRedirect('/pbx/ring-groups');

    $rg->refresh();
    expect($rg->name)->toBe('New Group');
    expect($rg->members()->count())->toBe(1);
    expect($rg->members()->first()->number)->toBe('1003');
});

it('admin can delete a ring group and its members are removed', function () {
    [$tenant, $user] = pbxAdmin();
    $pbx = mockPbx();
    $pbx->shouldReceive('reloadDialplan')->once()->andReturn(true);

    // Create the ring group directly so the delete request is the only
    // one that triggers reloadDialplan (avoids a twice() race with mocks).
    $rg = RingGroup::create([
        'tenant_id' => $tenant->id,
        'name'      => 'ToDelete',
        'extension' => '2099',
        'strategy'  => 'simultaneous',
        'timeout'   => 30,
    ]);
    $rg->members()->create(['number' => '1001']);

    $this->actingAs($user)
        ->delete("/pbx/ring-groups/{$rg->id}")
        ->assertRedirect('/pbx/ring-groups');

    // RingGroup uses SoftDeletes — the row is soft-deleted, not physically removed.
    $this->assertSoftDeleted('ring_groups', ['id' => $rg->id]);
    expect(\App\Models\RingGroupMember::where('ring_group_id', $rg->id)->count())->toBe(0);
});

// ── Call Records (tenant-scoped) ──────────────────────────────────────────────

it('call records index returns only own tenant records', function () {
    [$tenantA, $userA] = pbxAdmin();
    [$tenantB,]        = pbxAdmin();

    // Seed records for both tenants directly
    \Illuminate\Support\Facades\DB::table('call_records')->insert([
        [
            'id'                  => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'           => $tenantA->id,
            'uuid'                => (string) \Illuminate\Support\Str::uuid(),
            'caller_id_number'    => '1001',
            'caller_id_name'      => 'Alice',
            'destination_number'  => '2001',
            'direction'           => 'inbound',
            'duration_seconds'    => 30,
            'billable_seconds'    => 25,
            'hangup_cause'        => 'NORMAL_CLEARING',
            'started_at'          => now()->toDateTimeString(),
            'answered_at'         => now()->toDateTimeString(),
            'ended_at'            => now()->toDateTimeString(),
            'created_at'          => now()->toDateTimeString(),
            'updated_at'          => now()->toDateTimeString(),
        ],
        [
            'id'                  => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'           => $tenantB->id,
            'uuid'                => (string) \Illuminate\Support\Str::uuid(),
            'caller_id_number'    => '2001',
            'caller_id_name'      => 'Bob',
            'destination_number'  => '3001',
            'direction'           => 'outbound',
            'duration_seconds'    => 60,
            'billable_seconds'    => 55,
            'hangup_cause'        => 'NORMAL_CLEARING',
            'started_at'          => now()->toDateTimeString(),
            'answered_at'         => now()->toDateTimeString(),
            'ended_at'            => now()->toDateTimeString(),
            'created_at'          => now()->toDateTimeString(),
            'updated_at'          => now()->toDateTimeString(),
        ],
    ]);

    $this->actingAs($userA)
        ->get('/call-center/records')
        ->assertOk()
        ->assertInertia(fn ($page) =>
            $page->has('records.data', 1)
                 ->where('records.data.0.caller_id_number', '1001')
        );
});

// ── Voicemail ─────────────────────────────────────────────────────────────────

it('voicemail index is accessible to admin and returns a page', function () {
    [, $user] = pbxAdmin();

    $this->actingAs($user)
        ->get('/pbx/voicemail')
        ->assertOk();
});
