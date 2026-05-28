<?php

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\Tenant;
use App\Services\Sync\ConflictResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/sync/ingest
 *
 * Recibe ventas e inventory_movements del servidor local y los inserta
 * en cloud aplicando resolución de conflictos.
 *
 * Autenticación: Bearer token = CLOUD_SYNC_SECRET.
 */
class SyncIngestController extends Controller
{
    public function __invoke(Request $request, ConflictResolver $resolver): JsonResponse
    {
        $data = $request->validate([
            'server_id' => ['required', 'string', 'max:32'],
            'tenant_id' => ['required', 'integer'],
            'sales' => ['array'],
            'movements' => ['array'],
        ]);

        $tenantId = (int) $data['tenant_id'];
        $serverId = $data['server_id'];

        $ingested = ['sales' => 0, 'movements' => 0, 'conflicts' => 0];

        DB::transaction(function () use ($data, $tenantId, $serverId, $resolver, &$ingested) {
            foreach ($data['sales'] ?? [] as $row) {
                $uuid = $row['uuid'] ?? null;
                $existing = $uuid ? Sale::where('uuid', $uuid)->where('tenant_id', $tenantId)->first() : null;

                if ($existing) {
                    $resolved = $resolver->resolve('sales', $uuid, $row, $existing->toArray(), $tenantId);
                    $existing->fill($resolved)->save();
                    $ingested['conflicts']++;
                } else {
                    $row['server_id'] = $serverId;
                    $row['synced_at'] = now();
                    Sale::create($row);
                    $ingested['sales']++;
                }
            }

            foreach ($data['movements'] ?? [] as $row) {
                $uuid = $row['uuid'] ?? null;
                $existing = $uuid ? InventoryMovement::where('uuid', $uuid)->where('tenant_id', $tenantId)->first() : null;

                if (! $existing) {
                    $row['server_id'] = $serverId;
                    $row['synced_at'] = now();
                    InventoryMovement::create($row);
                    $ingested['movements']++;
                }
                // Los movimientos de inventario son append-only: si ya existe, no se toca.
            }
        });

        Log::channel('daily')->info('[SyncIngest] ingested', ['tenant' => $tenantId, 'server' => $serverId] + $ingested);

        return response()->json(['ok' => true, 'ingested' => $ingested]);
    }
}
