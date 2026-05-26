<?php

return [
    'notifications' => [
        'inventory_alerts_email' => env('TAGUARA_INVENTORY_ALERT_EMAIL', false),
        'fe_alerts_email' => env('TAGUARA_FE_ALERT_EMAIL', false),
        'operational_alerts_email' => env('TAGUARA_OPERATIONAL_ALERT_EMAIL', false),
        'inventory_expiry_days' => env('TAGUARA_INVENTORY_EXPIRY_DAYS', 90),
    ],
];
