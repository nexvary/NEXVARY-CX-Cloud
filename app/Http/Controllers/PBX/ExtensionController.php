<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Services\PBX\ExtensionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExtensionController extends Controller
{
    public function __construct(
        private readonly ExtensionService $extensions,
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
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'number' => [
                'required', 'string', 'max:20',
                // Unique within the tenant, allowing two tenants to both have ext 1001
                Rule::unique('extensions', 'number')
                    ->where('tenant_id', $tenantId)
                    ->withoutTrashed(),
            ],
            'description'  => ['nullable', 'string', 'max:255'],
            'call_timeout' => ['integer', 'min:10', 'max:120'],
            'sip_password' => ['nullable', 'string', 'min:8'],
        ]);

        $this->extensions->create($validated);

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
            'description'  => ['nullable', 'string', 'max:255'],
            'call_timeout' => ['integer', 'min:10', 'max:120'],
            'sip_password' => ['nullable', 'string', 'min:8'],
        ]);

        $this->extensions->update($extension, $validated);

        return redirect()->route('pbx.extensions.index')
                         ->with('success', __('messages.extension_updated'));
    }

    public function destroy(Extension $extension): \Illuminate\Http\RedirectResponse
    {
        $this->extensions->delete($extension);

        return redirect()->route('pbx.extensions.index')
                         ->with('success', __('messages.extension_deleted'));
    }

    public function setEnabled(Request $request, Extension $extension): JsonResponse
    {
        $enabled = $request->boolean('enabled');
        $extension->update(['enabled' => $enabled]);

        // Write or remove the FreeSWITCH directory entry based on enabled state
        if ($enabled) {
            // Re-derive the plaintext password is not possible after hashing; rewrite
            // only if a new password is provided, otherwise leave the existing config file
            if ($request->filled('sip_password')) {
                $this->extensions->update($extension, ['sip_password' => $request->input('sip_password')]);
            }
        } else {
            // Remove the config file — FreeSWITCH will reject SIP REGISTER with 403
            $this->extensions->disableInFreeSWITCH($extension);
        }

        return response()->json(['ok' => true, 'enabled' => $enabled]);
    }

    public function setDnd(Request $request, Extension $extension): JsonResponse
    {
        $this->extensions->setDnd($extension, $request->boolean('enabled'));
        return response()->json(['ok' => true]);
    }

    public function setForward(Request $request, Extension $extension): JsonResponse
    {
        $request->validate([
            'number'  => ['nullable', 'string'],
            'enabled' => ['boolean'],
        ]);

        $destination = $request->boolean('enabled') ? $request->input('number') : null;
        $this->extensions->setForward($extension, $destination);

        return response()->json(['ok' => true]);
    }

    public function status(Extension $extension): JsonResponse
    {
        return response()->json(
            $this->extensions->getRegistrationStatus($extension)
        );
    }
}
