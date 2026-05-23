<?php

namespace App\Actions\Pos;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\SalePayment;
use App\Models\User;

class CloseCashSession
{
    /**
     * @param  array{actual_closing_amount: int, notes?: string|null}  $data
     */
    public function execute(CashSession $session, array $data, User $user): CashSession
    {
        $expected = $session->expectedClosingAmount();
        $actual = $data['actual_closing_amount'];

        $session->update([
            'closed_by_user_id' => $user->id,
            'actual_closing_amount' => $actual,
            'difference' => $actual - $expected,
            'status' => CashSessionStatus::Closed,
            'notes' => $data['notes'] ?? $session->notes,
            'closed_at' => now(),
        ]);

        $this->recordPaymentCounts($session->refresh(), $actual);

        return $session->refresh();
    }

    private function recordPaymentCounts(CashSession $session, int $actualCash): void
    {
        $totals = SalePayment::query()
            ->where('cash_session_id', $session->id)
            ->with('paymentMethod:id,name,affects_cash')
            ->selectRaw('payment_method_id, SUM(amount) as expected_amount, COUNT(*) as transactions_count')
            ->groupBy('payment_method_id')
            ->get();

        foreach ($totals as $total) {
            $isCash = $total->paymentMethod?->affects_cash === true;
            $expected = (int) $total->expected_amount;
            $counted = $isCash ? max(0, $actualCash - $session->opening_amount) : $expected;

            $session->paymentCounts()->updateOrCreate(
                ['payment_method_id' => $total->payment_method_id],
                [
                    'tenant_id' => $session->tenant_id,
                    'expected_amount' => $expected,
                    'counted_amount' => $counted,
                    'difference' => $counted - $expected,
                    'transactions_count' => (int) $total->transactions_count,
                ],
            );
        }
    }
}
