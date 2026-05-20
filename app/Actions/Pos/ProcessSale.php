<?php

namespace App\Actions\Pos;

use App\Actions\Inventory\RegisterInventoryMovement;
use App\Enums\InventoryLotStatus;
use App\Enums\SaleStatus;
use App\Models\InventoryLot;
use App\Models\ProductPresentation;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProcessSale
{
    /**
     * @param  array{
     *     payment_method: string,
     *     amount_tendered?: int|null,
     *     notes?: string|null,
     *     items: array<int, array{
     *         product_id: int,
     *         product_presentation_id: int,
     *         description: string,
     *         quantity: int,
     *         unit_price: int,
     *         tax_rate: int|float|string
     *     }>
     * }  $data
     */
    public function execute(array $data, User $user, ?int $cashSessionId = null): Sale
    {
        return DB::transaction(function () use ($data, $user, $cashSessionId): Sale {
            $totals = $this->calculateTotals($data['items']);

            $amountTendered = isset($data['amount_tendered']) ? (int) $data['amount_tendered'] : null;
            $change = ($amountTendered !== null) ? max(0, $amountTendered - $totals['total']) : null;

            $sale = Sale::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'cash_session_id' => $cashSessionId,
                'document_number' => 'VTA-TEMP',
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'payment_method' => $data['payment_method'],
                'amount_tendered' => $amountTendered,
                'change_amount' => $change,
                'status' => SaleStatus::Completed,
                'notes' => $data['notes'] ?? null,
            ]);

            $sale->update([
                'document_number' => 'VTA-'.str_pad((string) $sale->id, 8, '0', STR_PAD_LEFT),
            ]);

            foreach ($data['items'] as $itemData) {
                $presentation = ProductPresentation::findOrFail($itemData['product_presentation_id']);
                $minUnitsNeeded = $itemData['quantity'] * $presentation->minimum_unit_quantity;

                $lot = $this->findFEFOLot($itemData['product_id'], $minUnitsNeeded);

                $line = $this->lineCalculation($itemData);

                $saleItem = $sale->items()->create([
                    'tenant_id' => $user->tenant_id,
                    'product_id' => $itemData['product_id'],
                    'product_presentation_id' => $itemData['product_presentation_id'],
                    'inventory_lot_id' => $lot->id,
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'tax_rate' => (float) $itemData['tax_rate'],
                    'line_subtotal' => $line['subtotal'],
                    'line_tax' => $line['tax'],
                    'line_total' => $line['total'],
                ]);

                $movement = app(RegisterInventoryMovement::class)->sale($lot, $minUnitsNeeded, $user, [
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'reference_code' => $sale->document_number,
                    'notes' => "Venta {$sale->document_number}: {$itemData['description']}",
                    'occurred_at' => now(),
                ]);

                $saleItem->forceFill(['inventory_movement_id' => $movement->id])->save();
            }

            return $sale->refresh();
        });
    }

    private function findFEFOLot(int $productId, int $minUnitsNeeded): InventoryLot
    {
        $lot = InventoryLot::query()
            ->where('product_id', $productId)
            ->where('status', InventoryLotStatus::Available)
            ->where('current_quantity', '>=', $minUnitsNeeded)
            ->orderByRaw('expires_on IS NULL, expires_on ASC')
            ->first();

        if (! $lot) {
            throw new InvalidArgumentException("Stock insuficiente. Se necesitan {$minUnitsNeeded} unidades minimas.");
        }

        return $lot;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{subtotal: int, tax: int, total: int}
     */
    private function lineCalculation(array $item): array
    {
        $subtotal = $item['quantity'] * $item['unit_price'];
        $tax = (int) round($subtotal * ((float) $item['tax_rate'] / 100));

        return ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $subtotal + $tax];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: int, tax_total: int, total: int}
     */
    private function calculateTotals(array $items): array
    {
        return collect($items)->reduce(function (array $carry, array $item): array {
            $line = $this->lineCalculation($item);

            return [
                'subtotal' => $carry['subtotal'] + $line['subtotal'],
                'tax_total' => $carry['tax_total'] + $line['tax'],
                'total' => $carry['total'] + $line['total'],
            ];
        }, ['subtotal' => 0, 'tax_total' => 0, 'total' => 0]);
    }
}
