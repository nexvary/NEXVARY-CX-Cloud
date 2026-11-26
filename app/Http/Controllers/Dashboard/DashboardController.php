<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CallRecord;
use App\Models\Extension;
use App\Models\SmsConversation;
use App\Models\Ticket;
use App\Models\Trunk;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\PbxMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly FreeSwitchService $pbx) {}

    public function index(): Response
    {
        $pbxStatus = PbxMode::fusionPbx() ? ['connected' => false] : $this->pbx->getStatus();
        $activeCalls = PbxMode::fusionPbx() ? 0 : count($this->pbx->getActiveCalls());

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

        $trunks = Trunk::select('id', 'name', 'sip_host', 'enabled')
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id'      => $t->id,
                'name'    => $t->name,
                'host'    => $t->sip_host,
                'enabled' => (bool) $t->enabled,
            ])->toArray();

        $dbHealthy    = $this->probeDb();
        $redisHealthy = $this->probeRedis();

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'extensions'             => $totalExtensions,
                'trunks'                 => $totalTrunks,
                'calls_today'            => $callsToday,
                'open_tickets'           => $openTickets,
                'active_calls'           => $activeCalls,
                'available_trunks'       => $enabledTrunks,
                'unread_sms'             => $unreadSms,
                'today_call_minutes'     => $minutesToday,
            ],
            'pbx_health' => [
                'connected' => $pbxStatus['connected'] ?? false,
                'version'   => $pbxStatus['version'] ?? null,
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
            'trunks'            => $trunks,
            'services'          => [
                'freeswitch' => $pbxStatus['connected'] ?? false,
                'database'   => $dbHealthy,
                'redis'      => $redisHealthy,
            ],
        ]);
    }

    private function probeDb(): bool
    {
        try { return (int) DB::scalar('select 1') === 1; } catch (\Throwable) { return false; }
    }

    private function probeRedis(): bool
    {
        try { return (string) Redis::connection()->ping() === 'PONG'; } catch (\Throwable) { return false; }
    }

    public function metrics(): JsonResponse
    {
        return response()->json([
            'active_calls' => count($this->pbx->getActiveCalls()),
            'pbx_status'   => PbxMode::fusionPbx() ? null : $this->pbx->getStatus(),
            'calls_1h'     => CallRecord::where('started_at', '>=', now()->subHour())->count(),
        ]);
    }
}
