<?php

namespace App\Actions\Purchases;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreatePurchaseOrder
{
    /**
     * @param  array{
     *     tenant_id: int,
     *     supplier_id: int,
     *     order_number: string,
     *     order_date: string,
     *     expected_date?: string|null,
     *     notes?: string|null,
     *     items: array<int, array{
     *         product_id: int,
     *         product_presentation_id?: int|null,
     *         description: string,
     *         quantity: int,
     *         unit_cost: int,
     *         tax_rate?: int|float|string|null
     *     }>
     * }  $data
     */
    public function execute(array $data, ?User $user = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $user): PurchaseOrder {
            $totals = $this->totals($data['items']);

            $order = PurchaseOrder::create([
                'tenant_id' => $data['tenant_id'],
                'supplier_id' => $data['supplier_id'],
                'user_id' => $user?->id,
                'order_number' => $data['order_number'],
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'status' => PurchaseOrderStatus::Draft,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $line = $this->lineTotals($item);

                $order->items()->create([
                    'tenant_id' => $data['tenant_id'],
                    'product_id' => $item['product_id'],
                    'product_presentation_id' => $item['product_presentation_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'line_subtotal' => $line['subtotal'],
                    'line_tax' => $line['tax'],
                    'line_total' => $line['total'],
                ]);
            }

            return $order->load('items.product');
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
