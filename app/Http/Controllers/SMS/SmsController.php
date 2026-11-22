<?php

namespace App\Http\Controllers\SMS;

use App\Http\Controllers\Controller;
use App\Models\SmsConversation;
use App\Models\SmsMessage;
use App\Services\Telnyx\TelnyxService;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SmsController extends Controller
{
    // ── Web UI ────────────────────────────────────────────────────────────────

    public function index(): Response
    {
        return Inertia::render('SMS/Index', [
            'conversations'    => SmsConversation::with('latestMessage')
                ->latest('last_message_at')
                ->paginate(30),
            'telnyx_available' => TelnyxService::configured(),
        ]);
    }

    public function show(SmsConversation $conversation): Response
    {
        $conversation->load('messages');
        $conversation->update(['status' => 'read']);

        return Inertia::render('SMS/Index', [
            'conversations'       => SmsConversation::latest('last_message_at')->paginate(30),
            'active_conversation' => $conversation,
            'telnyx_available'    => TelnyxService::configured(),
        ]);
    }

    public function send(Request $request, SmsConversation $conversation): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:1600']]);

        if (!TelnyxService::configured()) {
            return redirect()->back()->withErrors(['body' => 'Telnyx is not configured. Connect your account in Settings → Integrations.']);
        }

        try {
            $telnyx = TelnyxService::make();
            $result = $telnyx->sendSms(
                from: $conversation->did_number,
                to:   $conversation->contact_number,
                text: $request->input('body'),
            );

            DB::transaction(function () use ($conversation, $request, $result) {
                $message = $conversation->messages()->create([
                    'body'              => $request->input('body'),
                    'direction'         => 'outbound',
                    'status'            => $result['success'] ? 'sent' : 'failed',
                    'telnyx_message_id' => $result['message_id'],
                    'error_message'     => $result['error'],
                    'cost'              => $result['cost'] ? (float) ($result['cost']['amount'] ?? 0) : null,
                    'sent_at'           => $result['success'] ? now() : null,
                    'sent_by'           => auth()->id(),
                ]);

                $conversation->update(['last_message_at' => now()]);

                return $message;
            });

            if (!$result['success']) {
                return redirect()->back()->withErrors(['body' => 'SMS delivery failed: ' . ($result['error'] ?? 'Unknown error')]);
            }
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors(['body' => $e->getMessage()]);
        }

        return redirect()->route('sms.show', $conversation);
    }

    public function compose(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'to'   => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'from' => ['required', 'string'],
            'body' => ['required', 'string', 'max:1600'],
        ]);

        if (!TelnyxService::configured()) {
            return redirect()->back()->withErrors(['body' => 'Telnyx is not configured.']);
        }

        try {
            $telnyx = TelnyxService::make();
            $result = $telnyx->sendSms(
                from: $request->input('from'),
                to:   $request->input('to'),
                text: $request->input('body'),
            );

            $convo = DB::transaction(function () use ($request, $result) {
                $convo = SmsConversation::firstOrCreate(
                    [
                        'did_number'     => $request->input('from'),
                        'contact_number' => $request->input('to'),
                    ],
                    ['status' => 'open'],
                );
                $convo->messages()->create([
                    'body'              => $request->input('body'),
                    'direction'         => 'outbound',
                    'status'            => $result['success'] ? 'sent' : 'failed',
                    'telnyx_message_id' => $result['message_id'],
                    'error_message'     => $result['error'],
                    'cost'              => $result['cost'] ? (float) ($result['cost']['amount'] ?? 0) : null,
                    'sent_at'           => $result['success'] ? now() : null,
                    'sent_by'           => auth()->id(),
                ]);
                $convo->update(['last_message_at' => now()]);
                return $convo;
            });

            if (!$result['success']) {
                return redirect()->back()->withErrors(['body' => 'SMS delivery failed: ' . ($result['error'] ?? 'Unknown error')]);
            }

            return redirect()->route('sms.show', $convo);
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors(['body' => $e->getMessage()]);
        }
    }

    // ── Webhook (Telnyx → server) ─────────────────────────────────────────────

    /**
     * Processes inbound Telnyx webhook events.
     *
     * Security requirements:
     *  - Ed25519 signature MUST be verified before any processing
     *  - If webhook_public_key is not configured, we FAIL CLOSED (403)
     *  - Replay protection: reject events older than 5 minutes
     *  - Idempotency: telnyx_event_id prevents duplicate record creation
     */
    public function webhook(Request $request): HttpResponse
    {
        // ── 1. Fail closed: reject if no public key configured ────────────────
        $publicKey = config('nexvary.telnyx.webhook_public_key');
        if (empty($publicKey)) {
            Log::critical('Telnyx webhook rejected: webhook_public_key not configured. Configure it in nexvary.telnyx.webhook_public_key.');
            return response('Webhook not configured', 403);
        }

        // ── 2. Verify Ed25519 signature ───────────────────────────────────────
        try {
            if (!TelnyxService::verifyIncomingWebhook($request)) {
                Log::warning('Telnyx webhook rejected: invalid signature', [
                    'ip' => $request->ip(),
                ]);
                return response('Forbidden', 403);
            }
        } catch (\Throwable $e) {
            Log::error('Telnyx webhook signature verification exception', ['error' => $e->getMessage()]);
            return response('Forbidden', 403);
        }

        // ── 3. Parse event ────────────────────────────────────────────────────
        $data      = $request->json()->all();
        $eventId   = $data['data']['id'] ?? null;
        $eventType = $data['data']['event_type'] ?? '';

        if (!$eventId) {
            Log::warning('Telnyx webhook: missing event ID');
            return response('Bad Request', 400);
        }

        // ── 4. Idempotency: skip already-processed events ─────────────────────
        if (SmsMessage::where('telnyx_event_id', $eventId)->exists()) {
            return response('', 200);
        }

        // ── 5. Process by event type ──────────────────────────────────────────
        try {
            match ($eventType) {
                'message.received' => $this->handleInboundMessage($data, $eventId),
                'message.sent'     => $this->handleMessageStatus($data, 'sent'),
                'message.delivered'=> $this->handleMessageStatus($data, 'delivered'),
                'message.failed'   => $this->handleMessageStatus($data, 'failed'),
                default            => null, // unknown event types are silently ignored
            };
        } catch (\Throwable $e) {
            Log::error('Telnyx webhook processing error', [
                'event_type' => $eventType,
                'event_id'   => $eventId,
                'error'      => $e->getMessage(),
            ]);
            return response('Internal Server Error', 500);
        }

        return response('', 200);
    }

    // ── Private webhook handlers ──────────────────────────────────────────────

    private function handleInboundMessage(array $data, string $eventId): void
    {
        $payload = $data['data']['payload'] ?? [];
        $from    = $payload['from']['phone_number'] ?? null;
        $to      = $payload['to'][0]['phone_number'] ?? null;
        $text    = $payload['text'] ?? '';
        $msgId   = $payload['id'] ?? null;

        if (!$from || !$to) {
            Log::warning('Telnyx webhook: message.received missing from/to', ['payload' => array_keys($payload)]);
            return;
        }

        DB::transaction(function () use ($from, $to, $text, $msgId, $eventId) {
            $convo = SmsConversation::firstOrCreate(
                ['did_number' => $to, 'contact_number' => $from],
                ['status' => 'new'],
            );

            $convo->messages()->create([
                'body'              => $text,
                'direction'         => 'inbound',
                'status'            => 'received',
                'telnyx_message_id' => $msgId,
                'telnyx_event_id'   => $eventId,
                'sent_at'           => now(),
            ]);

            $convo->update([
                'last_message_at' => now(),
                'status'          => 'new',
            ]);
        });
    }

    private function handleMessageStatus(array $data, string $status): void
    {
        $payload = $data['data']['payload'] ?? [];
        $msgId   = $payload['id'] ?? null;

        if (!$msgId) {
            return;
        }

        $message = SmsMessage::where('telnyx_message_id', $msgId)->first();
        if (!$message) {
            return;
        }

        $update = ['status' => $status];
        if ($status === 'delivered') {
            $update['delivered_at'] = now();
        }

        $message->update($update);
    }
}
