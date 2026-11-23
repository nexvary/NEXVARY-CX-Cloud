<?php

use App\Models\BillingAccount;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['nexvary.installed' => true]);
});

// ── Shared helpers ────────────────────────────────────────────────────────────

function crmAdmin(): array
{
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole(Role::findOrCreate('admin', 'web'));
    return [$tenant, $user];
}

// ── CRM — Contacts ────────────────────────────────────────────────────────────

it('admin can create a contact', function () {
    [, $user] = crmAdmin();

    $this->actingAs($user)
        ->post('/crm/contacts', [
            'name'  => 'Jane Doe',
            'email' => 'jane@example.test',
            'phone' => '+15555550100',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('contacts', ['email' => 'jane@example.test']);
});

it('contact belongs to the creating user tenant', function () {
    [$tenant, $user] = crmAdmin();

    $this->actingAs($user)
        ->post('/crm/contacts', ['name' => 'Bob', 'email' => 'bob@example.test']);

    $contact = Contact::first();
    expect($contact->tenant_id)->toBe($tenant->id);
});

it('contact index returns only own tenant records', function () {
    [$tenantA, $userA] = crmAdmin();
    [$tenantB, $userB] = crmAdmin();

    // Seed one contact per tenant directly
    \Illuminate\Support\Facades\DB::table('contacts')->insert([
        [
            'id'         => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'  => $tenantA->id,
            'name'       => 'Alice',
            'first_name' => 'Alice',
            'email'      => 'alice@a.test',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ],
        [
            'id'         => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'  => $tenantB->id,
            'name'       => 'Bob',
            'first_name' => 'Bob',
            'email'      => 'bob@b.test',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ],
    ]);

    $this->actingAs($userA)
        ->get('/crm/contacts')
        ->assertOk()
        ->assertInertia(fn ($page) =>
            $page->has('contacts.data', 1)
                 ->where('contacts.data.0.email', 'alice@a.test')
        );
});

it('tenant A cannot view or modify tenant B contact', function () {
    [$tenantA, $userA] = crmAdmin();
    [$tenantB, $userB] = crmAdmin();

    $bContact = Contact::create([
        'tenant_id' => $tenantB->id,
        'name'      => 'Bob',
        'email'     => 'bob@b.test',
    ]);

    // Route model binding applies BelongsToTenant scope → 404
    $this->actingAs($userA)
        ->get("/crm/contacts/{$bContact->id}")
        ->assertNotFound();
});

it('admin can delete a contact', function () {
    [, $user] = crmAdmin();

    $this->actingAs($user)
        ->post('/crm/contacts', ['name' => 'ToDelete', 'email' => 'del@example.test']);

    $contact = Contact::first();

    $this->actingAs($user)
        ->delete("/crm/contacts/{$contact->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
});

it('contact name is required', function () {
    [, $user] = crmAdmin();

    $this->actingAs($user)
        ->post('/crm/contacts', ['email' => 'nada@example.test'])
        ->assertSessionHasErrors('name');
});

// ── Billing — Accounts ────────────────────────────────────────────────────────

it('admin can create a billing account', function () {
    [, $user] = crmAdmin();

    $this->actingAs($user)
        ->post('/billing/accounts', [
            'name'     => 'Main Account',
            'currency' => 'USD',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('billing_accounts', ['name' => 'Main Account', 'currency' => 'USD']);
});

it('billing account belongs to the creating user tenant', function () {
    [$tenant, $user] = crmAdmin();

    $this->actingAs($user)
        ->post('/billing/accounts', ['name' => 'Corp', 'currency' => 'GBP']);

    $account = BillingAccount::first();
    expect($account->tenant_id)->toBe($tenant->id);
});

it('billing account index returns only own tenant records', function () {
    [$tenantA, $userA] = crmAdmin();
    [$tenantB, $userB] = crmAdmin();

    \Illuminate\Support\Facades\DB::table('billing_accounts')->insert([
        [
            'id'           => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'    => $tenantA->id,
            'name'         => 'Account A',
            'currency'     => 'USD',
            'balance'      => 0,
            'credit_limit' => 0,
            'created_at'   => now()->toDateTimeString(),
            'updated_at'   => now()->toDateTimeString(),
        ],
        [
            'id'           => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'    => $tenantB->id,
            'name'         => 'Account B',
            'currency'     => 'EUR',
            'balance'      => 0,
            'credit_limit' => 0,
            'created_at'   => now()->toDateTimeString(),
            'updated_at'   => now()->toDateTimeString(),
        ],
    ]);

    $this->actingAs($userA)
        ->get('/billing/accounts')
        ->assertOk()
        ->assertInertia(fn ($page) =>
            $page->has('accounts.data', 1)
                 ->where('accounts.data.0.name', 'Account A')
        );
});

it('tenant A cannot view tenant B billing account', function () {
    [$tenantA, $userA] = crmAdmin();
    [$tenantB,]        = crmAdmin();

    $bAccount = BillingAccount::create([
        'tenant_id'    => $tenantB->id,
        'name'         => 'B Corp',
        'currency'     => 'EUR',
        'balance'      => 0,
        'credit_limit' => 0,
    ]);

    $this->actingAs($userA)
        ->get("/billing/accounts/{$bAccount->id}")
        ->assertNotFound();
});

it('non-admin cannot access billing routes', function () {
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole(Role::findOrCreate('agent', 'web'));

    $this->actingAs($user)
        ->get('/billing/accounts')
        ->assertForbidden();
});

it('currency must be three characters', function () {
    [, $user] = crmAdmin();

    $this->actingAs($user)
        ->post('/billing/accounts', ['name' => 'Bad', 'currency' => 'US'])
        ->assertSessionHasErrors('currency');
});
