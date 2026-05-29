<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryLotStatus;
use App\Enums\InventoryMovementType;
use App\Models\InventoryLot;
use App\Models\InventoryTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConfirmInventoryTransfer
{
    public function __construct(private RegisterInventoryMovement $registerMovement) {}

    /**
     * @throws InvalidArgumentException
     */
    public function execute(InventoryTransfer $transfer, User $user): void
    {
        if ($transfer->status->value !== 'draft') {
            throw new InvalidArgumentException('Solo se pueden confirmar traslados en estado borrador.');
        }

        DB::transaction(function () use ($transfer, $user): void {
            $transfer->load('items.lot');

            foreach ($transfer->items as $item) {
                $lot = $item->lot;

                // Validar stock disponible en sucursal origen
                if ($lot->current_quantity < $item->quantity) {
                    throw new InvalidArgumentException(
                        "Lote {$lot->lot_number}: stock insuficiente ({$lot->current_quantity} disponibles, {$item->quantity} solicitados)."
                    );
                }

                // Salida en sucursal origen
                $this->registerMovement->execute(
                    $lot,
                    InventoryMovementType::TransferOut,
                    -$item->quantity,
                    $user,
                    ['reference_type' => 'inventory_transfer', 'reference_id' => $transfer->id, 'reference_code' => $transfer->uuid]
                );

                // Buscar lote con mismo número en sucursal destino
                $destinationLot = InventoryLot::where('tenant_id', $transfer->tenant_id)
                    ->where('branch_id', $transfer->to_branch_id)
                    ->where('product_id', $lot->product_id)
                    ->where('lot_number', $lot->lot_number)
                    ->first();

                if (! $destinationLot) {
                    // Crear lote nuevo en sucursal destino con cantidad transferida
                    $destinationLot = InventoryLot::create([
                        'tenant_id' => $transfer->tenant_id,
                        'branch_id' => $transfer->to_branch_id,
                        'product_id' => $lot->product_id,
                        'product_presentation_id' => $lot->product_presentation_id,
                        'lot_number' => $lot->lot_number,
                        'expires_on' => $lot->expires_on,
                        'initial_quantity' => 0,
                        'current_quantity' => 0,
                        'unit_cost' => $lot->unit_cost,
                        'status' => InventoryLotStatus::Available,
                        'received_at' => now(),
                    ]);
                }

                // Entrada en sucursal destino
                $this->registerMovement->execute(
                    $destinationLot,
                    InventoryMovementType::TransferIn,
                    $item->quantity,
                    $user,
                    ['reference_type' => 'inventory_transfer', 'reference_id' => $transfer->id, 'reference_code' => $transfer->uuid]
                );
            }

            $transfer->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);
        });
    }
}
