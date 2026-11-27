<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PBX\FreeSwitchService;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    public function __construct(private readonly FreeSwitchService $pbx) {}

    public function health(): JsonResponse
    {
        return response()->json([
            'status'    => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function pbxStatus(): JsonResponse
    {
        return response()->json($this->pbx->healthCheck());
    }

    public function liveMetrics(): JsonResponse
    {
        return response()->json([
            'active_calls' => $this->pbx->getActiveCalls(),
            'timestamp'    => now()->toIso8601String(),
        ]);
    }

    public function activeCalls(): JsonResponse
    {
        return response()->json($this->pbx->getActiveCalls());
    }
}
