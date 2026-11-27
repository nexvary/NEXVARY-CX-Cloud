<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\InboundRoute;
use App\Models\OutboundRoute;
use App\Models\Tenant;
use App\Models\Trunk;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\FusionPbxConfigService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles both inbound and outbound routes.
 * Route type (inbound|outbound) is determined by which named route was matched.
 *
 * Inbound routes:  dialplan/public context — match DID, transfer into tenant domain context.
 * Outbound routes: dialplan/{domain} context — match dialed number, bridge via gateway.
 */
class RouteController extends Controller
{
    public function __construct(
        private readonly FusionPbxConfigService $config,
        private readonly FreeSwitchService $pbx,
    ) {}

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function routeType(Request $request): string
    {
        return str_contains($request->route()->getName() ?? '', 'inbound') ? 'inbound' : 'outbound';
    }

    private function tenantDomain(Request $request): string
    {
        $tenantId = $request->user()->tenant_id;
        // Refuse to fall back to the shared 'default' SIP context — that would
        // silently mix a tenant's calls with the system default context.
        abort_if(empty($tenantId), 403, 'PBX routes require a tenant context.');
        $tenant = Tenant::findOrFail($tenantId);
        return $tenant->sipDomain();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $type = $this->routeType($request);

        if ($type === 'inbound') {
            $routes = InboundRoute::with('trunk')->orderBy('priority')->get();
        } else {
            $routes = OutboundRoute::with('trunk')->orderBy('priority')->get();
        }

        return Inertia::render('PBX/Routes/Index', [
            'type'   => $type,
            'routes' => $routes,
            'trunks' => Trunk::orderBy('name')->get(['id', 'name', 'sip_host', 'enabled']),
        ]);
    }

    // ── Create form ───────────────────────────────────────────────────────────

    public function create(Request $request): Response
    {
        $type = $this->routeType($request);

        return Inertia::render('PBX/Routes/Form', [
            'type'   => $type,
            'trunks' => Trunk::orderBy('name')->get(['id', 'name', 'sip_host', 'enabled']),
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $type   = $this->routeType($request);
        $domain = $this->tenantDomain($request);

        if ($type === 'inbound') {
            return $this->storeInbound($request, $domain);
        }
        return $this->storeOutbound($request, $domain);
    }

    private function storeInbound(Request $request, string $domain): \Illuminate\Http\RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'did'              => ['nullable', 'string', 'max:30',
                Rule::unique('inbound_routes', 'did')->where('tenant_id', $tenantId),
            ],
            'name'             => ['required', 'string', 'max:100'],
            'destination_type' => ['required', 'string', 'in:extension,ivr,queue,ring_group,voicemail'],
            'destination'      => ['required', 'string', 'max:100'],
            // Trunk must belong to the same tenant — prevents using another tenant's gateway
            'trunk_id'         => ['nullable', 'uuid',
                Rule::exists('trunks', 'id')->where('tenant_id', $tenantId),
            ],
            'priority'         => ['integer', 'min:1', 'max:999'],
            'enabled'          => ['boolean'],
        ]);

        $route = InboundRoute::create($validated);

        $this->config->writeInboundRoute([
            'id'          => $route->id,
            'did'         => preg_replace('/\D/', '', $route->did ?? ''),
            'destination' => $route->destination,
            'domain'      => $domain,
        ]);
        $this->pbx->reloadDialplan();

        return redirect()->route('pbx.inbound_routes.index')
                         ->with('success', __('messages.route_created'));
    }

    private function storeOutbound(Request $request, string $domain): \Illuminate\Http\RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:100',
                Rule::unique('outbound_routes', 'name')->where('tenant_id', $tenantId),
            ],
            // Validate regex is syntactically safe; reject patterns with no meaning
            'dial_pattern' => ['required', 'string', 'max:200',
                function ($attr, $val, $fail) {
                    if (@preg_match('/^(?:' . $val . ')$/', '') === false) {
                        $fail('The dial pattern is not a valid regular expression.');
                    }
                },
            ],
            // Trunk must belong to the same tenant — prevents using another tenant's gateway
            'trunk_id'     => ['nullable', 'uuid',
                Rule::exists('trunks', 'id')->where('tenant_id', $tenantId),
            ],
            'trunk_prefix' => ['nullable', 'string', 'max:20'],
            'priority'     => ['integer', 'min:1', 'max:999'],
            'enabled'      => ['boolean'],
        ]);

        $route = OutboundRoute::create($validated);

        $gatewayName = $route->trunk_id ? "nexvary-{$route->trunk_id}" : '';
        $this->config->writeOutboundRoute([
            'id'      => $route->id,
            'pattern' => $route->dial_pattern,
            'gateway' => $gatewayName,
            'prefix'  => $route->trunk_prefix ?? '',
            'domain'  => $domain,
        ]);
        $this->pbx->reloadDialplan();

        return redirect()->route('pbx.outbound_routes.index')
                         ->with('success', __('messages.route_created'));
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(Request $request, string $id): Response
    {
        $route = $this->findRoute($request, $id);

        return Inertia::render('PBX/Routes/Form', [
            'type'     => $this->routeType($request),
            'route'    => $route->load('trunk'),
            'readonly' => true,
            'trunks'   => Trunk::orderBy('name')->get(['id', 'name', 'sip_host', 'enabled']),
        ]);
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function edit(Request $request, string $id): Response
    {
        $route = $this->findRoute($request, $id);

        return Inertia::render('PBX/Routes/Form', [
            'type'   => $this->routeType($request),
            'route'  => $route->load('trunk'),
            'trunks' => Trunk::orderBy('name')->get(['id', 'name', 'sip_host', 'enabled']),
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, string $id): \Illuminate\Http\RedirectResponse
    {
        $type   = $this->routeType($request);
        $domain = $this->tenantDomain($request);
        $route  = $this->findRoute($request, $id);

        if ($type === 'inbound') {
            return $this->updateInbound($request, $route, $domain);
        }
        return $this->updateOutbound($request, $route, $domain);
    }

    private function updateInbound(Request $request, InboundRoute $route, string $domain): \Illuminate\Http\RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'did'              => ['nullable', 'string', 'max:30',
                Rule::unique('inbound_routes', 'did')
                    ->where('tenant_id', $tenantId)
                    ->ignore($route->id),
            ],
            'name'             => ['required', 'string', 'max:100'],
            'destination_type' => ['required', 'string', 'in:extension,ivr,queue,ring_group,voicemail'],
            'destination'      => ['required', 'string', 'max:100'],
            'trunk_id'         => ['nullable', 'uuid',
                Rule::exists('trunks', 'id')->where('tenant_id', $tenantId),
            ],
            'priority'         => ['integer', 'min:1', 'max:999'],
            'enabled'          => ['boolean'],
        ]);

        $route->update($validated);

        $this->config->writeInboundRoute([
            'id'          => $route->id,
            'did'         => preg_replace('/\D/', '', $route->did ?? ''),
            'destination' => $route->destination,
            'domain'      => $domain,
        ]);
        $this->pbx->reloadDialplan();

        return redirect()->route('pbx.inbound_routes.index')
                         ->with('success', __('messages.route_updated'));
    }

    private function updateOutbound(Request $request, OutboundRoute $route, string $domain): \Illuminate\Http\RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:100',
                Rule::unique('outbound_routes', 'name')
                    ->where('tenant_id', $tenantId)
                    ->ignore($route->id),
            ],
            'dial_pattern' => ['required', 'string', 'max:200',
                function ($attr, $val, $fail) {
                    if (@preg_match('/^(?:' . $val . ')$/', '') === false) {
                        $fail('The dial pattern is not a valid regular expression.');
                    }
                },
            ],
            'trunk_id'     => ['nullable', 'uuid',
                Rule::exists('trunks', 'id')->where('tenant_id', $tenantId),
            ],
            'trunk_prefix' => ['nullable', 'string', 'max:20'],
            'priority'     => ['integer', 'min:1', 'max:999'],
            'enabled'      => ['boolean'],
        ]);

        $route->update($validated);

        $gatewayName = $route->trunk_id ? "nexvary-{$route->trunk_id}" : '';
        $this->config->writeOutboundRoute([
            'id'      => $route->id,
            'pattern' => $route->dial_pattern,
            'gateway' => $gatewayName,
            'prefix'  => $route->trunk_prefix ?? '',
            'domain'  => $domain,
        ]);
        $this->pbx->reloadDialplan();

        return redirect()->route('pbx.outbound_routes.index')
                         ->with('success', __('messages.route_updated'));
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(Request $request, string $id): \Illuminate\Http\RedirectResponse
    {
        $type   = $this->routeType($request);
        $domain = $this->tenantDomain($request);
        $route  = $this->findRoute($request, $id);

        if ($type === 'inbound') {
            $this->config->deleteInboundRoute(['id' => $route->id]);
            $redirectRoute = 'pbx.inbound_routes.index';
        } else {
            $this->config->deleteOutboundRoute([
                'id'     => $route->id,
                'domain' => $domain,
            ]);
            $redirectRoute = 'pbx.outbound_routes.index';
        }

        $route->delete();
        $this->pbx->reloadDialplan();

        return redirect()->route($redirectRoute)
                         ->with('success', __('messages.route_deleted'));
    }

    // ── Route model binding ───────────────────────────────────────────────────

    private function findRoute(Request $request, string $id): InboundRoute|OutboundRoute
    {
        $type = $this->routeType($request);

        // TenantScope is applied via BelongsToTenant — cross-tenant access returns 404.
        if ($type === 'inbound') {
            return InboundRoute::findOrFail($id);
        }
        return OutboundRoute::findOrFail($id);
    }
}
