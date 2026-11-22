<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\RingGroup;
use App\Services\PBX\FreeSwitchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RingGroupController extends Controller
{
    public function __construct(private readonly FreeSwitchService $pbx) {}

    public function index(): Response
    {
        return Inertia::render('PBX/RingGroups/Index', [
            'ringGroups' => RingGroup::with('members')->latest()->paginate(25),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('PBX/RingGroups/Form');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255', 'unique:ring_groups,name'],
            'extension'      => ['required', 'string', 'unique:ring_groups,extension'],
            'strategy'       => ['required', 'in:simultaneous,sequential,ordered'],
            'timeout'        => ['integer', 'min:10', 'max:300'],
            'destination'    => ['nullable', 'string'],
            'destination_type' => ['nullable', 'string'],
            'members'        => ['nullable', 'array'],
            'members.*'      => ['string'],
        ]);

        $rg = RingGroup::create(\Illuminate\Support\Arr::except($validated, ['members']));

        if (!empty($validated['members'])) {
            foreach ($validated['members'] as $number) {
                $rg->members()->create(['number' => $number]);
            }
        }

        $this->pbx->reloadDialplan();

        return redirect()->route('pbx.ring-groups.index')
                         ->with('success', 'Ring group created.');
    }

    public function edit(RingGroup $ringGroup): Response
    {
        return Inertia::render('PBX/RingGroups/Form', [
            'ringGroup' => $ringGroup->load('members'),
        ]);
    }

    public function update(Request $request, RingGroup $ringGroup): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'strategy'       => ['required', 'in:simultaneous,sequential,ordered'],
            'timeout'        => ['integer', 'min:10', 'max:300'],
            'destination'    => ['nullable', 'string'],
            'destination_type' => ['nullable', 'string'],
            'members'        => ['nullable', 'array'],
            'members.*'      => ['string'],
        ]);

        $ringGroup->update(\Illuminate\Support\Arr::except($validated, ['members']));

        if (isset($validated['members'])) {
            $ringGroup->members()->delete();
            foreach ($validated['members'] as $number) {
                $ringGroup->members()->create(['number' => $number]);
            }
        }

        $this->pbx->reloadDialplan();

        return redirect()->route('pbx.ring-groups.index')
                         ->with('success', 'Ring group updated.');
    }

    public function destroy(RingGroup $ringGroup): \Illuminate\Http\RedirectResponse
    {
        $ringGroup->members()->delete();
        $ringGroup->delete();
        $this->pbx->reloadDialplan();

        return redirect()->route('pbx.ring-groups.index')
                         ->with('success', 'Ring group deleted.');
    }
}
