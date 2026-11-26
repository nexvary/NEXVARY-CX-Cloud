<?php

namespace App\Http\Controllers\PBX;

use App\Data\SipProviderPresets;
use App\Http\Controllers\Controller;
use App\Models\Trunk;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\FusionPbxConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => [
                'required', 'string',
                Rule::unique('trunks', 'name')->where('tenant_id', $tenantId),
            ],
            'preset_key'        => ['nullable', 'string', 'in:' . implode(',', SipProviderPresets::keys())],
            'auth_mode'         => ['required', 'string', 'in:credential,ip_acl'],
            // SignalWire supplies space_name instead of sip_host — the host is derived below
            'space_name'        => ['nullable', 'string', 'max:63', 'regex:/^[a-z0-9-]+$/'],
            'sip_host'          => ['required_without:space_name', 'nullable', 'string', 'max:253'],
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

        // SignalWire: derive sip_host from space_name
        if (!empty($validated['space_name'])) {
            $validated['sip_host'] = $validated['space_name'] . '.sip.signalwire.com';
        }
        unset($validated['space_name']);

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
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name'              => ['required', 'string',
                Rule::unique('trunks', 'name')->where('tenant_id', $tenantId)->ignore($trunk->id),
            ],
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
        // FS gateway name matches the one written in renderGatewayXml: "nexvary-{id}"
        $this->pbx->reloadGateway("nexvary-{$trunk->id}");

        return response()->json(['ok' => true]);
    }

    public function health(Trunk $trunk): JsonResponse
    {
        $status = $this->pbx->api("sofia status gateway nexvary-{$trunk->id}");

        return response()->json(['raw' => $status]);
    }

    /**
     * Enable a gateway and register it with the SIP provider.
     *
     * Writes the config (in case it was removed), instructs FreeSWITCH to
     * start the gateway, then returns the registration status so the UI can
     * show REGED / NOREG / TRYING immediately.
     */
    public function activate(Request $request, Trunk $trunk): JsonResponse
    {
        $this->config->writeTrunkConfig($trunk);
        $this->pbx->reloadGateway("nexvary-{$trunk->id}");

        $trunk->update(['enabled' => true]);

        $raw    = $this->pbx->api("sofia status gateway nexvary-{$trunk->id}") ?: '';
        $status = $this->parseGatewayStatus($raw);

        return response()->json(['ok' => true, 'status' => $status, 'raw' => $raw]);
    }

    /**
     * Disable a gateway: kill the registration and mark the trunk disabled.
     */
    public function deactivate(Request $request, Trunk $trunk): JsonResponse
    {
        $this->pbx->api("sofia profile external killgw nexvary-{$trunk->id}");

        $trunk->update(['enabled' => false]);

        return response()->json(['ok' => true, 'status' => 'DISABLED']);
    }

    /** Extract the state token from a "sofia status gateway" response. */
    private function parseGatewayStatus(string $raw): string
    {
        if (preg_match('/\b(REGED|NOREG|TRYING|FAILED|TIMEOUT|UNREGED)\b/i', $raw, $m)) {
            return strtoupper($m[1]);
        }

        return 'UNKNOWN';
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
