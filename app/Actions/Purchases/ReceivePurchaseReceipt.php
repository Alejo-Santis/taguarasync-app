<?php

namespace App\Actions\Purchases;

use App\Actions\Inventory\RegisterInventoryMovement;
use App\Enums\InventoryLotStatus;
use App\Enums\PurchaseReceiptStatus;
use App\Models\InventoryLot;
use App\Models\PurchaseReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReceivePurchaseReceipt
{
    /**
     * @param  array{
     *     tenant_id: int,
     *     supplier_id: int,
     *     document_number: string,
     *     document_date?: mixed,
     *     received_at?: mixed,
     *     source_file_path?: string|null,
     *     notes?: string|null,
     *     items: array<int, array{
     *         product_id: int,
     *         product_presentation_id?: int|null,
     *         description: string,
     *         lot_number: string,
     *         expires_on?: mixed,
     *         quantity: int,
     *         unit_cost: int,
     *         tax_rate?: int|float|string|null
     *     }>
     * }  $data
     */
    public function execute(array $data, ?User $user = null): PurchaseReceipt
    {
        return DB::transaction(function () use ($data, $user): PurchaseReceipt {
            $totals = $this->totals($data['items']);

            $receipt = PurchaseReceipt::create([
                'tenant_id' => $data['tenant_id'],
                'supplier_id' => $data['supplier_id'],
                'user_id' => $user?->id,
                'document_number' => $data['document_number'],
                'document_date' => $data['document_date'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'status' => PurchaseReceiptStatus::Received,
                'source_file_path' => $data['source_file_path'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $line = $this->lineTotals($item);
                $lot = $this->findOrCreateLot($data['tenant_id'], $item);

                $receiptItem = $receipt->items()->create([
                    'tenant_id' => $data['tenant_id'],
                    'product_id' => $item['product_id'],
                    'product_presentation_id' => $item['product_presentation_id'] ?? null,
                    'inventory_lot_id' => $lot->id,
                    'description' => $item['description'],
                    'lot_number' => $item['lot_number'],
                    'expires_on' => $item['expires_on'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'line_subtotal' => $line['subtotal'],
                    'line_tax' => $line['tax'],
                    'line_total' => $line['total'],
                ]);

                $movement = app(RegisterInventoryMovement::class)->purchase($lot, $item['quantity'], $user, [
                    'reference_type' => PurchaseReceipt::class,
                    'reference_id' => $receipt->id,
                    'reference_code' => $receipt->document_number,
                    'notes' => "Recepcion {$receipt->document_number}: {$item['description']}",
                    'occurred_at' => $receipt->received_at,
                ]);

                $receiptItem->forceFill([
                    'inventory_movement_id' => $movement->id,
                ])->save();
            }

            return $receipt->load(['items.lot', 'items.movement']);
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{subtotal: int, tax: int, total: int}
     */
    private function lineTotals(array $item): array
    {
        $subtotal = (int) $item['quantity'] * (int) $item['unit_cost'];
        $tax = (int) round($subtotal * (((float) ($item['tax_rate'] ?? 0)) / 100));

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: int, tax_total: int, total: int}
     */
    private function totals(array $items): array
    {
        return collect($items)->reduce(function (array $totals, array $item): array {
            $line = $this->lineTotals($item);

            return [
                'subtotal' => $totals['subtotal'] + $line['subtotal'],
                'tax_total' => $totals['tax_total'] + $line['tax'],
                'total' => $totals['total'] + $line['total'],
            ];
        }, ['subtotal' => 0, 'tax_total' => 0, 'total' => 0]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function findOrCreateLot(int $tenantId, array $item): InventoryLot
    {
        return InventoryLot::firstOrCreate([
            'tenant_id' => $tenantId,
            'product_id' => $item['product_id'],
            'lot_number' => $item['lot_number'],
        ], [
            'product_presentation_id' => $item['product_presentation_id'] ?? null,
            'expires_on' => $item['expires_on'] ?? null,
            'initial_quantity' => 0,
            'current_quantity' => 0,
            'unit_cost' => $item['unit_cost'],
            'status' => InventoryLotStatus::Available,
            'received_at' => now(),
        ]);
    }
}
