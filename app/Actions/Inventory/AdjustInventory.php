<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdjustInventory
{
    /**
     * @param  array{
     *     inventory_lot_id: int,
     *     type: string,
     *     quantity: int,
     *     reason: string
     * }  $data
     */
    public function execute(array $data, User $user): InventoryMovement
    {
        $type = match ($data['type']) {
            'in' => InventoryMovementType::AdjustmentIn,
            'out' => InventoryMovementType::AdjustmentOut,
            default => throw new InvalidArgumentException('Tipo de ajuste invalido. Use "in" o "out".'),
        };

        $lot = InventoryLot::find($data['inventory_lot_id']);

        if (! $lot) {
            throw new InvalidArgumentException('Lote de inventario no encontrado.');
        }

        return DB::transaction(function () use ($data, $lot, $type, $user): InventoryMovement {
            $register = app(RegisterInventoryMovement::class);

            $quantity = abs($data['quantity']);

            if ($type === InventoryMovementType::AdjustmentOut && $lot->current_quantity < $quantity) {
                throw new InvalidArgumentException(
                    "Stock insuficiente. El lote tiene {$lot->current_quantity} unidades."
                );
            }

            $delta = $type === InventoryMovementType::AdjustmentIn ? $quantity : -$quantity;

            return $register->execute($lot, $type, $delta, $user, [
                'notes' => $data['reason'],
                'occurred_at' => now(),
            ]);
        });
    }
}
