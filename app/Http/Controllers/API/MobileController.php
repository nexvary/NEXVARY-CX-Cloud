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
use App\Services\PBX\PbxMode;
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
        $pbxHealth = PbxMode::fusionPbx() ? ['status' => 'managed'] : $this->pbx->healthCheck();

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

        // Tenant isolation is enforced by the BelongsToTenant global scope on SmsConversation
        $conversations = SmsConversation::latest('last_message_at')
            ->paginate($perPage, ['id', 'did_number', 'contact_number', 'status', 'last_message_at']);

        return response()->json($conversations);
    }

    public function smsMessages(SmsConversation $conversation): JsonResponse
    {
        // Route model binding resolves through the BelongsToTenant global scope,
        // so a conversation from another tenant triggers a 404 automatically.
        $messages = $conversation->messages()->latest()->limit(100)->get(
            ['id', 'direction', 'body', 'status', 'telnyx_message_id', 'sent_at', 'delivered_at']
        );

        return response()->json([
            'conversation' => $conversation->only('id', 'did_number', 'contact_number', 'status'),
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

    // ── Device management ─────────────────────────────────────────────────────

    public function listDevices(Request $request): JsonResponse
    {
        $devices = $request->user()->tokens()
            ->where('name', 'like', 'device-%')
            ->get(['id', 'name', 'last_used_at', 'expires_at', 'created_at']);

        return response()->json(['data' => $devices]);
    }

    public function revokeDevice(Request $request, int $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()
            ->where('id', $tokenId)
            ->where('name', 'like', 'device-%')
            ->delete();

        if (!$deleted) {
            return response()->json(['error' => 'Device token not found'], 404);
        }

        return response()->json(['revoked' => true]);
    }

    // ── Pairing flow ──────────────────────────────────────────────────────────

    /**
     * Step 1 (web UI): generate a short-lived pairing QR token.
     * Called from the web settings page — requires web session auth (not mobile token).
     * The token has only the 'mobile.pair' ability so it cannot access the API directly.
     */
    public static function generatePairingToken(\Illuminate\Http\Request $request): JsonResponse
    {
        $user = $request->user();

        // Clean up expired pairing tokens only (do not revoke active device tokens)
        $user->tokens()
            ->where('name', 'like', 'pairing-%')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '<', now()))
            ->delete();

        $token = $user->createToken(
            name: 'pairing-' . now()->timestamp,
            abilities: ['mobile.pair'],
            expiresAt: now()->addMinutes(10),
        );

        return response()->json([
            'pairing_token' => $token->plainTextToken,
            'expires_at'    => now()->addMinutes(10)->toIso8601String(),
            'server_url'    => config('app.url'),
            'api_version'   => 'v1',
        ]);
    }

    /**
     * Render the web pairing page (Inertia).
     * Generates a fresh short-lived pairing token, encodes the QR payload as a
     * server-side SVG (via bacon/bacon-qr-code) and passes the base64 image to
     * the frontend so no external QR service ever receives the token.
     */
    public static function renderPairingPage(\Illuminate\Http\Request $request): \Illuminate\Http\Response|\Inertia\Response
    {
        $user = $request->user();

        // Clean expired pairing tokens before issuing a new one
        $user->tokens()
            ->where('name', 'like', 'pairing-%')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '<', now()))
            ->delete();

        $token = $user->createToken(
            name: 'pairing-' . now()->timestamp,
            abilities: ['mobile.pair'],
            expiresAt: now()->addMinutes(10),
        );

        $expiresAt = now()->addMinutes(10)->toIso8601String();
        $payload   = json_encode([
            'pairing_token' => $token->plainTextToken,
            'server_url'    => config('app.url'),
        ], JSON_UNESCAPED_SLASHES);

        // Render QR as SVG using the already-required bacon/bacon-qr-code library.
        // The SVG is base64-encoded and passed as a data URI so no external request
        // is needed and the token never leaves the server boundary.
        $qrDataUri = null;
        try {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(280),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd(),
            );
            $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode(
                (new \BaconQrCode\Writer($renderer))->writeString($payload)
            );
        } catch (\Throwable) {
            // Graceful degradation: page renders without QR — user can copy the payload
        }

        return inertia('Settings/MobilePairing', [
            'qr_data_uri'    => $qrDataUri,
            'payload'        => $payload,
            'expires_at'     => $expiresAt,
        ]);
    }

    /**
     * Step 2 (Android app): exchange a one-time pairing token for a durable device token.
     * Requires a valid bearer token with 'mobile.pair' ability.
     * The pairing token is immediately revoked after exchange (single-use).
     */
    public function exchangePairingToken(Request $request): JsonResponse
    {
        $request->validate([
            'device_name' => ['required', 'string', 'max:100'],
            'device_id'   => ['required', 'string', 'max:200'],
        ]);

        $user        = $request->user();
        $pairingToken = $request->bearerToken();

        // Create the durable device token with minimal required abilities
        $deviceToken = $user->createToken(
            name: 'device-' . substr(md5($request->input('device_id')), 0, 8),
            abilities: ['mobile'],
            expiresAt: now()->addDays(90),
        );

        // Revoke the single-use pairing token immediately
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'token'       => $deviceToken->plainTextToken,
            'expires_at'  => now()->addDays(90)->toIso8601String(),
            'server_url'  => config('app.url'),
            'api_version' => 'v1',
        ]);
    }
}
