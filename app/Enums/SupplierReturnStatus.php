<?php

namespace App\Enums;

enum SupplierReturnStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Confirmed => 'Confirmada',
            self::Voided => 'Anulada',
        };
    }
}
