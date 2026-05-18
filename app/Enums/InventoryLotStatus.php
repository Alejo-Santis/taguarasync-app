<?php

namespace App\Enums;

enum InventoryLotStatus: string
{
    case Available = 'available';
    case Quarantined = 'quarantined';
    case Expired = 'expired';
    case Depleted = 'depleted';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Quarantined => 'Cuarentena',
            self::Expired => 'Vencido',
            self::Depleted => 'Agotado',
        };
    }
}
