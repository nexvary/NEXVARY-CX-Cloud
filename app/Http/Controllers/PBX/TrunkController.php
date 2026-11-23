<?php

namespace App\Http\Controllers\PBX;

use App\Data\SipProviderPresets;
use App\Http\Controllers\Controller;
use App\Models\Trunk;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\FusionPbxConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrunkController extends Controller
{
    public function __construct(
        private readonly FreeSwitchService $pbx,
        private readonly FusionPbxConfigService $config,
    ) {}

    public function index(): Response
    {
        return Inertia::render('PBX/Trunks/Index', [
            'trunks' => Trunk::latest()->paginate(25),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('PBX/Trunks/Form', [
            'presets' => SipProviderPresets::all(),
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'unique:trunks,name'],
            'preset_key'        => ['nullable', 'string', 'in:' . implode(',', SipProviderPresets::keys())],
            'auth_mode'         => ['required', 'string', 'in:credential,ip_acl'],
            'sip_host'          => ['required', 'string', 'max:253'],
            'sip_port'          => ['integer', 'min:1', 'max:65535'],
            'transport'         => ['string', 'in:udp,tcp,tls'],
            'proxy'             => ['nullable', 'string', 'max:253'],
            'username'          => ['nullable', 'string', 'max:200', 'required_if:auth_mode,credential'],
            'realm'             => ['nullable', 'string', 'max:253'],
            'caller_id_name'    => ['nullable', 'string', 'max:60'],
            'caller_id_number'  => ['nullable', 'string', 'max:30'],
            'caller_id_in_from' => ['boolean'],
            'register'          => ['boolean'],
            'preset_version'    => ['nullable', 'string', 'max:20'],
        ]);

        // New gateways are saved disabled until explicitly activated (security default)
        $validated['enabled'] = false;

        // Credentials are written to the FS config file, never stored in DB plaintext
        $trunk = Trunk::create($validated);
        $this->config->writeTrunkConfig($trunk, $request->input('sip_password', ''));
        $this->pbx->reloadSipProfiles();

        return redirect()->route('pbx.trunks.index')
                         ->with('success', __('messages.trunk_created'));
    }

    public function show(Trunk $trunk): Response
    {
        return Inertia::render('PBX/Trunks/Show', [
            'trunk'   => $trunk,
            'presets' => SipProviderPresets::all(),
        ]);
    }

    public function edit(Trunk $trunk): Response
    {
        return Inertia::render('PBX/Trunks/Form', [
            'trunk'   => $trunk,
            'presets' => SipProviderPresets::all(),
        ]);
    }

    public function update(Request $request, Trunk $trunk): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string'],
            'auth_mode'         => ['required', 'string', 'in:credential,ip_acl'],
            'sip_host'          => ['required', 'string', 'max:253'],
            'sip_port'          => ['integer', 'min:1', 'max:65535'],
            'transport'         => ['string', 'in:udp,tcp,tls'],
            'proxy'             => ['nullable', 'string', 'max:253'],
            'username'          => ['nullable', 'string', 'max:200', 'required_if:auth_mode,credential'],
            'realm'             => ['nullable', 'string', 'max:253'],
            'caller_id_name'    => ['nullable', 'string', 'max:60'],
            'caller_id_number'  => ['nullable', 'string', 'max:30'],
            'caller_id_in_from' => ['boolean'],
            'register'          => ['boolean'],
            'enabled'           => ['boolean'],
        ]);

        $trunk->update($validated);

        if ($request->filled('sip_password')) {
            $this->config->writeTrunkConfig($trunk->fresh(), $request->input('sip_password'));
        }

        $this->pbx->reloadSipProfiles();

        return redirect()->route('pbx.trunks.index')
                         ->with('success', __('messages.trunk_updated'));
    }

    public function destroy(Trunk $trunk): \Illuminate\Http\RedirectResponse
    {
        $this->config->deleteTrunk($trunk);
        $trunk->delete();
        $this->pbx->reloadSipProfiles();

        return redirect()->route('pbx.trunks.index')
                         ->with('success', __('messages.trunk_deleted'));
    }

    public function reload(Trunk $trunk): JsonResponse
    {
        $this->pbx->reloadGateway($trunk->name);

        return response()->json(['ok' => true]);
    }

    public function health(Trunk $trunk): JsonResponse
    {
        $status = $this->pbx->api("sofia status gateway nexvary-trunk-{$trunk->id}");

        return response()->json(['raw' => $status]);
    }

    /**
     * Return all built-in provider presets for the frontend picker.
     * This endpoint requires no specific provider knowledge on the client side.
     */
    public function presets(): JsonResponse
    {
        return response()->json(SipProviderPresets::all());
    }
}
