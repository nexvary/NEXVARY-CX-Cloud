<?php

namespace App\Services\PBX;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * FreeSWITCH ESL (Event Socket Library) integration service.
 * Communicates with FreeSWITCH over its native TCP event socket.
 */
class FreeSwitchService
{
    private ?mixed $socket = null;
    private bool $authenticated = false;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $password,
    ) {}

    public static function make(): static
    {
        return new static(
            host: config('nexvary.freeswitch.host', '127.0.0.1'),
            port: (int) config('nexvary.freeswitch.esl_port', 8021),
            password: config('nexvary.freeswitch.esl_password', 'ClueCon'),
        );
    }

    // ── Connection ────────────────────────────────────────────────────────────

    public function connect(): bool
    {
        try {
            $this->socket = @fsockopen(
                hostname: $this->host,
                port: $this->port,
                error_code: $errCode,
                error_message: $errMsg,
                timeout: 5.0
            );

            if (!$this->socket) {
                Log::error('FreeSWITCH ESL connect failed', [
                    'host' => $this->host,
                    'port' => $this->port,
                    'error' => $errMsg,
                ]);
                return false;
            }

            stream_set_timeout($this->socket, 10);
            $this->readResponse(); // "auth/request"

            fwrite($this->socket, "auth {$this->password}\n\n");
            $response = $this->readResponse();

            $this->authenticated = str_contains($response, '+OK accepted');

            if (!$this->authenticated) {
                Log::error('FreeSWITCH ESL authentication failed');
                $this->disconnect();
                return false;
            }

            return true;
        } catch (Exception $e) {
            Log::error('FreeSWITCH ESL connection exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
            $this->authenticated = false;
        }
    }

    public function isConnected(): bool
    {
        return $this->socket !== null && $this->authenticated;
    }

    // ── Core ESL API ──────────────────────────────────────────────────────────

    public function api(string $command): string|false
    {
        if (!$this->ensureConnected()) {
            return false;
        }

        fwrite($this->socket, "api {$command}\n\n");
        return $this->readResponse();
    }

    public function bgapi(string $command): string|false
    {
        if (!$this->ensureConnected()) {
            return false;
        }

        fwrite($this->socket, "bgapi {$command}\n\n");
        return $this->readResponse();
    }

    // ── Status ────────────────────────────────────────────────────────────────

    public function getStatus(): array
    {
        $raw = $this->api('status');
        if (!$raw) {
            return ['connected' => false, 'status' => 'unreachable'];
        }

        $lines = explode("\n", trim($raw));
        $data = ['connected' => true, 'raw' => $raw];

        foreach ($lines as $line) {
            if (preg_match('/(\d+)\s+session\(s\)\s+since\s+startup/', $line, $m)) {
                $data['total_sessions'] = (int) $m[1];
            }
            if (preg_match('/(\d+)\s+session\(s\)\s+-\s+peak\s+(\d+)/', $line, $m)) {
                $data['current_sessions'] = (int) $m[1];
                $data['peak_sessions'] = (int) $m[2];
            }
            if (preg_match('/FreeSWITCH\s+([\d.]+)\s+/', $line, $m)) {
                $data['version'] = $m[1];
            }
        }

        return $data;
    }

    public function getActiveCalls(): array
    {
        $raw = $this->api('show calls as json');
        if (!$raw) {
            return [];
        }

        try {
            $json = json_decode(trim($raw), true, 512, JSON_THROW_ON_ERROR);
            return $json['rows'] ?? [];
        } catch (Exception) {
            return [];
        }
    }

    public function getActiveCallCount(): int
    {
        $raw = $this->api('show calls count');
        if (!$raw) {
            return 0;
        }
        preg_match('/(\d+)\s+total/', $raw, $m);
        return isset($m[1]) ? (int) $m[1] : 0;
    }

    public function getRegistrations(): array
    {
        $raw = $this->api('show registrations as json');
        if (!$raw) {
            return [];
        }

        try {
            $json = json_decode(trim($raw), true, 512, JSON_THROW_ON_ERROR);
            return $json['rows'] ?? [];
        } catch (Exception) {
            return [];
        }
    }

    public function getRegistrationCount(): int
    {
        $registrations = $this->getRegistrations();
        return count($registrations);
    }

    // ── Channel Control ───────────────────────────────────────────────────────

    public function killChannel(string $uuid): bool
    {
        $result = $this->api("uuid_kill {$uuid}");
        return $result !== false && str_contains((string) $result, '+OK');
    }

    public function transferCall(string $uuid, string $extension, string $context = 'default'): bool
    {
        $result = $this->api("uuid_transfer {$uuid} {$extension} XML {$context}");
        return $result !== false && str_contains((string) $result, '+OK');
    }

    public function holdCall(string $uuid): bool
    {
        $result = $this->api("uuid_hold {$uuid}");
        return $result !== false && str_contains((string) $result, '+OK');
    }

    public function resumeCall(string $uuid): bool
    {
        $result = $this->api("uuid_hold off {$uuid}");
        return $result !== false && str_contains((string) $result, '+OK');
    }

    public function sendDtmf(string $uuid, string $digits): bool
    {
        $result = $this->api("uuid_send_dtmf {$uuid} {$digits}");
        return $result !== false && str_contains((string) $result, '+OK');
    }

    // ── Configuration Reload ──────────────────────────────────────────────────

    public function reloadDialplan(string $context = ''): bool
    {
        $cmd = $context ? "reloadxml" : "reloadxml";
        $result = $this->api($cmd);
        return $result !== false;
    }

    public function reloadSipProfiles(): bool
    {
        $result = $this->api('sofia profile internal restart');
        return $result !== false;
    }

    public function reloadGateway(string $gateway): bool
    {
        $result = $this->api("sofia profile external killgw {$gateway}");
        $this->api("sofia profile external startgw {$gateway}");
        return $result !== false;
    }

    // ── Health ────────────────────────────────────────────────────────────────

    public function healthCheck(): array
    {
        $cached = Cache::get('freeswitch.health');
        if ($cached) {
            return $cached;
        }

        $connected = $this->connect();
        if (!$connected) {
            $health = [
                'status' => 'down',
                'connected' => false,
                'active_calls' => 0,
                'registrations' => 0,
                'checked_at' => now()->toIso8601String(),
            ];
            Cache::put('freeswitch.health', $health, 30);
            $this->disconnect();
            return $health;
        }

        $status = $this->getStatus();
        $health = [
            'status' => 'up',
            'connected' => true,
            'version' => $status['version'] ?? 'unknown',
            'active_calls' => $this->getActiveCallCount(),
            'registrations' => $this->getRegistrationCount(),
            'total_sessions' => $status['total_sessions'] ?? 0,
            'checked_at' => now()->toIso8601String(),
        ];

        Cache::put('freeswitch.health', $health, 30);
        $this->disconnect();
        return $health;
    }

    // ── Originate ─────────────────────────────────────────────────────────────

    public function originate(
        string $callString,
        string $extension,
        string $context = 'default',
        array $channelVars = []
    ): string|false {
        $vars = '';
        if (!empty($channelVars)) {
            $varList = implode(',', array_map(
                fn($k, $v) => "{$k}={$v}",
                array_keys($channelVars),
                array_values($channelVars)
            ));
            $vars = "[{$varList}]";
        }

        $cmd = "originate {$vars}{$callString} {$extension} XML {$context}";
        return $this->bgapi($cmd);
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function readResponse(): string
    {
        $response = '';
        $contentLength = 0;

        while (!feof($this->socket)) {
            $line = fgets($this->socket, 4096);
            if ($line === false) {
                break;
            }
            $response .= $line;

            if (preg_match('/^Content-Length:\s*(\d+)/i', $line, $m)) {
                $contentLength = (int) $m[1];
            }

            if (trim($line) === '' && $contentLength > 0) {
                $body = fread($this->socket, $contentLength);
                $response .= $body;
                break;
            }

            if (trim($line) === '' && $contentLength === 0) {
                break;
            }
        }

        return $response;
    }

    private function ensureConnected(): bool
    {
        if ($this->isConnected()) {
            return true;
        }
        return $this->connect();
    }
}
