<?php

namespace App\Services\Install;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class Installation
{
    public static function path(): string
    {
        return storage_path('installation-settings');
    }

    public static function finished(): bool
    {
        return is_file(self::path()) || is_file(storage_path('installed')) || config('nexvary.installed', false);
    }

    public static function secureTransport(Request $request): bool
    {
        return $request->isSecure() || (!config('session.secure')
            && in_array($request->getHost(), ['localhost', '127.0.0.1'], true)
            && in_array($request->ip(), ['127.0.0.1', '::1'], true));
    }

    public static function settings(array $database, array $redis, string $locale): array
    {
        return [
            'app.locale' => $locale,
            'database.default' => 'pgsql',
            'database.connections.pgsql' => self::database($database),
            'database.redis.options.prefix' => 'nexvary_cx_'.bin2hex(random_bytes(12)).'_',
            'database.redis.default' => self::redis($redis, 0),
            'database.redis.cache' => self::redis($redis, 1),
            'cache.default' => 'redis',
            'session.driver' => 'redis',
            'queue.default' => 'redis',
        ];
    }

    public static function database(array $values): array
    {
        return array_replace(config('database.connections.pgsql'), [
            'url' => null,
            'host' => $values['host'],
            'port' => $values['port'],
            'database' => $values['name'],
            'username' => $values['user'],
            'password' => $values['password'] ?? '',
        ]);
    }

    public static function redis(array $values, int $database): array
    {
        return [
            'scheme' => 'tcp',
            'host' => $values['host'],
            'port' => $values['port'],
            'username' => $values['user'] ?? null,
            'password' => $values['password'] ?? null,
            'database' => $database,
            'timeout' => 3,
            'read_write_timeout' => 3,
        ];
    }

    public static function load(): void
    {
        if (is_file(self::path())) {
            config(Crypt::decrypt(file_get_contents(self::path())));
            app()->setLocale(config('app.locale'));
        } elseif (!self::finished() && !app()->environment('testing')) {
            // Allow the installer to boot before the customer connects Redis.
            config(['cache.default' => 'file', 'session.driver' => 'file']);
        }
    }
}
