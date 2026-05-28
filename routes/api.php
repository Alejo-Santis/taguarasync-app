<?php

use App\Http\Controllers\Api\Sync\SyncDeltaController;
use App\Http\Controllers\Api\Sync\SyncIngestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sync API — Arquitectura Híbrida Offline (Fase 7)
|--------------------------------------------------------------------------
| Solo accesible con el Bearer token configurado en CLOUD_SYNC_SECRET.
| El servidor local consume estos endpoints cuando recupera conectividad.
*/
Route::middleware('sync.secret')->prefix('sync')->group(function () {
    Route::post('ingest', SyncIngestController::class)->name('api.sync.ingest');
    Route::get('delta', SyncDeltaController::class)->name('api.sync.delta');
});
