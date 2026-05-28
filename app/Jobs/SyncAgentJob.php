<?php

namespace App\Jobs;

use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SyncCheckpoint;
use App\Services\Sync\ConnectivityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ciclo de sincronización bidireccional local → cloud.
 *
 * Diseñado para ejecutarse cada N segundos (config sync.agent.interval)
 * cuando el servidor está en modo 'local' y hay conectividad disponible.
 *
 * Flujo:
 *  1. Verificar conectividad — si offline, salir sin error.
 *  2. Push: enviar ventas e inventory_movements locales no sincronizados.
 *  3. Pull: descargar delta del cloud (datos maestros actualizados).
 *  4. Actualizar SyncCheckpoint del tenant.
 */
class SyncAgentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly int $tenantId) {}

    public function handle(ConnectivityService $connectivity): void
    {
        if (config('sync.app_mode') !== 'local') {
            return;
        }

        if ($connectivity->isOffline()) {
            Log::channel('daily')->info('[SyncAgent] offline — skip', ['tenant' => $this->tenantId]);

            return;
        }

        $checkpoint = SyncCheckpoint::firstOrCreate(
            ['tenant_id' => $this->tenantId, 'server_id' => config('sync.server_id')],
            ['status' => 'pending'],
        );

        $checkpoint->update(['status' => 'syncing', 'last_sync_at' => now()]);

        try {
            $this->push();
            $this->pull($checkpoint);

            $checkpoint->update(['status' => 'synced', 'last_successful_sync_at' => now(), 'error_detail' => null]);
        } catch (\Throwable $e) {
            Log::channel('daily')->error('[SyncAgent] error', ['tenant' => $this->tenantId, 'error' => $e->getMessage()]);
            $checkpoint->update(['status' => 'error', 'error_detail' => $e->getMessage()]);
        }
    }

    private function push(): void
    {
        $batchSize = (int) config('sync.agent.batch_size', 100);
        $cloudUrl = rtrim(config('sync.cloud_sync_url'), '/');
        $secret = config('sync.cloud_sync_secret');

        $sales = Sale::pendingSync()
            ->where('tenant_id', $this->tenantId)
            ->limit($batchSize)
            ->get();

        $movements = InventoryMovement::pendingSync()
            ->where('tenant_id', $this->tenantId)
            ->limit($batchSize)
            ->get();

        if ($sales->isEmpty() && $movements->isEmpty()) {
            return;
        }

        $response = Http::withToken($secret)
            ->timeout(15)
            ->post("{$cloudUrl}/api/sync/ingest", [
                'server_id' => config('sync.server_id'),
                'tenant_id' => $this->tenantId,
                'sales' => $sales->toArray(),
                'movements' => $movements->toArray(),
            ]);

        $response->throw();

        $now = now();

        DB::table('sales')
            ->whereIn('uuid', $sales->pluck('uuid'))
            ->update(['synced_at' => $now]);

        DB::table('inventory_movements')
            ->whereIn('uuid', $movements->pluck('uuid'))
            ->update(['synced_at' => $now]);
    }

    private function pull(SyncCheckpoint $checkpoint): void
    {
        $cloudUrl = rtrim(config('sync.cloud_sync_url'), '/');
        $secret = config('sync.cloud_sync_secret');

        $since = $checkpoint->last_successful_sync_at?->toISOString() ?? '1970-01-01T00:00:00Z';

        $response = Http::withToken($secret)
            ->timeout(15)
            ->get("{$cloudUrl}/api/sync/delta", [
                'tenant_id' => $this->tenantId,
                'since' => $since,
            ]);

        $response->throw();

        $delta = $response->json();

        // Aplicar actualizaciones de datos maestros (suppliers, products, price_lists, etc.)
        foreach ($delta['master_data'] ?? [] as $table => $rows) {
            foreach ($rows as $row) {
                DB::table($table)->upsert(
                    [$row],
                    ['uuid'],
                    array_keys($row),
                );
            }
        }
    }
}
