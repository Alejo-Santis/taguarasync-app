<?php

namespace App\Actions\Purchases;

use App\Enums\InventoryLotStatus;
use App\Enums\ProductStatus;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;

class GetSupplierReturnFormOptions
{
    /**
     * @return array{
     *     suppliers: array<int, array{id: int, name: string, nit: ?string}>,
     *     receipts: array<int, array{id: int, document_number: string, received_at: string}>,
     *     products: array<int, array{
     *         id: int,
     *         name: string,
     *         code: ?string,
     *         purchase_price: int,
     *         tax_rate: string,
     *         lots: array<int, array{id: int, lot_number: string, current_quantity: int, unit_cost: int}>
     *     }>
     * }
     */
    public function execute(): array
    {
        return [
            'suppliers' => Supplier::query()
                ->select(['id', 'name', 'nit'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Supplier $supplier) => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'nit' => $supplier->nit,
                ])
                ->all(),
            'receipts' => PurchaseReceipt::query()
                ->select(['id', 'document_number', 'received_at'])
                ->orderByDesc('received_at')
                ->limit(200)
                ->get()
                ->map(fn (PurchaseReceipt $receipt) => [
                    'id' => $receipt->id,
                    'document_number' => $receipt->document_number,
                    'received_at' => $receipt->received_at->toDateString(),
                ])
                ->all(),
            'products' => Product::query()
                ->select(['id', 'commercial_name', 'internal_code', 'purchase_price', 'tax_rate'])
                ->with([
                    'inventoryLots' => fn ($query) => $query
                        ->select(['id', 'product_id', 'lot_number', 'current_quantity', 'unit_cost', 'status'])
                        ->where('status', InventoryLotStatus::Available)
                        ->where('current_quantity', '>', 0)
                        ->orderBy('lot_number'),
                ])
                ->where('status', ProductStatus::Active)
                ->whereHas('inventoryLots', fn ($query) => $query
                    ->where('status', InventoryLotStatus::Available)
                    ->where('current_quantity', '>', 0)
                )
                ->orderBy('commercial_name')
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->commercial_name,
                    'code' => $product->internal_code,
                    'purchase_price' => $product->purchase_price,
                    'tax_rate' => $product->tax_rate,
                    'lots' => $product->inventoryLots
                        ->map(fn (InventoryLot $lot) => [
                            'id' => $lot->id,
                            'lot_number' => $lot->lot_number,
                            'current_quantity' => $lot->current_quantity,
                            'unit_cost' => $lot->unit_cost,
                        ])
                        ->values()
                        ->all(),
                ])
                ->all(),
        ];
    }
}
