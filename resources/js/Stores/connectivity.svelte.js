/**
 * Store de conectividad para la arquitectura híbrida offline.
 *
 * Solo activo cuando app_mode === 'local'.
 * En modo 'cloud' siempre reporta online=true sin polling.
 *
 * Estados posibles:
 *   online      — conectado al cloud
 *   offline     — sin conectividad
 *   degraded    — respuesta lenta / errores intermitentes
 *   reconnecting — acababa de estar offline, verificando reconexión
 */

const POLL_INTERVAL_MS = 5_000;

let online       = $state(true);
let appMode      = $state('cloud');
let serverId     = $state('cloud');
let syncStatus   = $state('pending');   // pending|syncing|synced|error
let lastSyncAt   = $state(null);
let syncError    = $state(null);
let initialized  = $state(false);

let _pollTimer = null;

function startPolling() {
    if (_pollTimer !== null) return;

    _pollTimer = setInterval(async () => {
        try {
            const res  = await fetch('/sync/status', { credentials: 'same-origin' });
            const data = await res.json();

            online    = data.online ?? true;
            syncStatus = data.checkpoint?.status ?? 'pending';
            lastSyncAt = data.checkpoint?.last_successful_sync_at ?? null;
            syncError  = data.checkpoint?.error_detail ?? null;
        } catch {
            online = false;
        }
    }, POLL_INTERVAL_MS);
}

function stopPolling() {
    if (_pollTimer !== null) {
        clearInterval(_pollTimer);
        _pollTimer = null;
    }
}

/**
 * Inicializar el store con los datos del servidor compartidos via Inertia.
 * Llamar una vez desde AppLayout al montar.
 *
 * @param {{ app_mode: string, server_id: string }} syncProps
 */
function init(syncProps) {
    if (initialized) return;

    appMode  = syncProps?.app_mode  ?? 'cloud';
    serverId = syncProps?.server_id ?? 'cloud';

    // En modo cloud no necesitamos polling: siempre online
    if (appMode === 'local') {
        startPolling();
    }

    initialized = true;
}

function destroy() {
    stopPolling();
    initialized = false;
}

export const connectivity = {
    get online()     { return online; },
    get offline()    { return !online; },
    get appMode()    { return appMode; },
    get serverId()   { return serverId; },
    get syncStatus() { return syncStatus; },
    get lastSyncAt() { return lastSyncAt; },
    get syncError()  { return syncError; },
    get isLocal()    { return appMode === 'local'; },
    init,
    destroy,
};
