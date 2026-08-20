<?php

namespace App\Console\Commands;

use App\Jobs\SyncAgentJob;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Proceso de larga duración para el servidor local: despacha SyncAgentJob
 * cada `sync.agent.interval` segundos para cada tenant con sync offline
 * habilitado. Pensado para correr como proceso supervisado por Docker
 * (`restart: unless-stopped`) dentro del servidor local de cada farmacia.
 *
 * El Scheduler estándar de Laravel no baja de 1 minuto de granularidad, por
 * eso el ciclo de sync (cada 5s por defecto) vive en este loop dedicado en
 * vez de en routes/console.php.
 */
#[Signature('sync:agent {--once : Ejecutar un solo ciclo y salir (para pruebas y despliegue manual)}')]
#[Description('Loop continuo que despacha el ciclo de sincronización cada N segundos en el servidor local.')]
class SyncAgentCommand extends Command
{
    private bool $shouldStop = false;

    public function handle(): int
    {
        if (config('sync.app_mode') !== 'local') {
            $this->error('sync:agent solo aplica en servidores en modo local (APP_MODE=local).');

            return self::FAILURE;
        }

        $this->trap([SIGTERM, SIGINT], function (): void {
            $this->shouldStop = true;
        });

        $interval = (int) config('sync.agent.interval', 5);
        $this->info('Sync agent iniciado — server_id='.config('sync.server_id')." intervalo={$interval}s.");

        while (! $this->shouldStop) {
            $this->runCycle();

            if ($this->option('once')) {
                break;
            }

            sleep($interval);
        }

        $this->info('Sync agent detenido.');

        return self::SUCCESS;
    }

    private function runCycle(): void
    {
        try {
            $tenants = Tenant::where('offline_sync_enabled', true)->get();

            foreach ($tenants as $tenant) {
                SyncAgentJob::dispatch($tenant->id);
            }
        } catch (Throwable $e) {
            Log::channel('daily')->error('[SyncAgentCommand] cycle failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
