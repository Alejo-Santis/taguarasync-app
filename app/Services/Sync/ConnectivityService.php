<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\Cache;

class ConnectivityService
{
    private const CACHE_KEY = 'sync:connectivity:online';

    public function isOnline(): bool
    {
        $ttl = (int) config('sync.connectivity.ttl', 3);
        $host = config('sync.connectivity.ping_host', '8.8.8.8');
        $port = (int) config('sync.connectivity.ping_port', 53);

        return Cache::remember(self::CACHE_KEY, $ttl, fn () => $this->ping($host, $port));
    }

    public function isOffline(): bool
    {
        return ! $this->isOnline();
    }

    /** Invalida el caché para forzar un re-check en la próxima llamada. */
    public function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function ping(string $host, int $port): bool
    {
        $timeout = (int) config('sync.connectivity.timeout', 2);

        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
