<?php

return [
    'enabled' => env('FE_ENABLED', false),
    'api_url' => env('FE_API_URL', 'https://api.nextpyme.plus/api'),
    'ubl_prefix' => env('FE_UBL_PREFIX', '/ubl2.1'),
    'api_token' => env('FE_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Mapeo de códigos DIAN → IDs internos de la API Nextpyme
    | Verificado contra los CSVs oficiales de Nextpyme (public/csv/).
    |--------------------------------------------------------------------------
    */
    'map' => [
        // Tipos de documento de identificación (customer)
        // Fuente: public/csv/type_document_identifications.csv
        // Formato: nextpyme_id | name | dian_code
        'id_types' => [
            '11' => 1,   // Registro civil
            '12' => 2,   // Tarjeta de identidad
            '13' => 3,   // Cédula de ciudadanía (CC)
            '21' => 4,   // Tarjeta de extranjería
            '22' => 5,   // Cédula de extranjería (CE)
            '31' => 6,   // NIT
            '41' => 7,   // Pasaporte
            '42' => 8,   // Documento de identificación extranjero
            '47' => 11,  // PEP
            '48' => 12,  // PPT
            '50' => 9,   // NIT de otro país
            '91' => 10,  // NUIP
        ],

        // Tipos de organización
        // Fuente: public/csv/type_organizations.csv
        'org_types' => [
            '1' => 1,    // Persona jurídica
            '2' => 2,    // Persona natural
        ],

        // Tipos de régimen (IVA)
        // Fuente: public/csv/type_regimes.csv
        'regime_types' => [
            '48' => 1,   // Responsable de IVA
            '49' => 2,   // No responsable de IVA
        ],

        // type_liability_id por tipo de régimen del cliente
        // Fuente: public/csv/type_liabilities.csv
        // 7=Gran contribuyente(O-13), 117=No responsable(R-99-PN)
        'liabilities' => [
            'responsible' => 7,    // Régimen 48 — Responsable IVA
            'no_responsible' => 117,  // Régimen 49 — No responsable / Consumidor final
        ],

        // Métodos de pago Nextpyme
        // Fuente: public/csv/payment_methods.csv
        // CORREGIDO: 42=Consignación bancaria, 48=Tarjeta Crédito, 49=Tarjeta Débito
        'payment_methods' => [
            'cash' => 10,    // Efectivo
            'card' => 48,    // Tarjeta crédito (genérico para compatibilidad)
            'card_credit' => 48,    // Tarjeta crédito
            'card_debit' => 49,    // Tarjeta débito
            'transfer' => 47,    // Transferencia Débito Bancaria
            'deposit' => 42,    // Consignación bancaria
            'check' => 20,    // Cheque
            'other' => 10,    // Otro → Efectivo como fallback
        ],

        // Unidades de medida (Nextpyme ID)
        // Fuente: public/csv/unit_measures.csv → id=70 = "Unidad"
        'unit_measure_default' => 70,

        // ID de impuesto IVA en Nextpyme
        // Fuente: public/csv/taxes.csv → id=1 = IVA
        'iva_tax_id' => 1,

        // Tipos de documento para la factura
        // Fuente: public/csv/type_documents.csv
        'doc_types' => [
            'invoice' => 1,   // Factura de Venta Nacional
            'credit_note' => 4,   // Nota Crédito
            'debit_note' => 5,   // Nota Débito
        ],

        // type_item_identification_id (clasificación del ítem)
        // Fuente: public/csv/type_item_identifications.csv → 4=Estándar del contribuyente
        'item_identification_default' => 4,
    ],
];
