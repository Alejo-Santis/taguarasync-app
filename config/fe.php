<?php

return [
    'enabled' => env('FE_ENABLED', false),
    'api_url' => env('FE_API_URL', 'https://api.nextpyme.plus/api'),
    'api_token' => env('FE_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Mapeo de códigos DIAN → IDs internos de la API Nextpyme
    |--------------------------------------------------------------------------
    */
    'map' => [
        // Tipos de documento de identificación (customer)
        'id_types' => [
            '11' => 11,  // Registro civil
            '12' => 12,  // Tarjeta de identidad
            '13' => 3,   // Cédula de ciudadanía
            '21' => 4,   // Tarjeta de extranjería
            '22' => 2,   // Cédula de extranjería
            '31' => 6,   // NIT
            '41' => 7,   // Pasaporte
            '42' => 9,   // Doc. extranjero
            '47' => 8,   // PEP
            '91' => 3,   // NUIP → CC
        ],

        // Tipos de organización
        'org_types' => [
            '1' => 1,    // Persona jurídica
            '2' => 2,    // Persona natural
        ],

        // Tipos de régimen (IVA)
        'regime_types' => [
            '48' => 1,   // Responsable de IVA
            '49' => 2,   // No responsable de IVA
        ],

        // Métodos de pago Nextpyme
        'payment_methods' => [
            'cash' => 10,   // Efectivo
            'card' => 48,   // Tarjeta débito
            'transfer' => 47,   // Transferencia
            'other' => 1,    // Otro
        ],

        // Unidades de medida (default: unidad)
        'unit_measure_default' => 70,

        // ID de impuesto IVA en Nextpyme
        'iva_tax_id' => 1,

        // Tipos de documento para la factura
        'doc_types' => [
            'invoice' => 1,
            'credit_note' => 4,
            'debit_note' => 5,
        ],
    ],
];
