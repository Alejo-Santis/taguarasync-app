<?php

namespace App\Enums;

enum PurchaseRadianStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Rejected = 'rejected';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente RADIAN',
            self::Validated => 'Validada',
            self::Rejected => 'Rechazada',
            self::Error => 'Error validacion',
        };
    }
}
