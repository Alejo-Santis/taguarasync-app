<?php

namespace App\Actions\Pos;

use App\Enums\PaymentMethod;
use App\Models\CashSession;

class TransformCashSessionSummary
{
    /**
     * Shapes a CashSession into the flat structure expected by both the
     * cash-session report screen and the QZ Tray Z-report builder
     * (buildCashSessionEscPos in resources/js/Services/QzPrinter.js).
     *
     * @return array<string, mixed>
     */
    public function execute(CashSession $session): array
    {
        $session->loadMissing(['register:id,name,code', 'user:id,name', 'closedBy:id,name']);

        $cashSales = (int) ($session->cash_sales_total ?? $session->cashSalesTotal());
        $cardSales = (int) ($session->card_sales_total ?? $session->sales()->where('payment_method', PaymentMethod::Card->value)->sum('total'));
        $transferSales = (int) ($session->transfer_sales_total ?? $session->sales()->where('payment_method', PaymentMethod::Transfer->value)->sum('total'));
        $expected = $session->opening_amount + $cashSales;

        return [
            'uuid' => $session->uuid,
            'status' => [
                'value' => $session->status->value,
                'label' => $session->status->label(),
            ],
            'register' => [
                'name' => $session->register?->name,
                'code' => $session->register?->code,
            ],
            'cashier' => $session->user?->name,
            'closed_by' => $session->closedBy?->name,
            'opening_amount' => $session->opening_amount,
            'expected_closing' => $expected,
            'actual_closing_amount' => $session->actual_closing_amount,
            'difference' => $session->difference,
            'sales_count' => (int) ($session->sales_count ?? $session->sales()->count()),
            'sales_total' => (int) ($session->sales_total ?? $session->salesTotal()),
            'cash_sales_total' => $cashSales,
            'card_sales_total' => $cardSales,
            'transfer_sales_total' => $transferSales,
            'notes' => $session->notes,
            'opened_at' => $session->opened_at?->format('d/m/Y H:i'),
            'closed_at' => $session->closed_at?->format('d/m/Y H:i'),
        ];
    }
}
