<?php

namespace App\Actions\Pos;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OpenCashSession
{
    /**
     * @param  array{cash_register_id: int, opening_amount: int, notes?: string|null}  $data
     */
    public function execute(array $data, User $user): CashSession
    {
        $alreadyOpen = CashSession::where('user_id', $user->id)
            ->where('status', CashSessionStatus::Open)
            ->exists();

        if ($alreadyOpen) {
            throw ValidationException::withMessages([
                'cash_register_id' => 'Ya tienes un turno abierto. Cierra el turno actual antes de abrir uno nuevo.',
            ]);
        }

        $registerOpen = CashSession::where('cash_register_id', $data['cash_register_id'])
            ->where('status', CashSessionStatus::Open)
            ->exists();

        if ($registerOpen) {
            throw ValidationException::withMessages([
                'cash_register_id' => 'Esta caja ya tiene un turno abierto en este momento.',
            ]);
        }

        return CashSession::create([
            'tenant_id' => $user->tenant_id,
            'cash_register_id' => $data['cash_register_id'],
            'user_id' => $user->id,
            'opening_amount' => $data['opening_amount'],
            'status' => CashSessionStatus::Open,
            'notes' => $data['notes'] ?? null,
            'opened_at' => now(),
        ]);
    }
}
