<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CallRecord;
use App\Models\Extension;
use App\Models\Ticket;
use App\Models\Trunk;
use App\Services\PBX\FreeSwitchService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly FreeSwitchService $pbx) {}

    public function index(): Response
    {
        $pbxHealth = $this->pbx->healthCheck();

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'extensions'    => Extension::count(),
                'trunks'        => Trunk::where('is_active', true)->count(),
                'calls_today'   => CallRecord::whereDate('started_at', today())->count(),
                'open_tickets'  => Ticket::where('status', 'open')->count(),
            ],
            'pbx_health'  => $pbxHealth,
            'recent_calls' => CallRecord::with('extension')
                ->latest('started_at')
                ->limit(10)
                ->get(['id', 'caller_id', 'destination', 'direction', 'duration', 'status', 'started_at', 'extension_id']),
        ]);
    }

    public function metrics(): JsonResponse
    {
        return response()->json([
            'active_calls' => $this->pbx->getActiveCalls(),
            'pbx_uptime'   => $this->pbx->healthCheck(),
            'calls_1h'     => CallRecord::where('started_at', '>=', now()->subHour())->count(),
        ]);
    }
}
