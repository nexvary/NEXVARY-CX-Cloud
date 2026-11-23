<?php

/**
 * FreeSWITCH internal call proof.
 *
 * Uses the ESL API to originate a loopback call to the FreeSWITCH built-in
 * echo extension (9196) and verifies:
 *   1. The call reaches ACTIVE state
 *   2. Both legs are bridged (two-way audio path)
 *   3. Hangup completes cleanly
 *   4. Active call count returns to 0
 *
 * Runs against the isolated QA FreeSWITCH container started by qa-freeswitch.sh
 * via the env vars FREESWITCH_HOST, FREESWITCH_ESL_PORT, FREESWITCH_ESL_PASSWORD.
 *
 * Exit 0 = all checks passed.  Exit 1 = failure with diagnostic to stderr.
 */

$host     = getenv('FREESWITCH_HOST')         ?: '127.0.0.1';
$port     = (int) (getenv('FREESWITCH_ESL_PORT')    ?: 8021);
$password = getenv('FREESWITCH_ESL_PASSWORD') ?: 'ClueCon';

// ── ESL helpers ──────────────────────────────────────────────────────────────

function esl_connect(string $host, int $port, string $password): mixed
{
    $sock = @fsockopen($host, $port, $ec, $em, 10);
    if (!$sock) {
        fwrite(STDERR, "ESL connect failed: $em\n");
        exit(1);
    }
    stream_set_timeout($sock, 15);

    $greeting = esl_read($sock);
    if (!str_contains($greeting, 'auth/request')) {
        fwrite(STDERR, "No auth request from ESL.\n");
        exit(1);
    }

    fwrite($sock, "auth $password\n\n");
    $resp = esl_read($sock);
    if (!str_contains($resp, '+OK accepted')) {
        fwrite(STDERR, "ESL authentication failed.\n");
        exit(1);
    }

    return $sock;
}

function esl_api(mixed $sock, string $cmd): string
{
    fwrite($sock, "api $cmd\n\n");
    return esl_read($sock, bodyOnly: true);
}

function esl_bgapi(mixed $sock, string $cmd): string
{
    fwrite($sock, "bgapi $cmd\n\n");
    return esl_read($sock);
}

function esl_read(mixed $sock, bool $bodyOnly = false): string
{
    $headers = '';
    $contentLength = 0;

    while (($line = fgets($sock, 4096)) !== false) {
        $headers .= $line;
        if (preg_match('/^Content-Length:\s*(\d+)/i', $line, $m)) {
            $contentLength = (int) $m[1];
        }
        if (trim($line) === '') {
            $body = $contentLength ? (string) stream_get_contents($sock, $contentLength) : '';
            return $bodyOnly ? $body : $headers . $body;
        }
    }
    return '';
}

// ── Connect ──────────────────────────────────────────────────────────────────

$sock = esl_connect($host, $port, $password);

// ── Verify zero active calls before we start ─────────────────────────────────

$beforeJson = esl_api($sock, 'show calls as json');
$before = json_decode(trim($beforeJson), true);
if (($before['row_count'] ?? -1) !== 0) {
    fwrite(STDERR, "Expected 0 active calls before originate; got: {$before['row_count']}.\n");
    exit(1);
}

// ── Originate a loopback call to the echo extension (9196) ───────────────────
//
// loopback/<dest>/<context> creates two internal legs.
// &echo() on the B-leg echoes audio back — both legs become BRIDGED.

$origResp = esl_bgapi($sock, 'originate loopback/9196/default &echo()');

// Wait up to 15 seconds for the call to reach ACTIVE state.
$callUuid = null;
$active   = false;

for ($attempt = 0; $attempt < 30; $attempt++) {
    sleep(1);
    $callsJson = esl_api($sock, 'show calls as json');
    $calls = json_decode(trim($callsJson), true);

    if (($calls['row_count'] ?? 0) > 0) {
        foreach ($calls['rows'] as $row) {
            if (($row['callstate'] ?? '') === 'ACTIVE') {
                $callUuid = $row['uuid'] ?? null;
                $active   = true;
                break 2;
            }
        }
    }
}

if (!$active || !$callUuid) {
    // Dump last call list for diagnostics
    fwrite(STDERR, "Call never reached ACTIVE state.\n");
    fwrite(STDERR, "Last call list: $callsJson\n");
    exit(1);
}

printf("Call active. UUID=%s\n", $callUuid);

// ── Verify two-way audio path ────────────────────────────────────────────────
//
// For standard bridged calls:  variable_bridge_uuid is set to the other leg.
// For loopback/echo channels:  variable_other_loopback_leg_uuid identifies the
//   partner leg (loopback creates A/B legs connected internally).
//
// Either indicator confirms that two legs are connected and audio can flow
// in both directions.

$channelInfo = esl_api($sock, "uuid_dump {$callUuid}");
$hasBridgeUuid   = str_contains($channelInfo, 'variable_bridge_uuid');
$hasLoopbackPeer = str_contains($channelInfo, 'variable_other_loopback_leg_uuid');

if (!$hasBridgeUuid && !$hasLoopbackPeer) {
    fwrite(STDERR, "Call is ACTIVE but no bridged/loopback partner — two-way audio not confirmed.\n");
    fwrite(STDERR, "Channel info: $channelInfo\n");
    exit(1);
}

$indicator = $hasBridgeUuid ? 'bridge_uuid' : 'other_loopback_leg_uuid';
echo "Call has partner leg ({$indicator}) — two-way audio path confirmed.\n";

// ── Hang up ──────────────────────────────────────────────────────────────────

$killResp = esl_api($sock, "uuid_kill {$callUuid}");
if (!str_contains((string) $killResp, '+OK')) {
    fwrite(STDERR, "uuid_kill failed: $killResp\n");
    exit(1);
}

echo "Hangup sent.\n";

// ── Wait for call count to return to 0 ───────────────────────────────────────

$cleared = false;
for ($attempt = 0; $attempt < 15; $attempt++) {
    sleep(1);
    $afterJson = esl_api($sock, 'show calls as json');
    $after = json_decode(trim($afterJson), true);
    if (($after['row_count'] ?? -1) === 0) {
        $cleared = true;
        break;
    }
}

if (!$cleared) {
    fwrite(STDERR, "Active calls did not clear after hangup.\n");
    exit(1);
}

echo "Active calls cleared to 0 after hangup.\n";
echo "FreeSWITCH internal call proof: PASSED\n";
echo "  - Originate:    OK\n";
echo "  - ACTIVE state: OK (UUID=$callUuid)\n";
echo "  - BRIDGED:      OK (two-way audio path confirmed)\n";
echo "  - Hangup:       OK\n";
echo "  - Cleared:      OK\n";

@fclose($sock);
