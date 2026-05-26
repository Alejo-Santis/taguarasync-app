<?php

namespace App\Actions\Products;

use App\Enums\InventoryLotStatus;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductPresentation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ListProducts
{
    /**
     * @return array{
     *     products: LengthAwarePaginator<int, array<string, mixed>>,
     *     filters: array{q: string, status: string, controlled: string, low_stock: string},
     *     stats: array{total: int, active: int, controlled: int, presentations: int},
     *     statuses: array<int, array{value: string, label: string}>
     * }
     */
    public function execute(Request $request): array
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'status' => $request->string('status')->toString(),
            'controlled' => $request->string('controlled')->toString(),
            'low_stock' => $request->string('low_stock')->toString(),
        ];

        $products = Product::query()
            ->select([
                'id',
                'uuid',
                'laboratory_id',
                'product_category_id',
                'active_ingredient_id',
                'minimum_unit_id',
                'internal_code',
                'barcode',
                'commercial_name',
                'generic_name',
                'cum',
                'health_registration',
                'pharmaceutical_form',
                'concentration',
                'purchase_price',
                'sale_price',
                'regulated_price',
                'tax_rate',
                'requires_invima_registration',
                'is_controlled',
                'control_level',
                'notes',
                'status',
                'created_at',
            ])
            ->with([
                'activeIngredient:id,dci_name',
                'category:id,name',
                'laboratory:id,name',
                'minimumUnit:id,name,code',
                'presentations' => fn ($query) => $query
                    ->select(['id', 'product_id', 'unit_id', 'name', 'minimum_unit_quantity', 'sale_price', 'is_default', 'is_active'])
                    ->with('unit:id,name,code')
                    ->orderByDesc('is_default')
                    ->orderBy('name'),
            ])
            ->withCount('presentations')
            ->when($filters['q'] !== '', fn (Builder $query) => $this->applySearch($query, $filters['q']))
            ->when($this->isValidStatus($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['controlled'] === 'yes', fn (Builder $query) => $query->where('is_controlled', true))
            ->when($filters['controlled'] === 'no', fn (Builder $query) => $query->where('is_controlled', false))
            ->when($filters['low_stock'] === '1', fn (Builder $query) => $query
                ->where('minimum_stock', '>', 0)
                ->whereRaw(
                    'minimum_stock > (SELECT COALESCE(SUM(il.current_quantity), 0) FROM inventory_lots il WHERE il.product_id = products.id AND il.status = ?)',
                    [InventoryLotStatus::Available->value]
                )
            )
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product) => $this->transformProduct($product));

        return [
            'products' => $products,
            'filters' => $filters,
            'stats' => $this->stats(),
            'statuses' => $this->statuses(),
        ];
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql'
            ? 'ilike'
            : 'like';

        return $query->where(function (Builder $query) use ($operator, $search): void {
            $query
                ->where('commercial_name', $operator, "%{$search}%")
                ->orWhere('generic_name', $operator, "%{$search}%")
                ->orWhere('barcode', $operator, "%{$search}%")
                ->orWhere('internal_code', $operator, "%{$search}%");
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function transformProduct(Product $product): array
    {
        return [
            'uuid' => $product->uuid,
            'internal_code' => $product->internal_code,
            'barcode' => $product->barcode,
            'commercial_name' => $product->commercial_name,
            'generic_name' => $product->generic_name,
            'cum' => $product->cum,
            'health_registration' => $product->health_registration,
            'pharmaceutical_form' => $product->pharmaceutical_form,
            'concentration' => $product->concentration,
            'purchase_price' => $product->purchase_price,
            'sale_price' => $product->sale_price,
            'regulated_price' => $product->regulated_price,
            'tax_rate' => $product->tax_rate,
            'requires_invima_registration' => $product->requires_invima_registration,
            'is_controlled' => $product->is_controlled,
            'control_level' => $product->control_level,
            'notes' => $product->notes,
            'status' => [
                'value' => $product->status->value,
                'label' => $product->status->label(),
            ],
            'laboratory' => $product->laboratory?->name,
            'category' => $product->category?->name,
            'active_ingredient' => $product->activeIngredient?->dci_name,
            'minimum_unit' => $product->minimumUnit ? [
                'name' => $product->minimumUnit->name,
                'code' => $product->minimumUnit->code,
            ] : null,
            'presentations_count' => $product->presentations_count,
            'presentations' => $product->presentations->map(fn ($presentation) => [
                'name' => $presentation->name,
                'unit' => $presentation->unit?->name,
                'minimum_unit_quantity' => $presentation->minimum_unit_quantity,
                'sale_price' => $presentation->sale_price,
                'is_default' => $presentation->is_default,
                'is_active' => $presentation->is_active,
            ])->values(),
        ];
    }

    /**
     * @return array{total: int, active: int, controlled: int, presentations: int}
     */
    private function stats(): array
    {
        return [
            'total' => Product::count(),
            'active' => Product::where('status', ProductStatus::Active)->count(),
            'controlled' => Product::where('is_controlled', true)->count(),
            'presentations' => ProductPresentation::count(),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return collect(ProductStatus::cases())
            ->map(fn (ProductStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->values()
            ->all();
    }

    private function isValidStatus(string $status): bool
    {
        return ProductStatus::tryFrom($status) !== null;
    }
}
