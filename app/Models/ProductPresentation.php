<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductPresentationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'product_id',
    'unit_id',
    'name',
    'barcode',
    'minimum_unit_quantity',
    'sale_price',
    'is_default',
    'is_active',
])]
class ProductPresentation extends Model
{
    /** @use HasFactory<ProductPresentationFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }

    /**
     * @return HasMany<InventoryLot, $this>
     */
    public function inventoryLots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_unit_quantity' => 'integer',
            'sale_price' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
