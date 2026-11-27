<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\CallBlacklist;
use App\Services\PBX\BlacklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlacklistController extends Controller
{
    public function __construct(private BlacklistService $service) {}

    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;
        return Inertia::render('PBX/Blacklist/Index', [
            'entries' => $this->service->list($tenantId),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('PBX/Blacklist/Form', ['entry' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pattern'      => ['required', 'string', 'max:64'],
            'pattern_type' => ['required', 'in:exact,prefix,regex'],
            'direction'    => ['required', 'in:inbound,outbound,both'],
            'description'  => ['nullable', 'string', 'max:255'],
            'enabled'      => ['boolean'],
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;
        $this->service->create($data);

        return redirect()->route('pbx.blacklist.index')->with('success', 'Entry added.');
    }

    public function edit(CallBlacklist $entry): Response
    {
        return Inertia::render('PBX/Blacklist/Form', ['entry' => $entry]);
    }

    public function update(Request $request, CallBlacklist $entry): RedirectResponse
    {
        $data = $request->validate([
            'pattern'      => ['required', 'string', 'max:64'],
            'pattern_type' => ['required', 'in:exact,prefix,regex'],
            'direction'    => ['required', 'in:inbound,outbound,both'],
            'description'  => ['nullable', 'string', 'max:255'],
            'enabled'      => ['boolean'],
        ]);

        $this->service->update($entry, $data);

        return redirect()->route('pbx.blacklist.index')->with('success', 'Entry updated.');
    }

    public function destroy(CallBlacklist $entry): RedirectResponse
    {
        $this->service->delete($entry);
        return redirect()->route('pbx.blacklist.index')->with('success', 'Entry removed.');
    }
}
