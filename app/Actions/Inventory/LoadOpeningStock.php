<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryLotStatus;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LoadOpeningStock
{
    /**
     * @param  array{
     *     tenant_id: int,
     *     items: array<int, array{
     *         product_id: int,
     *         product_presentation_id?: int|null,
     *         lot_number: string,
     *         expires_on?: string|null,
     *         quantity: int,
     *         unit_cost: int
     *     }>
     * }  $data
     * @return array<int, InventoryMovement>
     */
    public function execute(array $data, User $user): array
    {
        return DB::transaction(function () use ($data, $user): array {
            $movements = [];

            foreach ($data['items'] as $item) {
                $lot = InventoryLot::firstOrCreate(
                    [
                        'tenant_id' => $data['tenant_id'],
                        'product_id' => $item['product_id'],
                        'lot_number' => $item['lot_number'],
                    ],
                    [
                        'product_presentation_id' => $item['product_presentation_id'] ?? null,
                        'expires_on' => $item['expires_on'] ?? null,
                        'initial_quantity' => $item['quantity'],
                        'current_quantity' => 0,
                        'unit_cost' => $item['unit_cost'],
                        'status' => InventoryLotStatus::Available,
                        'received_at' => now(),
                    ]
                );

                if (! $lot->wasRecentlyCreated) {
                    $lot->increment('initial_quantity', $item['quantity']);
                }

                $movements[] = app(RegisterInventoryMovement::class)->opening($lot, $item['quantity'], $user, [
                    'notes' => 'Carga inicial de inventario',
                    'occurred_at' => now(),
                ]);
            }

            return $movements;
        });
    }
}
