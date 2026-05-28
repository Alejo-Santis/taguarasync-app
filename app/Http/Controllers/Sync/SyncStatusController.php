<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\SyncCheckpoint;
use App\Services\Sync\ConnectivityService;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncStatusController extends Controller
{
    public function __invoke(Request $request, ConnectivityService $connectivity): JsonResponse
    {
        $tenant = app(CurrentTenant::class)->get();

        if ($tenant && ! $tenant->offline_sync_enabled) {
            return response()->json([
                'app_mode' => 'cloud',
                'offline_sync_enabled' => false,
            ]);
        }

        $appMode = config('sync.app_mode', 'cloud');

        $checkpoint = SyncCheckpoint::query()
            ->where('tenant_id', '=', $request->user()->tenant_id)
            ->where('server_id', '=', config('sync.server_id'))
            ->first();

        return response()->json([
            'app_mode' => $appMode,
            'server_id' => config('sync.server_id'),
            'online' => $appMode === 'cloud' ? true : $connectivity->isOnline(),
            'checkpoint' => $checkpoint ? [
                'status' => $checkpoint->status,
                'last_sync_at' => $checkpoint->last_sync_at?->toISOString(),
                'last_successful_sync_at' => $checkpoint->last_successful_sync_at?->toISOString(),
                'error_detail' => $checkpoint->error_detail,
            ] : null,
        ]);
    }
}
