<?php

namespace App\Enums;

enum PaymentForm: string
{
    case Cash = '1';
    case Credit = '2';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Contado',
            self::Credit => 'Crédito',
        };
    }
}
