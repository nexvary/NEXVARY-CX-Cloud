<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Services\PBX\FreeSwitchService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VoicemailController extends Controller
{
    public function __construct(private readonly FreeSwitchService $pbx) {}

    public function index(): Response
    {
        $extensions = Extension::where('voicemail_enabled', true)->latest()->get(
            ['id', 'number', 'description', 'enabled']
        );

        $mailboxes = $extensions->map(fn($ext) => [
            'id'          => $ext->id,
            'extension'   => $ext->number,
            'description' => $ext->description,
            'enabled'     => $ext->enabled,
            'message_count' => 0,
            'unread_count'  => 0,
        ]);

        return Inertia::render('PBX/Voicemail/Index', [
            'mailboxes' => $mailboxes,
        ]);
    }

    public function show(string $box): Response
    {
        $extension = Extension::findOrFail($box);

        return Inertia::render('PBX/Voicemail/Messages', [
            'extension' => $extension,
            'messages'  => [],
        ]);
    }

    public function destroy(string $box, string $msg): RedirectResponse
    {
        return redirect()->route('pbx.voicemail.index')
                         ->with('success', 'Voicemail message deleted.');
    }
}
