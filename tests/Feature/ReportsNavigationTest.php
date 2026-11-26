<?php

use App\Models\CallRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(fn () => config(['nexvary.installed' => true]));

it('opens reports and exports filtered call records within the account tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    foreach ([
        [$tenant->id, '=match'],
        [$tenant->id, 'hidden-by-filter'],
        [Tenant::factory()->create()->id, 'match-other-tenant'],
    ] as [$tenantId, $caller]) {
        CallRecord::withoutGlobalScopes()->create([
            'uuid' => (string) Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenantId,
            'caller_id_number' => $caller,
            'destination_number' => '1002',
            'direction' => 'internal',
            'started_at' => now(),
        ]);
    }

    $this->actingAs($user)->get('/reports')->assertRedirect('/call-center/records');
    $response = $this->get('/call-center/records/export?search=match')
        ->assertDownload('call-detail-records.csv');
    expect($response->streamedContent())->toContain("'=match")
        ->not->toContain('hidden-by-filter', 'match-other-tenant');
});

it('restricts PBX email information to the administrator', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/email')->assertForbidden();
    $user->assignRole(Role::findOrCreate('superadmin', 'web'));
    $this->get('/email')->assertInertia(fn ($page) => $page->component('Settings/Email'));
});
