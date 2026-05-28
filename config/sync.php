<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Modo de operación del servidor
    |--------------------------------------------------------------------------
    | cloud: servidor SaaS en la nube (proveedor de verdad)
    | local: servidor físico en la farmacia (modo offline-first)
    */
    'app_mode' => env('APP_MODE', 'cloud'),

    /*
    |--------------------------------------------------------------------------
    | Identificador único de este servidor
    |--------------------------------------------------------------------------
    | En cloud siempre es 'cloud'. En local, usar el UUID del tenant o un slug
    | único asignado al instalar el servidor (p.ej. 'local-farmacia-norte').
    */
    'server_id' => env('SERVER_ID', 'cloud'),

    /*
    |--------------------------------------------------------------------------
    | Conexión al servidor cloud (solo relevante en modo local)
    |--------------------------------------------------------------------------
    */
    'cloud_sync_url' => env('CLOUD_SYNC_URL', ''),
    'cloud_sync_secret' => env('CLOUD_SYNC_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | ConnectivityService
    |--------------------------------------------------------------------------
    | host:  destino del ping para verificar conectividad a internet
    | ttl:   segundos que dura el resultado en Redis antes de re-verificar
    | timeout: segundos máximos de espera del ping (TCP)
    */
    'connectivity' => [
        'ping_host' => env('SYNC_PING_HOST', '8.8.8.8'),
        'ping_port' => (int) env('SYNC_PING_PORT', 53),
        'ttl' => (int) env('SYNC_CONNECTIVITY_TTL', 3),
        'timeout' => (int) env('SYNC_PING_TIMEOUT', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | SyncAgent
    |--------------------------------------------------------------------------
    | interval: segundos entre cada ciclo de sync (usado por el job scheduler)
    | batch_size: máximo de registros enviados por ciclo de push
    */
    'agent' => [
        'interval' => (int) env('SYNC_INTERVAL', 5),
        'batch_size' => (int) env('SYNC_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contingencia FE
    |--------------------------------------------------------------------------
    | Si offline, la FE se encola hasta max_hours antes de rechazarse.
    */
    'fe_contingency_max_hours' => (int) env('SYNC_FE_CONTINGENCY_MAX_HOURS', 48),
];
