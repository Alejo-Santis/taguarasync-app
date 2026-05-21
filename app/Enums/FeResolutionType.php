<?php

namespace App\Enums;

enum FeResolutionType: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case DebitNote = 'debit_note';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Factura de venta',
            self::CreditNote => 'Nota crédito',
            self::DebitNote => 'Nota débito',
        };
    }
}
