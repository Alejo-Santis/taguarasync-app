<?php

namespace App\Actions\Purchases;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\Supplier;

class GetPurchaseReceiptFormOptions
{
    /**
     * @return array{
     *     suppliers: array<int, array{id: int, name: string, nit: ?string}>,
     *     products: array<int, array{
     *         id: int,
     *         name: string,
     *         code: ?string,
     *         purchase_price: int,
     *         tax_rate: string,
     *         presentations: array<int, array{id: int, name: string, minimum_unit_quantity: int}>
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
            'products' => Product::query()
                ->select(['id', 'commercial_name', 'internal_code', 'purchase_price', 'tax_rate', 'status'])
                ->with([
                    'presentations' => fn ($query) => $query
                        ->select(['id', 'product_id', 'name', 'minimum_unit_quantity', 'is_active'])
                        ->where('is_active', true)
                        ->orderByDesc('is_default')
                        ->orderBy('name'),
                ])
                ->where('status', ProductStatus::Active)
                ->orderBy('commercial_name')
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->commercial_name,
                    'code' => $product->internal_code,
                    'purchase_price' => $product->purchase_price,
                    'tax_rate' => $product->tax_rate,
                    'presentations' => $product->presentations
                        ->map(fn (ProductPresentation $presentation) => [
                            'id' => $presentation->id,
                            'name' => $presentation->name,
                            'minimum_unit_quantity' => $presentation->minimum_unit_quantity,
                        ])
                        ->values()
                        ->all(),
                ])
                ->all(),
        ];
    }
}
