<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\NumberTranslation;
use App\Models\Tenant;
use App\Services\PBX\NumberTranslationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NumberTranslationController extends Controller
{
    public function __construct(
        private readonly NumberTranslationService $translations,
    ) {}

    public function index(): Response
    {
        return Inertia::render('PBX/NumberTranslations/Index', [
            'translations' => NumberTranslation::orderBy('priority')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('PBX/NumberTranslations/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRule($request);
        $validated['tenant_id'] = $request->user()->tenant_id;
        $domain = $this->tenantDomain($request);

        $this->translations->create($validated, $domain);

        return redirect()->route('pbx.number-translations.index')
            ->with('success', __('messages.number_translation_created'));
    }

    public function edit(NumberTranslation $numberTranslation): Response
    {
        return Inertia::render('PBX/NumberTranslations/Form', [
            'translation' => $numberTranslation,
        ]);
    }

    public function update(Request $request, NumberTranslation $numberTranslation): RedirectResponse
    {
        $validated = $this->validateRule($request);
        $domain = $this->tenantDomain($request);

        $this->translations->update($numberTranslation, $validated, $domain);

        return redirect()->route('pbx.number-translations.index')
            ->with('messages.number_translation_updated');
    }

    public function destroy(Request $request, NumberTranslation $numberTranslation): RedirectResponse
    {
        $domain = $this->tenantDomain($request);
        $this->translations->delete($numberTranslation, $domain);

        return redirect()->route('pbx.number-translations.index')
            ->with('success', __('messages.number_translation_deleted'));
    }

    private function tenantDomain(Request $request): string
    {
        $tenantId = $request->user()->tenant_id;
        abort_if(empty($tenantId), 403, 'Number translations require a tenant context.');
        return Tenant::findOrFail($tenantId)->sipDomain();
    }

    private function validateRule(Request $request): array
    {
        return $request->validate([
            'name'               => ['required', 'string', 'max:100'],
            'direction'          => ['required', 'string', 'in:inbound,outbound,both'],
            'pattern'            => ['required', 'string', 'max:200',
                function ($attr, $val, $fail) {
                    if (@preg_match('/^(?:' . $val . ')$/', '') === false) {
                        $fail('The pattern is not a valid regular expression.');
                    }
                },
            ],
            'replace'            => ['nullable', 'string', 'max:200'],
            'strip_prefix'       => ['nullable', 'string', 'max:20'],
            'add_prefix'         => ['nullable', 'string', 'max:20'],
            'privacy_enabled'    => ['boolean'],
            'caller_id_override' => ['nullable', 'string', 'max:50'],
            'priority'           => ['integer', 'min:1', 'max:999'],
            'enabled'            => ['boolean'],
        ]);
    }
}
