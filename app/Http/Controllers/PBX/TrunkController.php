<?php

namespace App\Http\Controllers\PBX;

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
        return Inertia::render('PBX/Trunks/Form');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'unique:trunks,name'],
            'provider'   => ['required', 'string'],
            'username'   => ['required', 'string'],
            'sip_server' => ['required', 'string'],
            'sip_port'   => ['integer', 'min:1', 'max:65535'],
            'register'   => ['boolean'],
        ]);

        // Password never stored in request validated array — stored encrypted separately
        $trunk = Trunk::create($validated);
        $this->config->writeTrunk($trunk, $request->input('sip_password', ''));
        $this->pbx->reloadSipProfiles();

        return redirect()->route('pbx.trunks.index')
                         ->with('success', __('messages.trunk_created'));
    }

    public function show(Trunk $trunk): Response
    {
        return Inertia::render('PBX/Trunks/Show', compact('trunk'));
    }

    public function edit(Trunk $trunk): Response
    {
        return Inertia::render('PBX/Trunks/Form', compact('trunk'));
    }

    public function update(Request $request, Trunk $trunk): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string'],
            'sip_server' => ['required', 'string'],
            'sip_port'   => ['integer', 'min:1', 'max:65535'],
            'register'   => ['boolean'],
        ]);

        $trunk->update($validated);

        if ($request->filled('sip_password')) {
            $this->config->writeTrunk($trunk->fresh(), $request->input('sip_password'));
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
}
