<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Sent => 'Enviada',
            self::PartiallyReceived => 'Parcialmente recibida',
            self::Received => 'Recibida',
            self::Cancelled => 'Cancelada',
        };
    }
}
