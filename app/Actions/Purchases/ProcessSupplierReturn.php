<?php

namespace App\Actions\Purchases;

use App\Actions\Inventory\RegisterInventoryMovement;
use App\Enums\InventoryLotStatus;
use App\Enums\SupplierReturnStatus;
use App\Models\InventoryLot;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProcessSupplierReturn
{
    /**
     * @param  array{
     *     tenant_id: int,
     *     supplier_id: int,
     *     purchase_receipt_id?: int|null,
     *     document_number: string,
     *     return_date: string,
     *     reason?: string|null,
     *     notes?: string|null,
     *     items: array<int, array{
     *         product_id: int,
     *         inventory_lot_id: int,
     *         description: string,
     *         lot_number: string,
     *         quantity: int,
     *         unit_cost: int,
     *         tax_rate?: int|float|string|null
     *     }>
     * }  $data
     */
    public function execute(array $data, ?User $user = null): SupplierReturn
    {
        return DB::transaction(function () use ($data, $user): SupplierReturn {
            $totals = $this->totals($data['items']);

            $supplierReturn = SupplierReturn::create([
                'tenant_id' => $data['tenant_id'],
                'supplier_id' => $data['supplier_id'],
                'purchase_receipt_id' => $data['purchase_receipt_id'] ?? null,
                'user_id' => $user?->id,
                'document_number' => $data['document_number'],
                'return_date' => $data['return_date'],
                'reason' => $data['reason'] ?? null,
                'status' => SupplierReturnStatus::Confirmed,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $lot = $this->findLot($item['inventory_lot_id'], $data['tenant_id'], $item['quantity']);
                $line = $this->lineTotals($item);

                $returnItem = $supplierReturn->items()->create([
                    'tenant_id' => $data['tenant_id'],
                    'product_id' => $item['product_id'],
                    'inventory_lot_id' => $lot->id,
                    'description' => $item['description'],
                    'lot_number' => $item['lot_number'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'line_subtotal' => $line['subtotal'],
                    'line_tax' => $line['tax'],
                    'line_total' => $line['total'],
                ]);

                $movement = app(RegisterInventoryMovement::class)->purchaseReturn($lot, $item['quantity'], $user, [
                    'reference_type' => SupplierReturn::class,
                    'reference_id' => $supplierReturn->id,
                    'reference_code' => $supplierReturn->document_number,
                    'notes' => "Devolucion {$supplierReturn->document_number}: {$item['description']}",
                    'occurred_at' => now(),
                ]);

                $returnItem->forceFill(['inventory_movement_id' => $movement->id])->save();
            }

            return $supplierReturn->load('items.lot');
        });
    }

    private function findLot(int $lotId, int $tenantId, int $quantity): InventoryLot
    {
        $lot = InventoryLot::query()
            ->where('id', $lotId)
            ->where('tenant_id', $tenantId)
            ->where('status', InventoryLotStatus::Available)
            ->where('current_quantity', '>=', $quantity)
            ->first();

        if (! $lot) {
            throw new InvalidArgumentException('Stock insuficiente en el lote para realizar la devolución.');
        }

        return $lot;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{subtotal: int, tax: int, total: int}
     */
    private function lineTotals(array $item): array
    {
        $subtotal = (int) $item['quantity'] * (int) $item['unit_cost'];
        $tax = (int) round($subtotal * (((float) ($item['tax_rate'] ?? 0)) / 100));

        return ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $subtotal + $tax];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: int, tax_total: int, total: int}
     */
    private function totals(array $items): array
    {
        return collect($items)->reduce(function (array $carry, array $item): array {
            $line = $this->lineTotals($item);

            return [
                'subtotal' => $carry['subtotal'] + $line['subtotal'],
                'tax_total' => $carry['tax_total'] + $line['tax'],
                'total' => $carry['total'] + $line['total'],
            ];
        }, ['subtotal' => 0, 'tax_total' => 0, 'total' => 0]);
    }
}
