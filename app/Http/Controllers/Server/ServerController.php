<?php

namespace App\Http\Controllers\Server;

use App\Http\Controllers\Controller;
use App\Services\PBX\FreeSwitchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Redis;

class ServerController extends Controller
{
    public function __construct(private readonly FreeSwitchService $pbx) {}

    public function index(): Response
    {
        $pbxStatus = $this->pbx->getStatus();
        $metrics   = $this->collectMetrics();

        return Inertia::render('Settings/Server', [
            'pbx'     => $pbxStatus,
            'metrics' => $metrics,
        ]);
    }

    private function collectMetrics(): array
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';

        // CPU / load
        $loadAvg    = $isWindows ? [0, 0, 0] : (sys_getloadavg() ?: [0, 0, 0]);
        $cpuPercent = 0;
        if (!$isWindows) {
            $stat1 = $this->readCpuStat();
            usleep(100_000);
            $stat2 = $this->readCpuStat();
            if ($stat1 && $stat2) {
                $idle1  = $stat1[3] + $stat1[4];
                $total1 = array_sum($stat1);
                $idle2  = $stat2[3] + $stat2[4];
                $total2 = array_sum($stat2);
                $dtotal = $total2 - $total1;
                $didle  = $idle2 - $idle1;
                $cpuPercent = $dtotal > 0 ? (int) round((1 - $didle / $dtotal) * 100) : 0;
            }
        }

        // RAM
        $ramTotal   = 0;
        $ramUsed    = 0;
        $ramPercent = 0;
        if (!$isWindows && ($memInfo = @file_get_contents('/proc/meminfo'))) {
            preg_match('/MemTotal:\s+(\d+)/', $memInfo, $mt);
            preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $ma);
            if ($mt && $ma) {
                $ramTotal   = (int) $mt[1];
                $ramUsed    = $ramTotal - (int) $ma[1];
                $ramPercent = (int) round($ramUsed / $ramTotal * 100);
            }
        }

        // Disk
        $diskTotal   = 0;
        $diskUsed    = 0;
        $diskPercent = 0;
        $freeSpace   = @disk_free_space('/');
        $totalSpace  = @disk_total_space('/');
        if ($freeSpace !== false && $totalSpace > 0) {
            $diskTotal   = (int) round($totalSpace / 1073741824);
            $diskUsed    = (int) round(($totalSpace - $freeSpace) / 1073741824);
            $diskPercent = (int) round((1 - $freeSpace / $totalSpace) * 100);
        }

        // PostgreSQL connectivity
        $pgOk = false;
        try {
            DB::connection()->getPdo();
            $pgOk = true;
        } catch (\Throwable) {}

        // Redis connectivity
        $redisOk = false;
        try {
            \Illuminate\Support\Facades\Redis::ping();
            $redisOk = true;
        } catch (\Throwable) {}

        return [
            'cpu_percent'   => $cpuPercent,
            'load_avg'      => array_map(fn($v) => round($v, 2), $loadAvg),
            'ram_total_kb'  => $ramTotal,
            'ram_used_kb'   => $ramUsed,
            'ram_percent'   => $ramPercent,
            'disk_total_gb' => $diskTotal,
            'disk_used_gb'  => $diskUsed,
            'disk_percent'  => $diskPercent,
            'postgresql'    => $pgOk,
            'redis'         => $redisOk,
            'freeswitch'    => $pbxStatus['connected'] ?? false,
            'php_version'   => PHP_VERSION,
            'os'            => php_uname('s') . ' ' . php_uname('r'),
            'timestamp'     => now()->toIso8601String(),
        ];
    }

    private function readCpuStat(): array|false
    {
        $data = @file_get_contents('/proc/stat');
        if (!$data) {
            return false;
        }
        preg_match('/^cpu\s+(.+)/m', $data, $m);

        return $m ? array_map('intval', explode(' ', trim($m[1]))) : false;
    }

    public function restartFreeswitch(): RedirectResponse
    {
        $this->pbx->bgapi('fsctl restart');

        return redirect()->route('settings.server')
                         ->with('success', 'FreeSWITCH restart command sent.');
    }

    public function saveOrganization(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'locale'   => ['nullable', 'string', 'max:10'],
            'country'  => ['nullable', 'string', 'max:10'],
        ]);

        $tenant = \App\Models\Tenant::find($request->user()->tenant_id);
        if ($tenant) {
            $update = ['name' => $validated['name']];
            if (!empty($validated['timezone'])) {
                $update['timezone'] = $validated['timezone'];
            }
            if (!empty($validated['locale'])) {
                $update['locale'] = $validated['locale'];
            }
            if (!empty($validated['country'])) {
                $existing = $tenant->settings ?? [];
                $existing['country'] = $validated['country'];
                $update['settings'] = $existing;
            }
            $tenant->update($update);
        }

        return redirect()->route('settings.organization')
                         ->with('success', 'Organization settings saved.');
    }
}
