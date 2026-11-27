<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\SipProfile;
use App\Services\PBX\SipProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SipProfileController extends Controller
{
    public function __construct(
        private readonly SipProfileService $profiles,
    ) {}

    public function index(): Response
    {
        return Inertia::render('PBX/SipProfiles/Index', [
            'profiles' => SipProfile::orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('PBX/SipProfiles/Form', [
            'defaultCodecs' => SipProfile::defaultCodecs(),
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $this->validateProfile($request);
        $validated['tenant_id'] = $request->user()->tenant_id;
        $validated['codecs'] = $validated['codecs'] ?? SipProfile::defaultCodecs();

        SipProfile::create($validated);

        return redirect()->route('pbx.sip_profiles.index')
            ->with('success', __('messages.sip_profile_created'));
    }

    public function edit(SipProfile $sipProfile): Response
    {
        return Inertia::render('PBX/SipProfiles/Form', [
            'profile'       => $sipProfile,
            'defaultCodecs' => SipProfile::defaultCodecs(),
        ]);
    }

    public function update(Request $request, SipProfile $sipProfile): \Illuminate\Http\RedirectResponse
    {
        $validated = $this->validateProfile($request);
        $sipProfile->update($validated);

        // Re-write the config file if the profile is active
        if ($sipProfile->enabled) {
            $this->profiles->write($sipProfile->fresh());
        }

        return redirect()->route('pbx.sip_profiles.index')
            ->with('success', __('messages.sip_profile_updated'));
    }

    public function destroy(SipProfile $sipProfile): \Illuminate\Http\RedirectResponse
    {
        $this->profiles->deactivate($sipProfile);
        $sipProfile->delete();

        return redirect()->route('pbx.sip_profiles.index')
            ->with('success', __('messages.sip_profile_deleted'));
    }

    public function activate(SipProfile $sipProfile): JsonResponse
    {
        $result = $this->profiles->activate($sipProfile);
        return response()->json($result, $result['activated'] ? 200 : 503);
    }

    public function deactivate(SipProfile $sipProfile): JsonResponse
    {
        $result = $this->profiles->deactivate($sipProfile);
        return response()->json($result);
    }

    public function status(SipProfile $sipProfile): JsonResponse
    {
        return response()->json($this->profiles->getStatus($sipProfile));
    }

    private function validateProfile(Request $request): array
    {
        return $request->validate([
            'name'                     => ['required', 'string', 'max:100'],
            'type'                     => ['required', 'string', 'in:internal,external'],
            'sip_port'                 => ['nullable', 'integer', 'min:1024', 'max:65535'],
            'sip_ip'                   => ['nullable', 'string', 'max:100'],
            'rtp_ip'                   => ['nullable', 'string', 'max:100'],
            'ext_sip_ip'               => ['nullable', 'string', 'max:100'],
            'ext_rtp_ip'               => ['nullable', 'string', 'max:100'],
            'nat_enabled'              => ['boolean'],
            'rtp_timeout'              => ['integer', 'min:30', 'max:7200'],
            'rtp_hold_timeout'         => ['integer', 'min:60', 'max:86400'],
            'codecs'                   => ['nullable', 'array'],
            'codecs.*'                 => ['string', 'max:20'],
            'tls_enabled'              => ['boolean'],
            'tls_port'                 => ['nullable', 'integer', 'min:1024', 'max:65535'],
            'srtp_mode'                => ['string', 'in:disabled,optional,mandatory'],
            'apply_inbound_acl'        => ['nullable', 'string', 'max:100'],
            'aggressive_nat_detection' => ['boolean'],
            'settings'                 => ['nullable', 'array'],
        ]);
    }
}
