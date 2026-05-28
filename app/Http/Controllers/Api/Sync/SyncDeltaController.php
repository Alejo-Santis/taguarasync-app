<?php

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/sync/delta?tenant_id=X&since=ISO8601
 *
 * Devuelve los datos maestros actualizados desde `since`.
 * El servidor local aplica estos cambios en su base de datos.
 *
 * Autenticación: Bearer token = CLOUD_SYNC_SECRET.
 */
class SyncDeltaController extends Controller
{
    /** Tablas de datos maestros que se sincronizan al local. */
    private const MASTER_TABLES = [
        'suppliers',
        'products',
        'product_presentations',
        'customers',
        'price_lists',
        'price_list_items',
        'categories',
        'laboratories',
        'active_ingredients',
        'measurement_units',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => ['required', 'integer'],
            'since' => ['required', 'string'],
        ]);

        $tenantId = (int) $request->input('tenant_id');

        $tenant = Tenant::find($tenantId);
        if (! $tenant || ! $tenant->offline_sync_enabled) {
            abort(403, 'Offline sync is not available for this tenant\'s plan.');
        }

        $since = Carbon::parse($request->input('since'));

        $masterData = [];

        foreach (self::MASTER_TABLES as $table) {
            $rows = DB::table($table)
                ->where('tenant_id', $tenantId)
                ->where('updated_at', '>', $since)
                ->get()
                ->toArray();

            if (! empty($rows)) {
                $masterData[$table] = array_map(fn ($row) => (array) $row, $rows);
            }
        }

        return response()->json([
            'ok' => true,
            'since' => $since->toISOString(),
            'server_time' => now()->toISOString(),
            'master_data' => $masterData,
        ]);
    }
}
