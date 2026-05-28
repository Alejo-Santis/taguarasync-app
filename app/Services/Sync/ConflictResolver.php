<?php

namespace App\Services\Sync;

use App\Models\SyncConflictLog;
use Illuminate\Support\Carbon;

/**
 * Resuelve conflictos cuando un mismo registro existe en local y en cloud.
 *
 * Estrategias:
 *  - append    → ventas e inventory_movements (append-only, sin conflicto real)
 *  - lww       → datos maestros: gana el updated_at más reciente
 *  - cloud_wins → precios regulados / config FE: siempre gana cloud
 */
class ConflictResolver
{
    /**
     * @param  array<string,mixed>  $local
     * @param  array<string,mixed>  $cloud
     * @return array<string,mixed> registro a persistir
     */
    public function resolve(string $table, string $uuid, array $local, array $cloud, int $tenantId): array
    {
        $strategy = $this->strategyFor($table);

        $resolved = match ($strategy) {
            'append' => $local,
            'cloud_wins' => $cloud,
            default => $this->resolveLww($local, $cloud),
        };

        SyncConflictLog::create([
            'tenant_id' => $tenantId,
            'table_name' => $table,
            'record_uuid' => $uuid,
            'strategy_applied' => $strategy,
            'local_data' => $local,
            'cloud_data' => $cloud,
            'resolved_data' => $resolved,
            'resolved_at' => now(),
        ]);

        return $resolved;
    }

    private function strategyFor(string $table): string
    {
        return match (true) {
            in_array($table, ['sales', 'inventory_movements']) => 'append',
            in_array($table, ['price_list_items']) => 'cloud_wins',
            default => 'lww',
        };
    }

    /**
     * Last-Write-Wins: gana el registro con updated_at más reciente.
     *
     * @param  array<string,mixed>  $local
     * @param  array<string,mixed>  $cloud
     * @return array<string,mixed>
     */
    private function resolveLww(array $local, array $cloud): array
    {
        $localTs = isset($local['updated_at']) ? Carbon::parse($local['updated_at']) : Carbon::minValue();
        $cloudTs = isset($cloud['updated_at']) ? Carbon::parse($cloud['updated_at']) : Carbon::minValue();

        return $localTs->gte($cloudTs) ? $local : $cloud;
    }
}
