<?php

namespace App\Actions\Pos;

use App\Enums\InventoryLotStatus;
use App\Enums\ProductStatus;
use App\Models\InventoryLot;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class GetPosProducts
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(string $query): array
    {
        $operator = (new Product)->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $products = Product::query()
            ->select([
                'id', 'commercial_name', 'generic_name', 'pharmaceutical_form',
                'concentration', 'internal_code', 'barcode', 'sale_price',
                'tax_rate', 'minimum_unit_id', 'is_controlled',
            ])
            ->with([
                'presentations' => fn ($q) => $q
                    ->where('is_active', true)
                    ->select(['id', 'product_id', 'name', 'sale_price', 'minimum_unit_quantity', 'is_default']),
                'minimumUnit:id,code',
            ])
            ->where('status', ProductStatus::Active)
            ->whereHas('inventoryLots', fn (Builder $q) => $q
                ->where('status', InventoryLotStatus::Available)
                ->where('current_quantity', '>', 0)
            )
            ->when($query !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('commercial_name', $operator, "%{$query}%")
                ->orWhere('generic_name', $operator, "%{$query}%")
                ->orWhere('internal_code', $operator, "%{$query}%")
                ->orWhere('barcode', $operator, "%{$query}%")
            ))
            ->orderBy('commercial_name')
            ->limit(24)
            ->get();

        $stockByProduct = InventoryLot::query()
            ->where('status', InventoryLotStatus::Available)
            ->whereIn('product_id', $products->pluck('id'))
            ->selectRaw('product_id, SUM(current_quantity) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        return $products->map(fn (Product $product) => [
            'id' => $product->id,
            'commercial_name' => $product->commercial_name,
            'generic_name' => $product->generic_name,
            'pharmaceutical_form' => $product->pharmaceutical_form,
            'concentration' => $product->concentration,
            'internal_code' => $product->internal_code,
            'sale_price' => $product->sale_price,
            'tax_rate' => $product->tax_rate,
            'is_controlled' => $product->is_controlled,
            'minimum_unit_code' => $product->minimumUnit?->code ?? 'und',
            'available_units' => (int) ($stockByProduct[$product->id] ?? 0),
            'presentations' => $product->presentations->map(fn ($pres) => [
                'id' => $pres->id,
                'name' => $pres->name,
                'unit_price' => $pres->sale_price ?? $product->sale_price,
                'minimum_unit_quantity' => $pres->minimum_unit_quantity,
                'is_default' => $pres->is_default,
                'available' => (int) floor(
                    ($stockByProduct[$product->id] ?? 0) / max(1, $pres->minimum_unit_quantity)
                ),
            ])->values()->all(),
        ])->values()->all();
    }
}
