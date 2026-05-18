<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'tenant_id',
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
    'status',
    'image_path',
    'notes',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Laboratory, $this>
     */
    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * @return BelongsTo<ActiveIngredient, $this>
     */
    public function activeIngredient(): BelongsTo
    {
        return $this->belongsTo(ActiveIngredient::class);
    }

    /**
     * @return BelongsTo<ProductUnit, $this>
     */
    public function minimumUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'minimum_unit_id');
    }

    /**
     * @return HasMany<ProductPresentation, $this>
     */
    public function presentations(): HasMany
    {
        return $this->hasMany(ProductPresentation::class);
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
            'purchase_price' => 'integer',
            'sale_price' => 'integer',
            'regulated_price' => 'integer',
            'tax_rate' => 'decimal:2',
            'requires_invima_registration' => 'boolean',
            'is_controlled' => 'boolean',
            'status' => ProductStatus::class,
        ];
    }
}
