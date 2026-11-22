<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\Did;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DidController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('PBX/DIDs/Index', [
            'dids' => Did::latest()->paginate(25),
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
            'destination_type' => ['nullable', 'string'],
            'destination_id'   => ['nullable', 'uuid'],
        ]);

        Did::create($validated);

        return redirect()->route('pbx.dids.index')
                         ->with('success', __('messages.did_created'));
    }

    public function show(Did $did): Response
    {
        return Inertia::render('PBX/DIDs/Form', ['did' => $did, 'readonly' => true]);
    }

    public function edit(Did $did): Response
    {
        return Inertia::render('PBX/DIDs/Form', ['did' => $did]);
    }

    public function update(Request $request, Did $did): \Illuminate\Http\RedirectResponse
    {
        $did->update($request->validate([
            'number'           => ['required', 'string'],
            'description'      => ['nullable', 'string'],
            'trunk_id'         => ['nullable', 'uuid'],
            'destination_type' => ['nullable', 'string'],
            'destination_id'   => ['nullable', 'uuid'],
            'enabled'          => ['boolean'],
        ]));

        return redirect()->route('pbx.dids.index')
                         ->with('success', __('messages.did_updated'));
    }

    public function destroy(Did $did): \Illuminate\Http\RedirectResponse
    {
        $did->delete();

        return redirect()->route('pbx.dids.index')
                         ->with('success', __('messages.did_deleted'));
    }
}
