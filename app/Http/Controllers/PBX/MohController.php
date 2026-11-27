<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\MohClass;
use App\Services\PBX\MohService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MohController extends Controller
{
    public function __construct(private MohService $service) {}

    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;
        return Inertia::render('PBX/MoH/Index', [
            'classes' => $this->service->list($tenantId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:128'],
            'source'     => ['required', 'in:files,stream_url'],
            'stream_url' => ['nullable', 'string', 'max:512'],
            'shuffle'    => ['boolean'],
            'enabled'    => ['boolean'],
        ]);
        $data['tenant_id'] = $request->user()->tenant_id;
        $this->service->create($data);
        return redirect()->route('pbx.moh.index')->with('success', 'MoH class created.');
    }

    public function update(Request $request, MohClass $moh): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:128'],
            'source'     => ['required', 'in:files,stream_url'],
            'stream_url' => ['nullable', 'string', 'max:512'],
            'shuffle'    => ['boolean'],
            'enabled'    => ['boolean'],
        ]);
        $this->service->update($moh, $data);
        return redirect()->route('pbx.moh.index')->with('success', 'MoH class updated.');
    }

    public function destroy(MohClass $moh): RedirectResponse
    {
        $this->service->delete($moh);
        return redirect()->route('pbx.moh.index')->with('success', 'MoH class deleted.');
    }
}
