<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CallRecord;
use App\Models\Extension;
use App\Models\SmsConversation;
use App\Models\Ticket;
use App\Models\Trunk;
use App\Services\PBX\FreeSwitchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly FreeSwitchService $pbx) {}

    public function index(): Response
    {
        $pbxStatus = $this->pbx->getStatus();
        $activeCalls = count($this->pbx->getActiveCalls());

        $totalExtensions = Extension::count();
        $totalTrunks     = Trunk::count();
        $enabledTrunks   = Trunk::where('enabled', true)->count();
        $callsToday      = CallRecord::whereDate('started_at', today())->count();
        $minutesToday    = (int) round(CallRecord::whereDate('started_at', today())->sum('duration_seconds') / 60);
        $openTickets     = Ticket::where('status', 'open')->count();
        $unreadSms       = SmsConversation::where('status', 'new')->count();

        // 24-hour call volume chart (hourly buckets)
        $callVolumeChart = collect(range(0, 23))->map(function ($h) {
            $hour   = now()->startOfDay()->addHours($h);
            $total  = CallRecord::whereBetween('started_at', [$hour, $hour->copy()->addHour()])->count();
            $ans    = CallRecord::whereBetween('started_at', [$hour, $hour->copy()->addHour()])
                        ->whereNotNull('answered_at')->count();
            return ['hour' => $hour->format('H:00'), 'calls' => $total, 'answered' => $ans];
        })->toArray();

        // Recent calls formatted for the UI
        $recentCalls = CallRecord::latest('started_at')
            ->limit(10)
            ->get(['id', 'caller_id_number', 'caller_id_name', 'destination_number',
                   'duration_seconds', 'hangup_cause', 'started_at', 'answered_at'])
            ->map(fn($c) => [
                'id'                 => $c->id,
                'caller'             => $c->caller_id_number ?? '—',
                'caller_id_number'   => $c->caller_id_number ?? '',
                'destination'        => $c->destination_number ?? '—',
                'destination_number' => $c->destination_number ?? '',
                'duration'           => $c->duration_seconds
                                          ? gmdate('H:i:s', $c->duration_seconds)
                                          : '00:00:00',
                'duration_seconds'   => (int) ($c->duration_seconds ?? 0),
                'status'             => $c->hangup_cause === 'NORMAL_CLEARING' && $c->answered_at
                                          ? 'answered'
                                          : ($c->answered_at ? 'answered' : 'missed'),
                'started_at'         => $c->started_at?->format('Y-m-d H:i'),
            ]);

        // Basic server metrics via sys calls (graceful fallback)
        $cpuPercent  = 0;
        $ramPercent  = 0;
        $diskPercent = 0;
        $loadAvg     = '—';
        if (PHP_OS_FAMILY !== 'Windows') {
            $la          = sys_getloadavg();
            $loadAvg     = $la ? implode(', ', array_map(fn($v) => round($v, 2), $la)) : '—';
            $memInfo     = @file_get_contents('/proc/meminfo');
            if ($memInfo) {
                preg_match('/MemTotal:\s+(\d+)/', $memInfo, $mt);
                preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $ma);
                if ($mt && $ma) {
                    $ramPercent = (int) round((1 - $ma[1] / $mt[1]) * 100);
                }
            }
            $df = shell_exec('df -P / 2>/dev/null | tail -1');
            if ($df && preg_match('/(\d+)%/', $df, $m)) {
                $diskPercent = (int) $m[1];
            }
        }

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'active_calls'           => $activeCalls,
                'registered_extensions'  => $totalExtensions,
                'total_extensions'       => $totalExtensions,
                'online_agents'          => 0,
                'total_agents'           => 0,
                'waiting_calls'          => 0,
                'available_trunks'       => $enabledTrunks,
                'total_trunks'           => $totalTrunks,
                'unread_sms'             => $unreadSms,
                'unread_email'           => 0,
                'open_tickets'           => $openTickets,
                'today_call_cost'        => '0.00',
                'today_call_count'       => $callsToday,
                'calls_today'            => $callsToday,
                'today_call_minutes'     => $minutesToday,
            ],
            'pbx' => [
                'connected' => $pbxStatus['connected'] ?? false,
                'version'   => $pbxStatus['version'] ?? null,
                'uptime'    => null,
            ],
            'server' => [
                'cpu_percent'  => $cpuPercent,
                'ram_percent'  => $ramPercent,
                'disk_percent' => $diskPercent,
                'load_avg'     => $loadAvg,
            ],
            'call_volume_chart' => $callVolumeChart,
            'recent_calls'      => $recentCalls,
            'security_alerts'   => 0,
        ]);
    }

    public function metrics(): JsonResponse
    {
        return response()->json([
            'active_calls' => count($this->pbx->getActiveCalls()),
            'pbx_status'   => $this->pbx->getStatus(),
            'calls_1h'     => CallRecord::where('started_at', '>=', now()->subHour())->count(),
        ]);
    }
}
