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
     * Three-state model: configured (config file on disk) → enabled (DB flag) → registered (ESL state).
     * Rollback: if FreeSWITCH is unreachable, the config file is removed and enabled stays false.
     */
    public function activate(Request $request, Trunk $trunk): JsonResponse
    {
        // Defense-in-depth: TenantScope already enforces isolation via model binding,
        // but an explicit check ensures no privilege escalation if scope is bypassed.
        if ($trunk->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        // Write gateway XML to disk (configured state = true)
        $this->config->writeTrunkConfig($trunk);

        // Instruct FreeSWITCH to load the gateway; check ESL connectivity
        $reloaded = $this->pbx->reloadGateway("nexvary-{$trunk->id}");

        if (!$reloaded) {
            // Rollback: remove the config file we just wrote so the system stays consistent
            $this->config->deleteTrunkConfig($trunk);

            return response()->json([
                'ok'         => false,
                'configured' => false,
                'enabled'    => false,
                'status'     => 'ESL_ERROR',
                'message'    => 'FreeSWITCH ESL is unreachable. Configuration rolled back.',
            ], 503);
        }

        // ESL accepted the command — mark the trunk enabled in the database
        $trunk->update(['enabled' => true]);

        // Query live registration state; the gateway may still be TRYING at this point
        $raw    = $this->pbx->api("sofia status gateway nexvary-{$trunk->id}") ?: '';
        $status = $this->parseGatewayStatus($raw);

        return response()->json([
            'ok'         => true,
            'configured' => true,
            'enabled'    => true,
            'status'     => $status,
            'raw'        => $raw,
        ]);
    }

    /**
     * Disable a gateway: kill the registration, remove the config file, mark disabled.
     *
     * Proceeds even if ESL is unreachable: the config file is removed so the gateway
     * cannot re-register on the next FreeSWITCH restart.
     */
    public function deactivate(Request $request, Trunk $trunk): JsonResponse
    {
        if ($trunk->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        // Best-effort ESL kill (non-fatal if FreeSWITCH is unreachable)
        $killed = $this->pbx->api("sofia profile external killgw nexvary-{$trunk->id}");

        // Remove config file so the gateway cannot re-register on FreeSWITCH restart
        $this->config->deleteTrunkConfig($trunk);

        $trunk->update(['enabled' => false]);

        return response()->json([
            'ok'         => true,
            'configured' => false,
            'enabled'    => false,
            'status'     => 'DISABLED',
            'esl_killgw' => $killed !== false,
        ]);
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
