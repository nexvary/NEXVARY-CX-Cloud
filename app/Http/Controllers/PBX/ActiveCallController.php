<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Services\PBX\ActiveCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActiveCallController extends Controller
{
    public function __construct(private ActiveCallService $calls) {}

    public function index(Request $request): JsonResponse
    {
        $calls = $this->calls->getCallsForTenant($request->user()->tenant_id);
        return response()->json(['calls' => array_values($calls)]);
    }

    public function hold(Request $request, string $uuid): JsonResponse
    {
        $mohClass = $request->input('moh_class');
        $ok = $this->calls->hold($uuid, $request->user()->tenant_id, $mohClass);
        return response()->json(['ok' => $ok], $ok ? 200 : 422);
    }

    public function resume(Request $request, string $uuid): JsonResponse
    {
        $ok = $this->calls->resume($uuid, $request->user()->tenant_id);
        return response()->json(['ok' => $ok], $ok ? 200 : 422);
    }

    public function transfer(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'type'        => ['required', 'in:blind,attended'],
            'destination' => ['required_if:type,blind', 'nullable', 'string'],
            'consult_uuid'=> ['required_if:type,attended', 'nullable', 'string', 'uuid'],
        ]);

        $tenantId = $request->user()->tenant_id;

        if ($request->input('type') === 'attended') {
            $ok = $this->calls->attendedTransfer($uuid, $request->input('consult_uuid'), $tenantId);
        } else {
            $ok = $this->calls->blindTransfer($uuid, $request->input('destination'), $tenantId);
        }

        return response()->json(['ok' => $ok], $ok ? 200 : 422);
    }

    public function park(Request $request, string $uuid): JsonResponse
    {
        $slot = $this->calls->park($uuid, $request->user()->tenant_id);
        $ok   = $slot !== '';
        return response()->json(['ok' => $ok, 'slot' => $slot ?: null], $ok ? 200 : 422);
    }

    public function listParked(Request $request): JsonResponse
    {
        $parked = $this->calls->listParked($request->user()->tenant_id);
        return response()->json(['parked' => $parked]);
    }

    public function pickup(Request $request, string $slot): JsonResponse
    {
        $request->validate([
            'retrieve_uuid' => ['required', 'string', 'uuid'],
        ]);
        $ok = $this->calls->pickup($request->input('retrieve_uuid'), $slot, $request->user()->tenant_id);
        return response()->json(['ok' => $ok], $ok ? 200 : 422);
    }
}
