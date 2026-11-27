<?php

namespace App\Services\Telnyx;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telnyx Messaging API v2 integration service.
 *
 * Credentials are stored encrypted at rest and never exposed to the
 * browser or any client application. All API calls are server-side only.
 */
class TelnyxService
{
    private const API_BASE = 'https://api.telnyx.com/v2';

    public function __construct(
        private readonly string $apiKey,
    ) {}

    public static function make(): static
    {
        $encrypted = config('nexvary.telnyx.api_key');
        if (empty($encrypted)) {
            throw new \RuntimeException(
                'Telnyx API key not configured. Connect your Telnyx account in Settings → Integrations.'
            );
        }
        return new static(apiKey: Crypt::decryptString($encrypted));
    }

    public static function configured(): bool
    {
        return !empty(config('nexvary.telnyx.api_key'));
    }

    /**
     * Verify an incoming Telnyx webhook without requiring an API key.
     * Fails closed: returns false if the public key is not configured.
     */
    public static function verifyIncomingWebhook(Request $request): bool
    {
        $publicKey = config('nexvary.telnyx.webhook_public_key');
        if (empty($publicKey)) {
            return false;
        }
        return (new static(apiKey: ''))->verifyWebhookSignature($request);
    }

    // ── Account ───────────────────────────────────────────────────────────────

    public function validateConnection(): array
    {
        $response = $this->get('/messaging_profiles');
        return [
            'connected' => $response->successful(),
            'status_code' => $response->status(),
            'profiles_count' => $response->successful()
                ? count($response->json('data', []))
                : 0,
        ];
    }

    public function getMessagingProfiles(): array
    {
        $response = $this->get('/messaging_profiles', ['page[size]' => 25]);
        if (!$response->successful()) {
            Log::error('Telnyx: failed to fetch messaging profiles', [
                'status' => $response->status(),
            ]);
            return [];
        }
        return $response->json('data', []);
    }

    public function getPhoneNumbers(): array
    {
        $response = $this->get('/phone_numbers', [
            'filter[features]' => 'sms',
            'page[size]' => 100,
        ]);
        if (!$response->successful()) {
            return [];
        }
        return $response->json('data', []);
    }

    // ── SMS Outbound ──────────────────────────────────────────────────────────

    public function sendSms(
        string $from,
        string $to,
        string $text,
        ?string $messagingProfileId = null
    ): array {
        $payload = [
            'from' => $from,
            'to'   => $to,
            'text' => $text,
            'type' => 'SMS',
        ];
        if ($messagingProfileId) {
            $payload['messaging_profile_id'] = $messagingProfileId;
        }

        $response = $this->post('/messages', $payload);
        $data = $response->json('data', []);

        return [
            'success'    => $response->successful(),
            'message_id' => $data['id'] ?? null,
            'status'     => $data['to'][0]['status'] ?? 'unknown',
            'parts'      => $data['parts'] ?? 1,
            'cost'       => $data['cost'] ?? null,
            'error'      => $response->successful() ? null : ($response->json('errors.0.detail') ?? 'Unknown error'),
        ];
    }

    // ── Webhook signature verification (Ed25519) ──────────────────────────────

    public function verifyWebhookSignature(Request $request): bool
    {
        $publicKey = config('nexvary.telnyx.webhook_public_key');
        if (empty($publicKey)) {
            Log::critical('Telnyx: webhook_public_key not configured — rejecting webhook (fail-closed)');
            return false;
        }

        $signature   = $request->header('telnyx-signature-ed25519');
        $timestamp   = $request->header('telnyx-timestamp');
        $rawBody     = $request->getContent();

        if (!$signature || !$timestamp) {
            Log::warning('Telnyx: webhook missing signature headers');
            return false;
        }

        // Reject replays older than 5 minutes
        $age = abs(now()->timestamp - (int) $timestamp);
        if ($age > 300) {
            Log::warning('Telnyx: webhook timestamp too old', ['age_seconds' => $age]);
            return false;
        }

        // Signed message: timestamp|body
        $signedMessage = $timestamp . '|' . $rawBody;

        try {
            $publicKeyBin  = base64_decode($publicKey, strict: true);
            $signatureBin  = base64_decode($signature, strict: true);
        } catch (\Throwable) {
            return false;
        }

        if ($publicKeyBin === false || $signatureBin === false) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signatureBin, $signedMessage, $publicKeyBin);
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────

    private function get(string $path, array $query = []): \Illuminate\Http\Client\Response
    {
        return Http::withToken($this->apiKey)
            ->timeout(15)
            ->withQueryParameters($query)
            ->get(self::API_BASE . $path);
    }

    private function post(string $path, array $data): \Illuminate\Http\Client\Response
    {
        return Http::withToken($this->apiKey)
            ->timeout(15)
            ->post(self::API_BASE . $path, $data);
    }
}
