<?php

namespace App\Http\Controllers\SMS;

use App\Http\Controllers\Controller;
use App\Models\SmsConversation;
use App\Models\SmsMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class SmsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SMS/Index', [
            'conversations' => SmsConversation::with('messages')
                ->latest('last_message_at')
                ->paginate(30),
        ]);
    }

    public function show(SmsConversation $conversation): Response
    {
        $conversation->load('messages');
        $conversation->update(['status' => 'read']);

        return Inertia::render('SMS/Index', [
            'conversations'       => SmsConversation::latest('last_message_at')->paginate(30),
            'active_conversation' => $conversation,
        ]);
    }

    public function send(Request $request, SmsConversation $conversation): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:1600']]);

        $conversation->messages()->create([
            'body'      => $request->input('body'),
            'direction' => 'outbound',
            'status'    => 'sent',
        ]);
        $conversation->update(['last_message_at' => now()]);

        return redirect()->route('sms.show', $conversation);
    }

    public function compose(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'to'   => ['required', 'string'],
            'body' => ['required', 'string', 'max:1600'],
        ]);

        $convo = SmsConversation::firstOrCreate(
            ['contact_number' => $request->input('to')],
            ['status' => 'open'],
        );
        $convo->messages()->create([
            'body'      => $request->input('body'),
            'direction' => 'outbound',
            'status'    => 'sent',
        ]);
        $convo->update(['last_message_at' => now()]);

        return redirect()->route('sms.show', $convo);
    }

    public function webhook(Request $request): HttpResponse
    {
        // Telnyx signature verification would go here in production
        $data = $request->json()->all();

        if (($data['data']['event_type'] ?? '') === 'message.received') {
            $payload = $data['data']['payload'] ?? [];
            $from    = $payload['from']['phone_number'] ?? null;
            $text    = $payload['text'] ?? '';

            if ($from) {
                $convo = SmsConversation::firstOrCreate(
                    ['contact_number' => $from],
                    ['status' => 'new'],
                );
                $convo->messages()->create([
                    'body'      => $text,
                    'direction' => 'inbound',
                    'status'    => 'received',
                ]);
                $convo->update(['last_message_at' => now(), 'status' => 'new']);
            }
        }

        return response('', 200);
    }
}
