<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\CallAgent;
use App\Models\Extension;
use App\Models\CallQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CallAgentController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;
        $agents   = CallAgent::with('extension')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->paginate(50);

        return Inertia::render('PBX/Agents/Index', [
            'agents' => $agents,
        ]);
    }

    public function create(Request $request): Response
    {
        $extensions = Extension::where('tenant_id', $request->user()->tenant_id)
            ->orderBy('number')
            ->get(['id', 'number', 'description']);

        return Inertia::render('PBX/Agents/Form', [
            'agent'      => null,
            'extensions' => $extensions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:128'],
            'extension_id' => ['nullable', 'uuid'],
            'skill_level'  => ['integer', 'min:1', 'max:10'],
            'status'       => ['required', 'in:available,unavailable,on_break'],
            'enabled'      => ['boolean'],
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;
        CallAgent::create($data);

        return redirect()->route('pbx.agents.index')->with('success', 'Agent created.');
    }

    public function edit(Request $request, CallAgent $agent): Response
    {
        $extensions = Extension::where('tenant_id', $request->user()->tenant_id)
            ->orderBy('number')
            ->get(['id', 'number', 'description']);

        return Inertia::render('PBX/Agents/Form', [
            'agent'      => $agent,
            'extensions' => $extensions,
        ]);
    }

    public function update(Request $request, CallAgent $agent): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:128'],
            'extension_id' => ['nullable', 'uuid'],
            'skill_level'  => ['integer', 'min:1', 'max:10'],
            'status'       => ['required', 'in:available,unavailable,on_break'],
            'enabled'      => ['boolean'],
        ]);

        $agent->update($data);
        return redirect()->route('pbx.agents.index')->with('success', 'Agent updated.');
    }

    public function destroy(CallAgent $agent): RedirectResponse
    {
        $agent->delete();
        return redirect()->route('pbx.agents.index')->with('success', 'Agent removed.');
    }

    public function setStatus(Request $request, CallAgent $agent): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:available,unavailable,on_break'],
        ]);
        $agent->update(['status' => $data['status']]);
        return response()->json(['ok' => true, 'status' => $agent->status]);
    }
}
