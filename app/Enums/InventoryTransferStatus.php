<?php

namespace App\Enums;

enum InventoryTransferStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Confirmed => 'Confirmado',
            self::Cancelled => 'Cancelado',
        };
    }
}
