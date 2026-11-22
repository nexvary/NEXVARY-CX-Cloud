<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\FusionPbxConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ExtensionController extends Controller
{
    public function __construct(
        private readonly FreeSwitchService $pbx,
        private readonly FusionPbxConfigService $config,
    ) {}

    public function index(): Response
    {
        return Inertia::render('PBX/Extensions/Index', [
            'extensions' => Extension::latest()->paginate(25),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('PBX/Extensions/Form');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'number'   => ['required', 'string', 'unique:extensions,number'],
            'name'     => ['required', 'string', 'max:255'],
            'timeout'  => ['integer', 'min:10', 'max:120'],
            'context'  => ['string', 'max:64'],
        ]);

        $extension = Extension::create(array_merge($validated, [
            'sip_password'  => Str::random(20),
            'voicemail_pin' => random_int(1000, 9999),
        ]));

        $this->config->writeExtension($extension);
        $this->pbx->reloadDialplan();

        return redirect()->route('pbx.extensions.index')
                         ->with('success', __('messages.extension_created'));
    }

    public function show(Extension $extension): Response
    {
        return Inertia::render('PBX/Extensions/Show', compact('extension'));
    }

    public function edit(Extension $extension): Response
    {
        return Inertia::render('PBX/Extensions/Form', compact('extension'));
    }

    public function update(Request $request, Extension $extension): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'timeout' => ['integer', 'min:10', 'max:120'],
            'context' => ['string', 'max:64'],
        ]);

        $extension->update($validated);
        $this->config->writeExtension($extension->fresh());
        $this->pbx->reloadDialplan();

        return redirect()->route('pbx.extensions.index')
                         ->with('success', __('messages.extension_updated'));
    }

    public function destroy(Extension $extension): \Illuminate\Http\RedirectResponse
    {
        $this->config->deleteExtension($extension);
        $extension->delete();
        $this->pbx->reloadDialplan();

        return redirect()->route('pbx.extensions.index')
                         ->with('success', __('messages.extension_deleted'));
    }

    public function setDnd(Request $request, Extension $extension): JsonResponse
    {
        $extension->update(['dnd_enabled' => $request->boolean('enabled')]);
        $this->config->writeExtension($extension->fresh());

        return response()->json(['ok' => true]);
    }

    public function setForward(Request $request, Extension $extension): JsonResponse
    {
        $request->validate(['number' => ['nullable', 'string'], 'enabled' => ['boolean']]);
        $extension->update([
            'call_forward_enabled' => $request->boolean('enabled'),
            'call_forward_number'  => $request->input('number'),
        ]);
        $this->config->writeExtension($extension->fresh());

        return response()->json(['ok' => true]);
    }

    public function status(Extension $extension): JsonResponse
    {
        $sofia = $this->pbx->api("sofia status profile internal reg {$extension->number}");

        return response()->json(['raw' => $sofia]);
    }
}
