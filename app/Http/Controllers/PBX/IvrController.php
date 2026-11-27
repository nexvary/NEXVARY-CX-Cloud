<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\IvrMenu;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\IvrProvisioningService;
use App\Services\PBX\FusionPbxConfigService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IvrController extends Controller
{
    public function __construct(
        private readonly FreeSwitchService $pbx,
        private readonly FusionPbxConfigService $config,
        private readonly IvrProvisioningService $provisioning,
    ) {}

    public function index(): Response
    {
        return Inertia::render('PBX/IVR/Index', [
            'menus' => IvrMenu::latest()->paginate(25),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('PBX/IVR/Form');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255', 'unique:ivr_menus,name'],
            'greeting_tts'  => ['nullable', 'string'],
            'greeting_file' => ['nullable', 'string'],
            'timeout'       => ['integer', 'min:1', 'max:60'],
            'max_failures'  => ['integer', 'min:1', 'max:10'],
        ]);

        $menu = IvrMenu::create($validated);
        $this->provisioning->provision($menu);

        return redirect()->route('pbx.ivr.index')
                         ->with('success', __('messages.ivr_created'));
    }

    public function show(IvrMenu $ivr): Response
    {
        return Inertia::render('PBX/IVR/Form', ['menu' => $ivr, 'readonly' => true]);
    }

    public function edit(IvrMenu $ivr): Response
    {
        return Inertia::render('PBX/IVR/Form', ['menu' => $ivr]);
    }

    public function update(Request $request, IvrMenu $ivr): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'greeting_tts'  => ['nullable', 'string'],
            'greeting_file' => ['nullable', 'string'],
            'timeout'       => ['integer', 'min:1', 'max:60'],
            'max_failures'  => ['integer', 'min:1', 'max:10'],
            'enabled'       => ['boolean'],
        ]);

        $ivr->update($validated);
        $this->provisioning->provision($ivr->fresh());

        return redirect()->route('pbx.ivr.index')
                         ->with('success', __('messages.ivr_updated'));
    }

    public function destroy(IvrMenu $ivr): \Illuminate\Http\RedirectResponse
    {
        $this->provisioning->deprovision($ivr);
        $ivr->delete();

        return redirect()->route('pbx.ivr.index')
                         ->with('success', __('messages.ivr_deleted'));
    }
}
