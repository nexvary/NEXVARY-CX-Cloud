<?php
namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\DeviceProvision;
use App\Models\Extension;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeviceProvisionController extends Controller
{
    public function index(): \Inertia\Response
    {
        return Inertia::render('PBX/DeviceProvisions/Index', [
            'provisions' => DeviceProvision::with('extension')->orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function create(): \Inertia\Response
    {
        return Inertia::render('PBX/DeviceProvisions/Form', [
            'extensions'  => Extension::orderBy('extension_number')->get(['id', 'number', 'description']),
            'deviceTypes' => DeviceProvision::$deviceTypes,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'extension_id' => ['nullable', 'uuid', 'exists:extensions,id'],
            'mac_address'  => ['nullable', 'string', 'max:20', 'regex:/^[0-9a-fA-F:.-]{12,17}$/'],
            'device_type'  => ['required', 'string', 'in:' . implode(',', DeviceProvision::$deviceTypes)],
            'enabled'      => ['boolean'],
            'settings'     => ['nullable', 'array'],
        ]);

        $validated['tenant_id'] = $request->user()->tenant_id;
        ['raw' => $raw, 'hash' => $hash] = DeviceProvision::generateToken();
        $validated['provision_token_hash'] = $hash;

        DeviceProvision::create($validated);

        return redirect()->route('pbx.device-provisions.index')
            ->with('provision_token', $raw)
            ->with('success', __('messages.provision_created'));
    }

    public function edit(DeviceProvision $deviceProvision): \Inertia\Response
    {
        return Inertia::render('PBX/DeviceProvisions/Form', [
            'provision'   => $deviceProvision,
            'extensions'  => Extension::orderBy('extension_number')->get(['id', 'number', 'description']),
            'deviceTypes' => DeviceProvision::$deviceTypes,
        ]);
    }

    public function update(Request $request, DeviceProvision $deviceProvision): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'extension_id' => ['nullable', 'uuid', 'exists:extensions,id'],
            'mac_address'  => ['nullable', 'string', 'max:20', 'regex:/^[0-9a-fA-F:.-]{12,17}$/'],
            'device_type'  => ['required', 'string', 'in:' . implode(',', DeviceProvision::$deviceTypes)],
            'enabled'      => ['boolean'],
            'settings'     => ['nullable', 'array'],
        ]);
        $deviceProvision->update($validated);
        return redirect()->route('pbx.device-provisions.index')
            ->with('success', __('messages.provision_updated'));
    }

    public function destroy(DeviceProvision $deviceProvision): \Illuminate\Http\RedirectResponse
    {
        $deviceProvision->delete();
        return redirect()->route('pbx.device-provisions.index')
            ->with('success', __('messages.provision_deleted'));
    }

    /** Regenerate the provision token for a device — old URL immediately stops working. */
    public function regenerateToken(DeviceProvision $deviceProvision): \Illuminate\Http\JsonResponse
    {
        ['raw' => $raw, 'hash' => $hash] = DeviceProvision::generateToken();
        $deviceProvision->update(['provision_token_hash' => $hash]);
        return response()->json(['token' => $raw, 'provisioning_url' => route('provision.serve', $raw)]);
    }
}
