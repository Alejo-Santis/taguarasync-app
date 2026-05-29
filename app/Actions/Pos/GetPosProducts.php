<?php

namespace App\Actions\Pos;

use App\Enums\InventoryLotStatus;
use App\Enums\ProductStatus;
use App\Models\InventoryLot;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class GetPosProducts
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(string $query, ?int $priceListId = null, ?int $branchId = null): array
    {
        $operator = (new Product)->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $products = Product::query()
            ->select([
                'id', 'commercial_name', 'generic_name', 'pharmaceutical_form',
                'concentration', 'internal_code', 'barcode', 'sale_price',
                'regulated_price', 'tax_rate', 'minimum_unit_id', 'is_controlled',
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
                ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId))
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

        $productIds = $products->pluck('id');

        $stockByProduct = InventoryLot::query()
            ->where('status', InventoryLotStatus::Available)
            ->whereIn('product_id', $productIds)
            ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId))
            ->selectRaw('product_id, SUM(current_quantity) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        // FEFO lot expiration: the lot that will be dispensed next (earliest non-null expiry, then nulls)
        $nextExpiryByProduct = InventoryLot::query()
            ->where('status', InventoryLotStatus::Available)
            ->where('current_quantity', '>', 0)
            ->whereNotNull('expires_on')
            ->whereIn('product_id', $productIds)
            ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId))
            ->orderByRaw('expires_on ASC')
            ->get(['product_id', 'expires_on'])
            ->unique('product_id')
            ->pluck('expires_on', 'product_id');

        $priceOverrides = $priceListId
            ? PriceListItem::query()
                ->where('price_list_id', $priceListId)
                ->whereIn('product_id', $productIds)
                ->pluck('sale_price', 'product_id')
            : collect();

        return $products->map(function (Product $product) use ($stockByProduct, $nextExpiryByProduct, $priceOverrides): array {
            $basePrice = $priceOverrides->get($product->id) ?? $product->sale_price;

            return [
                'id' => $product->id,
                'commercial_name' => $product->commercial_name,
                'generic_name' => $product->generic_name,
                'pharmaceutical_form' => $product->pharmaceutical_form,
                'concentration' => $product->concentration,
                'internal_code' => $product->internal_code,
                'sale_price' => $basePrice,
                'regulated_price' => $product->regulated_price,
                'tax_rate' => $product->tax_rate,
                'is_controlled' => $product->is_controlled,
                'minimum_unit_code' => $product->minimumUnit?->code ?? 'und',
                'available_units' => (int) ($stockByProduct[$product->id] ?? 0),
                'next_lot_expires_on' => isset($nextExpiryByProduct[$product->id])
                    ? $nextExpiryByProduct[$product->id]->toDateString()
                    : null,
                'presentations' => $product->presentations->map(fn ($pres) => [
                    'id' => $pres->id,
                    'name' => $pres->name,
                    'unit_price' => $pres->sale_price ?? $basePrice,
                    'minimum_unit_quantity' => $pres->minimum_unit_quantity,
                    'is_default' => $pres->is_default,
                    'available' => (int) floor(
                        ($stockByProduct[$product->id] ?? 0) / max(1, $pres->minimum_unit_quantity)
                    ),
                ])->values()->all(),
            ];
        })->values()->all();
    }
}
