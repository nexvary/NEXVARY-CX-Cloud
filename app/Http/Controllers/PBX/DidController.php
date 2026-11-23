<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\Did;
use App\Models\Tenant;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\FusionPbxConfigService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DidController extends Controller
{
    public function __construct(
        private readonly FusionPbxConfigService $config,
        private readonly FreeSwitchService $pbx,
    ) {}

    public function index(): Response
    {
        return Inertia::render('PBX/DIDs/Index', [
            'dids' => Did::with('trunk')->latest()->paginate(25),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('PBX/DIDs/Form');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'number'           => ['required', 'string', 'unique:dids,number'],
            'description'      => ['nullable', 'string'],
            'trunk_id'         => ['nullable', 'uuid'],
            'destination_type' => ['nullable', 'string', 'in:extension,ivr,queue,ring_group,voicemail'],
            'destination_id'   => ['nullable', 'uuid'],
            'enabled'          => ['boolean'],
        ]);

        $did = Did::create($validated);
        $this->applyDialplan($did);

        return redirect()->route('pbx.dids.index')
                         ->with('success', __('messages.did_created'));
    }

    public function show(Did $did): Response
    {
        return Inertia::render('PBX/DIDs/Form', ['did' => $did->load('trunk'), 'readonly' => true]);
    }

    public function edit(Did $did): Response
    {
        return Inertia::render('PBX/DIDs/Form', ['did' => $did->load('trunk')]);
    }

    public function update(Request $request, Did $did): \Illuminate\Http\RedirectResponse
    {
        $did->update($request->validate([
            'number'           => ['required', 'string'],
            'description'      => ['nullable', 'string'],
            'trunk_id'         => ['nullable', 'uuid'],
            'destination_type' => ['nullable', 'string', 'in:extension,ivr,queue,ring_group,voicemail'],
            'destination_id'   => ['nullable', 'uuid'],
            'enabled'          => ['boolean'],
        ]));

        $this->applyDialplan($did->fresh());

        return redirect()->route('pbx.dids.index')
                         ->with('success', __('messages.did_updated'));
    }

    public function destroy(Did $did): \Illuminate\Http\RedirectResponse
    {
        $this->config->deleteInboundRoute(['id' => $did->id]);
        $this->pbx->reloadDialplan();
        $did->delete();

        return redirect()->route('pbx.dids.index')
                         ->with('success', __('messages.did_deleted'));
    }

    private function applyDialplan(Did $did): void
    {
        if (!$did->enabled || !$did->destination_type) {
            // No active route to write; remove any stale config
            $this->config->deleteInboundRoute(['id' => $did->id]);
        } else {
            // Resolve the tenant's SIP domain so the inbound transfer lands in
            // the correct per-tenant dialplan context (not the global "default").
            $domain = 'default';
            if ($did->tenant_id) {
                $tenant = Tenant::find($did->tenant_id);
                if ($tenant) {
                    $domain = $tenant->sipDomain();
                }
            }

            $this->config->writeInboundRoute([
                'id'          => $did->id,
                'did'         => preg_replace('/\D/', '', $did->number),
                'destination' => $did->destination_id ?? $did->number,
                'domain'      => $domain,
            ]);
        }
        $this->pbx->reloadDialplan();
    }
}
