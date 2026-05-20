<?php

namespace App\Actions\Pos;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
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

        return $session->refresh();
    }
}
