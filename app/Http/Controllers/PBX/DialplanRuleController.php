<?php
namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\DialplanRule;
use App\Models\Tenant;
use App\Services\PBX\ConfigValidationService;
use App\Services\PBX\DialplanRuleService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DialplanRuleController extends Controller
{
    public function __construct(
        private readonly DialplanRuleService $service,
    ) {}

    public function index(): \Inertia\Response
    {
        return Inertia::render('PBX/DialplanRules/Index', [
            'rules' => DialplanRule::orderBy('priority')->orderBy('name')->get(),
        ]);
    }

    public function create(): \Inertia\Response
    {
        return Inertia::render('PBX/DialplanRules/Form');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $this->validateRule($request);
        $check     = ConfigValidationService::validateDialplanRule($validated);
        if (!$check['valid']) {
            return back()->withErrors(['validation' => implode(' ', $check['errors'])]);
        }
        $validated['tenant_id'] = $request->user()->tenant_id;
        $domain = $this->tenantDomain($request);
        $this->service->create($validated, $domain);
        return redirect()->route('pbx.dialplan-rules.index')
            ->with('success', __('messages.dialplan_rule_created'));
    }

    public function edit(DialplanRule $dialplanRule): \Inertia\Response
    {
        return Inertia::render('PBX/DialplanRules/Form', ['rule' => $dialplanRule]);
    }

    public function update(Request $request, DialplanRule $dialplanRule): \Illuminate\Http\RedirectResponse
    {
        $validated = $this->validateRule($request);
        $check     = ConfigValidationService::validateDialplanRule($validated);
        if (!$check['valid']) {
            return back()->withErrors(['validation' => implode(' ', $check['errors'])]);
        }
        $domain = $this->tenantDomain($request);
        $this->service->update($dialplanRule, $validated, $domain);
        return redirect()->route('pbx.dialplan-rules.index')
            ->with('success', __('messages.dialplan_rule_updated'));
    }

    public function destroy(Request $request, DialplanRule $dialplanRule): \Illuminate\Http\RedirectResponse
    {
        $domain = $this->tenantDomain($request);
        $this->service->delete($dialplanRule, $domain);
        return redirect()->route('pbx.dialplan-rules.index')
            ->with('success', __('messages.dialplan_rule_deleted'));
    }

    private function tenantDomain(Request $request): string
    {
        $tenantId = $request->user()->tenant_id;
        abort_if(empty($tenantId), 403, 'Dialplan rules require a tenant context.');
        return Tenant::findOrFail($tenantId)->sipDomain();
    }

    private function validateRule(Request $request): array
    {
        return $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'context'    => ['nullable', 'string', 'max:100'],
            'priority'   => ['integer', 'min:1', 'max:999'],
            'continue'   => ['boolean'],
            'conditions' => ['required', 'array', 'min:1'],
            'conditions.*.field'      => ['required', 'string', 'max:100'],
            'conditions.*.expression' => ['required', 'string', 'max:500'],
            'conditions.*.break'      => ['nullable', 'string', 'in:on-false,on-true,never,always'],
            'actions'    => ['required', 'array', 'min:1'],
            'actions.*.application'   => ['required', 'string', 'max:100'],
            'actions.*.data'          => ['nullable', 'string', 'max:500'],
            'actions.*.anti_action'   => ['boolean'],
            'enabled'    => ['boolean'],
        ]);
    }
}
