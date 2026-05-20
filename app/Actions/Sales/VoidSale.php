<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\RegisterInventoryMovement;
use App\Enums\SaleStatus;
use App\Models\InventoryLot;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VoidSale
{
    public function execute(Sale $sale, User $user): Sale
    {
        if ($sale->status !== SaleStatus::Completed) {
            throw new InvalidArgumentException('Solo se pueden anular ventas con estado Completada.');
        }

        return DB::transaction(function () use ($sale, $user): Sale {
            $sale->load('items');

            foreach ($sale->items as $item) {
                if (! $item->inventory_lot_id) {
                    continue;
                }

                $lot = InventoryLot::lockForUpdate()->find($item->inventory_lot_id);

                if (! $lot) {
                    continue;
                }

                $minUnitsToReturn = $item->quantity * ($item->presentation?->minimum_unit_quantity ?? 1);

                app(RegisterInventoryMovement::class)->saleReturn($lot, $minUnitsToReturn, $user, [
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'reference_code' => $sale->document_number,
                    'notes' => "Anulacion {$sale->document_number}: {$item->description}",
                    'occurred_at' => now(),
                ]);
            }

            $sale->update(['status' => SaleStatus::Voided]);

            return $sale->refresh();
        });
    }
}
