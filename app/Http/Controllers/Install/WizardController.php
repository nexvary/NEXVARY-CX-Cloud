<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Install\Installation;
use App\Services\PBX\FreeSwitchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Predis\Client;
use Spatie\Permission\Models\Role;

class WizardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Install/Wizard', [
            'setupEnabled' => strlen((string) config('nexvary.install_secret')) >= 32,
            'secureAccess' => Installation::secureTransport($request),
            'database' => [
                'host' => config('database.connections.pgsql.host'),
                'port' => (string) config('database.connections.pgsql.port'),
                'name' => config('database.connections.pgsql.database'),
                'user' => config('database.connections.pgsql.username'),
            ],
            'redis' => [
                'host' => config('database.redis.default.host'),
                'port' => (string) config('database.redis.default.port'),
                'user' => config('database.redis.default.username') ?? '',
            ],
        ]);
    }

    public function readiness(Request $request): JsonResponse
    {
        $checks = $this->requirements($request);

        return response()->json(['ok' => !in_array(false, $checks, true), 'checks' => $checks]);
    }

    public function connection(Request $request, string $service): JsonResponse
    {
        if ($service === 'freeswitch') {
            $pbx = app(FreeSwitchService::class);
            try {
                return response()->json(['ok' => $pbx->probe()]);
            } finally {
                $pbx->disconnect();
            }
        }

        $data = $request->validate($this->connectionRules($service));
        $ok = $service === 'database'
            ? $this->databaseReady($data['database'])
            : $this->redisReady($data['redis']);

        return response()->json(['ok' => $ok]);
    }

    public function complete(Request $request): JsonResponse
    {
        $data = $request->validate([
            ...$this->connectionRules('database'),
            ...$this->connectionRules('redis'),
            'locale' => ['required', 'in:en,ar'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255'],
            'admin.password' => ['required', 'string', 'min:12', 'confirmed', function ($field, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('The password exceeds 72 bytes.');
                }
            }],
        ]);

        if (in_array(false, $this->requirements($request), true)) {
            return response()->json(['ok' => false, 'problem' => 'requirements'], 409);
        }

        $lock = fopen(storage_path('installation.lock'), 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                fclose($lock);
            }

            return response()->json(['ok' => false, 'problem' => 'busy'], 409);
        }

        $temporary = null;
        try {
            if (Installation::finished()) {
                abort(404);
            }
            if (!$this->databaseReady($data['database'])) {
                return response()->json(['ok' => false, 'problem' => 'database'], 422);
            }
            if (!$this->redisReady($data['redis'])) {
                return response()->json(['ok' => false, 'problem' => 'redis'], 422);
            }

            $settings = Installation::settings($data['database'], $data['redis'], $data['locale']);
            $temporary = tempnam(storage_path(), 'setup-');
            if ($temporary === false || !chmod($temporary, 0600) ||
                file_put_contents($temporary, Crypt::encrypt($settings)) === false) {
                throw new \RuntimeException('Installation settings could not be written.');
            }

            config([
                'database.default' => 'pgsql',
                'database.connections.pgsql' => $settings['database.connections.pgsql'],
            ]);
            DB::purge('pgsql');

            DB::transaction(function () use ($data) {
                if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                    throw new \RuntimeException('Installation migrations failed.');
                }

                $admin = $data['admin'];
                $email = strtolower($admin['email']);
                $existing = User::query()->first();
                if ($existing) {
                    // Recover the same installation if the database commit succeeded
                    // but publication of the installation settings was interrupted.
                    if (User::count() !== 1 || !$existing->hasRole('superadmin') ||
                        $existing->email !== $email || !Hash::check($admin['password'], $existing->password)) {
                        throw new \RuntimeException('The database already contains accounts.');
                    }
                    return;
                }

                $user = User::create([
                    'name' => $admin['name'],
                    'email' => $email,
                    'password' => $admin['password'],
                    'locale' => $data['locale'],
                ]);
                Role::findOrCreate('superadmin', 'web');
                $user->assignRole('superadmin');
            });

            if (!rename($temporary, Installation::path())) {
                throw new \RuntimeException('Installation settings could not be published.');
            }
            $temporary = null;

            return response()->json(['ok' => true, 'login' => route('login', absolute: false)]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            throw $error;
        } catch (\Throwable $error) {
            // Connection errors may contain credentials or raw SQL parameters.
            logger()->warning('Installation could not finish', ['exception' => $error::class]);

            return response()->json(['ok' => false, 'problem' => 'installation'], 500);
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function requirements(Request $request): array
    {
        return [
            'php' => PHP_VERSION_ID >= 80300 && extension_loaded('pdo_pgsql') && extension_loaded('sockets'),
            'storage' => is_writable(storage_path()) && is_writable(storage_path('framework/sessions')),
            'assets' => is_file(public_path('build/manifest.json')),
            'https' => Installation::secureTransport($request),
        ];
    }

    private function connectionRules(string $service): array
    {
        $rules = [
            $service => ['required', $service === 'database' ? 'array:host,port,name,user,password' : 'array:host,port,user,password'],
            "$service.host" => ['required', 'string', 'max:253', 'regex:/^[a-z0-9][a-z0-9.-]*$/iD'],
            "$service.port" => ['required', 'integer', 'between:1,65535'],
            "$service.password" => ['nullable', 'string', 'max:255'],
        ];

        return $rules + ($service === 'database' ? [
            'database.name' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9_.-]+$/iD'],
            'database.user' => ['required', 'string', 'max:63'],
        ] : ['redis.user' => ['nullable', 'string', 'max:64']]);
    }

    private function databaseReady(array $data): bool
    {
        config(['database.connections.setup-check' => Installation::database($data)]);
        try {
            return (int) DB::connection('setup-check')->scalar('select 1') === 1;
        } catch (\Throwable) {
            return false;
        } finally {
            DB::purge('setup-check');
        }
    }

    private function redisReady(array $data): bool
    {
        try {
            foreach ([0, 1] as $database) {
                $client = new Client(Installation::redis($data, $database));
                try {
                    $key = 'nexvary:setup:'.bin2hex(random_bytes(16));
                    $client->setex($key, 30, 'ready');
                    if ($client->get($key) !== 'ready') {
                        return false;
                    }
                    $client->del([$key]);
                } finally {
                    $client->disconnect();
                }
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
