<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CallRecord;
use App\Models\Extension;
use App\Models\SmsConversation;
use App\Models\SmsMessage;
use App\Models\Ticket;
use App\Models\Trunk;
use App\Services\PBX\FreeSwitchService;
use App\Services\Telnyx\TelnyxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * REST API for the NEXVARY CX Manager Android application.
 *
 * All routes are under /api/mobile/v1 and require:
 *   - Valid bearer token (sanctum)
 *   - 'mobile' ability on the token
 *
 * Privileged writes additionally require the 'mobile.admin' ability.
 *
 * The mobile client MUST NOT connect directly to FreeSWITCH ESL,
 * SSH, or any infrastructure port. All privileged operations go
 * through these endpoints which validate, authorize, and audit.
 */
class MobileController extends Controller
{
    public function __construct(private readonly FreeSwitchService $pbx) {}

    // ── Server Status ─────────────────────────────────────────────────────────

    public function serverStatus(): JsonResponse
    {
        $pbxHealth = $this->pbx->healthCheck();

        $ram_percent = 0;
        $disk_percent = 0;
        $load_avg = null;
        if (PHP_OS_FAMILY !== 'Windows') {
            $memInfo = @file_get_contents('/proc/meminfo');
            if ($memInfo) {
                preg_match('/MemTotal:\s+(\d+)/', $memInfo, $mt);
                preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $ma);
                if ($mt && $ma) {
                    $ram_percent = (int) round((1 - $ma[1] / $mt[1]) * 100);
                }
            }
            $la = sys_getloadavg();
            $load_avg = $la ? array_map(fn($v) => round($v, 2), $la) : null;
            $df = shell_exec('df -P / 2>/dev/null | tail -1');
            if ($df && preg_match('/(\d+)%/', $df, $m)) {
                $disk_percent = (int) $m[1];
            }
        }

        $dbOk = DB::connection()->getDatabaseName() !== null;

        return response()->json([
            'server' => [
                'ram_percent'  => $ram_percent,
                'disk_percent' => $disk_percent,
                'load_avg'     => $load_avg,
                'uptime'       => @file_get_contents('/proc/uptime')
                                      ? (int) explode(' ', (string) @file_get_contents('/proc/uptime'))[0]
                                      : null,
            ],
            'services' => [
                'postgresql'  => $dbOk ? 'up' : 'down',
                'freeswitch'  => $pbxHealth['status'] ?? 'unknown',
                'freeswitch_version' => $pbxHealth['version'] ?? null,
            ],
            'pbx' => [
                'active_calls'  => $pbxHealth['active_calls'] ?? 0,
                'registrations' => $pbxHealth['registrations'] ?? 0,
            ],
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    // ── Extensions ────────────────────────────────────────────────────────────

    public function extensions(): JsonResponse
    {
        $extensions = Extension::latest()->get(['id', 'number', 'description', 'enabled']);
        $registrations = $this->pbx->getRegistrations();
        $registeredNumbers = collect($registrations)->pluck('user')->flip();

        $result = $extensions->map(fn($e) => [
            'id'          => $e->id,
            'number'      => $e->number,
            'description' => $e->description,
            'enabled'     => $e->enabled,
            'registered'  => $registeredNumbers->has($e->number),
        ]);

        return response()->json(['data' => $result, 'total' => $result->count()]);
    }

    // ── Active Calls ──────────────────────────────────────────────────────────

    public function activeCalls(): JsonResponse
    {
        $calls = $this->pbx->getActiveCalls();
        return response()->json([
            'data'  => $calls,
            'total' => count($calls),
        ]);
    }

    // ── Call History ──────────────────────────────────────────────────────────

    public function callHistory(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->query('per_page', 25)), 100);
        $calls = CallRecord::latest('started_at')
            ->paginate($perPage, ['id', 'caller_id_number', 'destination_number',
                                  'direction', 'duration_seconds', 'hangup_cause', 'started_at']);

        return response()->json($calls);
    }

    // ── SMS Conversations ─────────────────────────────────────────────────────

    public function smsInbox(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->query('per_page', 25)), 100);
        $conversations = SmsConversation::latest('last_message_at')
            ->paginate($perPage, ['id', 'contact_number', 'status', 'last_message_at']);

        return response()->json($conversations);
    }

    public function smsMessages(SmsConversation $conversation): JsonResponse
    {
        $messages = $conversation->messages()->latest()->limit(100)->get(
            ['id', 'direction', 'body', 'status', 'sent_at', 'delivered_at']
        );

        return response()->json([
            'conversation' => $conversation->only('id', 'contact_number', 'status'),
            'messages'     => $messages,
        ]);
    }

    // ── Alerts ────────────────────────────────────────────────────────────────

    public function alerts(): JsonResponse
    {
        $alerts = [];
        $pbxHealth = $this->pbx->healthCheck();

        if (!($pbxHealth['connected'] ?? false)) {
            $alerts[] = ['severity' => 'critical', 'message' => 'FreeSWITCH is not responding', 'since' => now()->toIso8601String()];
        }

        $openTickets = Ticket::where('status', 'open')->count();
        if ($openTickets > 0) {
            $alerts[] = ['severity' => 'info', 'message' => "{$openTickets} open support ticket(s)", 'since' => null];
        }

        return response()->json(['data' => $alerts, 'total' => count($alerts)]);
    }

    // ── Pairing / Token management ────────────────────────────────────────────

    /**
     * Returns a time-limited QR pairing token for a new device.
     * Requires the 'mobile.admin' ability.
     */
    public function generatePairingQr(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke old mobile tokens for this user
        $user->tokens()->where('name', 'like', 'mobile-%')->delete();

        // Create a short-lived pairing token (valid 10 minutes)
        $token = $user->createToken(
            name: 'mobile-' . now()->timestamp,
            abilities: ['mobile'],
            expiresAt: now()->addMinutes(10)
        );

        return response()->json([
            'pairing_token' => $token->plainTextToken,
            'expires_at'    => now()->addMinutes(10)->toIso8601String(),
            'server_url'    => config('app.url'),
            'api_version'   => 'v1',
        ]);
    }
}
