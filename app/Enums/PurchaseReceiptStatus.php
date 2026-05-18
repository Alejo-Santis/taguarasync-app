<?php

namespace App\Enums;

enum PurchaseReceiptStatus: string
{
    case Draft = 'draft';
    case Received = 'received';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Received => 'Recibida',
            self::Voided => 'Anulada',
        };
    }
}
