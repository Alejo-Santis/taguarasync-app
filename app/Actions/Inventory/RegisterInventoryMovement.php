<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryLotStatus;
use App\Enums\InventoryMovementType;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegisterInventoryMovement
{
    /**
     * @param  array{reference_type?: string|null, reference_id?: int|null, reference_code?: string|null, notes?: string|null, occurred_at?: mixed}  $context
     */
    public function execute(
        InventoryLot $lot,
        InventoryMovementType $type,
        int $quantityDelta,
        ?User $user = null,
        array $context = [],
    ): InventoryMovement {
        if ($quantityDelta === 0) {
            throw new InvalidArgumentException('Inventory movements require a non-zero quantity delta.');
        }

        return DB::transaction(function () use ($context, $lot, $quantityDelta, $type, $user): InventoryMovement {
            /** @var InventoryLot $lockedLot */
            $lockedLot = InventoryLot::query()
                ->whereKey($lot->id)
                ->lockForUpdate()
                ->firstOrFail();

            $quantityBefore = $lockedLot->current_quantity;
            $quantityAfter = $quantityBefore + $quantityDelta;

            if ($quantityAfter < 0) {
                throw new InvalidArgumentException('Inventory movement cannot leave the lot with negative stock.');
            }

            $lockedLot->forceFill([
                'current_quantity' => $quantityAfter,
                'status' => $quantityAfter === 0
                    ? InventoryLotStatus::Depleted
                    : InventoryLotStatus::Available,
            ])->save();

            return InventoryMovement::create([
                'tenant_id' => $lockedLot->tenant_id,
                'inventory_lot_id' => $lockedLot->id,
                'product_id' => $lockedLot->product_id,
                'product_presentation_id' => $lockedLot->product_presentation_id,
                'user_id' => $user?->id,
                'type' => $type,
                'quantity_delta' => $quantityDelta,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $lockedLot->unit_cost,
                'reference_type' => $context['reference_type'] ?? null,
                'reference_id' => $context['reference_id'] ?? null,
                'reference_code' => $context['reference_code'] ?? null,
                'notes' => $context['notes'] ?? null,
                'occurred_at' => $context['occurred_at'] ?? now(),
            ]);
        });
    }

    public function opening(InventoryLot $lot, int $quantity, ?User $user = null, array $context = []): InventoryMovement
    {
        return $this->execute($lot, InventoryMovementType::Opening, abs($quantity), $user, $context);
    }

    public function purchase(InventoryLot $lot, int $quantity, ?User $user = null, array $context = []): InventoryMovement
    {
        return $this->execute($lot, InventoryMovementType::Purchase, abs($quantity), $user, $context);
    }

    public function sale(InventoryLot $lot, int $quantity, ?User $user = null, array $context = []): InventoryMovement
    {
        return $this->execute($lot, InventoryMovementType::Sale, -abs($quantity), $user, $context);
    }

    public function saleReturn(InventoryLot $lot, int $quantity, ?User $user = null, array $context = []): InventoryMovement
    {
        return $this->execute($lot, InventoryMovementType::SaleReturn, abs($quantity), $user, $context);
    }
}
