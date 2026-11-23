<?php

use App\Models\SmsConversation;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['nexvary.installed' => true]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeSmsAdmin(): array
{
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole(Role::findOrCreate('admin', 'web'));
    return [$tenant, $user];
}

/**
 * Seed a DID number assigned to a specific tenant.
 */
function seedDid(string $number, string $tenantId): void
{
    DB::table('dids')->insert([
        'id'          => Str::uuid(),
        'tenant_id'   => $tenantId,
        'number'      => $number,
        'sms_enabled' => true,
        'enabled'     => true,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
}

/**
 * Build and sign a Telnyx webhook payload.
 *
 * The signature is computed over the raw JSON string (timestamp|body).
 * Callers must POST the rawBody verbatim so $request->getContent() matches
 * exactly what was signed — posting a decoded array would give form-encoded
 * content that doesn't match.
 */
function buildWebhookRequest(array $payload, string $secretKey, ?int $timestamp = null): array
{
    $timestamp = $timestamp ?? now()->timestamp;
    $rawBody   = json_encode($payload);
    $signed    = $timestamp . '|' . $rawBody;
    $sig       = base64_encode(sodium_crypto_sign_detached($signed, $secretKey));

    return [
        'rawBody' => $rawBody,
        'headers' => [
            'telnyx-signature-ed25519' => $sig,
            'telnyx-timestamp'         => (string) $timestamp,
        ],
    ];
}

/**
 * Post a signed Telnyx webhook request with the correct JSON content type.
 *
 * Headers must be placed in the server array with the HTTP_ prefix so that
 * $request->getContent() returns exactly the signed JSON string and
 * $request->header() can find the signature and timestamp.
 */
function postWebhook(\Tests\TestCase $test, array $req): \Illuminate\Testing\TestResponse
{
    return $test->call(
        'POST',
        '/webhooks/telnyx',
        [],   // parameters (not used — body is in $content)
        [],   // cookies
        [],   // files
        [
            'CONTENT_TYPE'                     => 'application/json',
            'HTTP_TELNYX_SIGNATURE_ED25519'    => $req['headers']['telnyx-signature-ed25519'],
            'HTTP_TELNYX_TIMESTAMP'            => $req['headers']['telnyx-timestamp'],
        ],
        $req['rawBody']
    );
}

function inboundPayload(string $to, string $from, string $text = 'Hello'): array
{
    return [
        'data' => [
            'id'         => Str::uuid(),
            'event_type' => 'message.received',
            'payload'    => [
                'from' => ['phone_number' => $from],
                'to'   => [['phone_number' => $to]],
                'text' => $text,
                'id'   => Str::uuid(),
            ],
        ],
    ];
}

function statusPayload(string $eventType, string $messageId): array
{
    return [
        'data' => [
            'id'         => Str::uuid(),
            'event_type' => $eventType,
            'payload'    => ['id' => $messageId],
        ],
    ];
}

// ── Webhook signature verification ───────────────────────────────────────────

it('rejects webhook when webhook_public_key is not configured', function () {
    config(['nexvary.telnyx.webhook_public_key' => null]);

    $this->post('/webhooks/telnyx', [], [])->assertForbidden();
});

it('rejects webhook with an invalid Ed25519 signature', function () {
    $kp        = sodium_crypto_sign_keypair();
    $publicKey = base64_encode(sodium_crypto_sign_publickey($kp));
    config(['nexvary.telnyx.webhook_public_key' => $publicKey]);

    $rawBody = json_encode(inboundPayload('+10000000001', '+15550000001'));
    $this->call('POST', '/webhooks/telnyx', [], [], [], [
        'CONTENT_TYPE'                     => 'application/json',
        'HTTP_TELNYX_SIGNATURE_ED25519'    => base64_encode(str_repeat("\x00", SODIUM_CRYPTO_SIGN_BYTES)),
        'HTTP_TELNYX_TIMESTAMP'            => (string) now()->timestamp,
    ], $rawBody)->assertForbidden();
});

it('rejects webhook with a timestamp older than 5 minutes (replay protection)', function () {
    $kp        = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($kp);
    $publicKey = base64_encode(sodium_crypto_sign_publickey($kp));
    config(['nexvary.telnyx.webhook_public_key' => $publicKey]);

    $payload = inboundPayload('+10000000001', '+15550000001');
    $req     = buildWebhookRequest($payload, $secretKey, now()->subMinutes(6)->timestamp);

    postWebhook($this, $req)->assertForbidden();
});

// ── Inbound message: tenant resolution via DID ────────────────────────────────

it('creates inbound conversation with correct tenant_id resolved from DID ownership', function () {
    $kp        = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($kp);
    $publicKey = base64_encode(sodium_crypto_sign_publickey($kp));
    config(['nexvary.telnyx.webhook_public_key' => $publicKey]);

    [$tenant,] = makeSmsAdmin();
    seedDid('+10000000001', $tenant->id);

    $payload = inboundPayload('+10000000001', '+15550000001');
    $req     = buildWebhookRequest($payload, $secretKey);

    postWebhook($this, $req)->assertOk();

    $this->assertDatabaseHas('sms_conversations', [
        'did_number'     => '+10000000001',
        'contact_number' => '+15550000001',
        'tenant_id'      => $tenant->id,
    ]);

    $this->assertDatabaseHas('sms_messages', [
        'direction' => 'inbound',
        'status'    => 'received',
        'body'      => 'Hello',
    ]);
});

it('discards inbound message for a DID not assigned to any tenant', function () {
    $kp        = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($kp);
    $publicKey = base64_encode(sodium_crypto_sign_publickey($kp));
    config(['nexvary.telnyx.webhook_public_key' => $publicKey]);

    // No DID seeded for this number
    $payload = inboundPayload('+19990000000', '+15550000001');
    $req     = buildWebhookRequest($payload, $secretKey);

    postWebhook($this, $req)->assertOk();

    $this->assertDatabaseMissing('sms_conversations', ['did_number' => '+19990000000']);
    $this->assertDatabaseCount('sms_messages', 0);
});

it('discards inbound message for a DID seeded with no tenant_id', function () {
    $kp        = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($kp);
    $publicKey = base64_encode(sodium_crypto_sign_publickey($kp));
    config(['nexvary.telnyx.webhook_public_key' => $publicKey]);

    // DID exists but has no tenant assigned
    DB::table('dids')->insert([
        'id' => Str::uuid(), 'tenant_id' => null, 'number' => '+18880000001',
        'sms_enabled' => true, 'enabled' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $payload = inboundPayload('+18880000001', '+15550000001');
    $req     = buildWebhookRequest($payload, $secretKey);

    postWebhook($this, $req)->assertOk();

    $this->assertDatabaseCount('sms_messages', 0);
});

// ── Idempotency ───────────────────────────────────────────────────────────────

it('processes a duplicate webhook event idempotently (no duplicate message)', function () {
    $kp        = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($kp);
    $publicKey = base64_encode(sodium_crypto_sign_publickey($kp));
    config(['nexvary.telnyx.webhook_public_key' => $publicKey]);

    [$tenant,] = makeSmsAdmin();
    seedDid('+10000000002', $tenant->id);

    $eventId   = Str::uuid();
    $messageId = Str::uuid();
    $payload   = [
        'data' => [
            'id'         => $eventId,
            'event_type' => 'message.received',
            'payload'    => [
                'from' => ['phone_number' => '+15550000002'],
                'to'   => [['phone_number' => '+10000000002']],
                'text' => 'Duplicate test',
                'id'   => $messageId,
            ],
        ],
    ];
    $req = buildWebhookRequest($payload, $secretKey);

    // First delivery
    postWebhook($this, $req)->assertOk();
    $count1 = SmsMessage::where('telnyx_event_id', $eventId)->count();
    expect($count1)->toBe(1);

    // Re-delivery of same event (new timestamp so signature must be re-generated)
    $req2 = buildWebhookRequest($payload, $secretKey);
    postWebhook($this, $req2)->assertOk();
    $count2 = SmsMessage::where('telnyx_event_id', $eventId)->count();
    expect($count2)->toBe(1); // still exactly one — idempotent
});

// ── Delivery status updates ───────────────────────────────────────────────────

it('updates message status for a matching delivery event', function () {
    $kp        = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($kp);
    $publicKey = base64_encode(sodium_crypto_sign_publickey($kp));
    config(['nexvary.telnyx.webhook_public_key' => $publicKey]);

    [$tenant, $user] = makeSmsAdmin();
    $convo = SmsConversation::withoutGlobalScopes()->create([
        'id' => Str::uuid(), 'tenant_id' => $tenant->id,
        'did_number' => '+10000000003', 'contact_number' => '+15550000003',
        'status' => 'open',
    ]);
    $telnyxMessageId = Str::uuid();
    $convo->messages()->create([
        'body'              => 'Hello',
        'direction'         => 'outbound',
        'status'            => 'sent',
        'telnyx_message_id' => $telnyxMessageId,
        'sent_by'           => $user->id,
    ]);

    $payload = statusPayload('message.delivered', $telnyxMessageId);
    $req     = buildWebhookRequest($payload, $secretKey);

    postWebhook($this, $req)->assertOk();

    $this->assertDatabaseHas('sms_messages', [
        'telnyx_message_id' => $telnyxMessageId,
        'status'            => 'delivered',
    ]);
});

it('ignores delivery event for an unknown telnyx_message_id without touching other records', function () {
    $kp        = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($kp);
    $publicKey = base64_encode(sodium_crypto_sign_publickey($kp));
    config(['nexvary.telnyx.webhook_public_key' => $publicKey]);

    $payload = statusPayload('message.delivered', Str::uuid());
    $req     = buildWebhookRequest($payload, $secretKey);

    postWebhook($this, $req)->assertOk();

    // No crash, no data mutation
    $this->assertDatabaseCount('sms_messages', 0);
});

it('does not overwrite delivered status with a late sent event', function () {
    $kp        = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($kp);
    $publicKey = base64_encode(sodium_crypto_sign_publickey($kp));
    config(['nexvary.telnyx.webhook_public_key' => $publicKey]);

    [$tenant, $user] = makeSmsAdmin();
    $convo = SmsConversation::withoutGlobalScopes()->create([
        'id' => Str::uuid(), 'tenant_id' => $tenant->id,
        'did_number' => '+10000000004', 'contact_number' => '+15550000004',
        'status' => 'open',
    ]);
    $telnyxMessageId = Str::uuid();
    $convo->messages()->create([
        'body'              => 'Already delivered',
        'direction'         => 'outbound',
        'status'            => 'delivered',         // already at final state
        'telnyx_message_id' => $telnyxMessageId,
        'sent_by'           => $user->id,
        'delivered_at'      => now(),
    ]);

    // A late 'message.sent' event must not regress the status
    $payload = statusPayload('message.sent', $telnyxMessageId);
    $req     = buildWebhookRequest($payload, $secretKey);

    postWebhook($this, $req)->assertOk();

    $this->assertDatabaseHas('sms_messages', [
        'telnyx_message_id' => $telnyxMessageId,
        'status'            => 'delivered',         // unchanged
    ]);
});

// ── Cross-tenant isolation ────────────────────────────────────────────────────

it('prevents tenant A from reading tenant B SMS conversations via show route', function () {
    [$tenantA, $userA] = makeSmsAdmin();
    [$tenantB,]        = makeSmsAdmin();

    $convoB = SmsConversation::withoutGlobalScopes()->create([
        'id' => Str::uuid(), 'tenant_id' => $tenantB->id,
        'did_number' => '+10000000005', 'contact_number' => '+15550000005',
        'status' => 'open',
    ]);

    // Route model binding applies TenantScope — tenantA cannot resolve tenantB's conversation
    $this->actingAs($userA)->get("/sms/{$convoB->id}")->assertNotFound();
});

it('prevents tenant A from sending on a DID owned by tenant B', function () {
    [$tenantA, $userA] = makeSmsAdmin();
    [$tenantB,]        = makeSmsAdmin();

    // DID owned by tenant B
    seedDid('+10000000006', $tenantB->id);

    $convoB = SmsConversation::withoutGlobalScopes()->create([
        'id' => Str::uuid(), 'tenant_id' => $tenantB->id,
        'did_number' => '+10000000006', 'contact_number' => '+15550000006',
        'status' => 'open',
    ]);

    // tenantA tries to send on tenantB's conversation — blocked by both route model binding
    // (scope) AND tenantOwnsDid check.
    $this->actingAs($userA)
        ->post("/sms/{$convoB->id}", ['body' => 'Cross-tenant message'])
        ->assertNotFound(); // route model binding rejects it first
});

// ── Legacy null-tenant conversation ───────────────────────────────────────────

it('handles a legacy null-tenant conversation without creating a duplicate', function () {
    $kp        = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($kp);
    $publicKey = base64_encode(sodium_crypto_sign_publickey($kp));
    config(['nexvary.telnyx.webhook_public_key' => $publicKey]);

    [$tenant,] = makeSmsAdmin();
    seedDid('+10000000007', $tenant->id);

    // Pre-existing conversation with no tenant_id (legacy record)
    SmsConversation::withoutGlobalScopes()->create([
        'id'             => Str::uuid(),
        'tenant_id'      => null,
        'did_number'     => '+10000000007',
        'contact_number' => '+15550000007',
        'status'         => 'open',
    ]);

    $payload = inboundPayload('+10000000007', '+15550000007');
    $req     = buildWebhookRequest($payload, $secretKey);

    postWebhook($this, $req)->assertOk();

    // The unique constraint prevents a second conversation — only one exists
    $count = SmsConversation::withoutGlobalScopes()
        ->where('did_number', '+10000000007')
        ->where('contact_number', '+15550000007')
        ->count();
    expect($count)->toBe(1);

    // A message was added (to the existing legacy conversation)
    $this->assertDatabaseHas('sms_messages', [
        'direction' => 'inbound',
        'body'      => 'Hello',
    ]);
});
