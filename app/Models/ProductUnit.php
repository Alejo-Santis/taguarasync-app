<?php

namespace App\Models;

use App\Enums\ProductUnitKind;
use Database\Factories\ProductUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'kind', 'allows_decimals', 'is_active'])]
class ProductUnit extends Model
{
    /** @use HasFactory<ProductUnitFactory> */
    use HasFactory;

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'minimum_unit_id');
    }

    /**
     * @return HasMany<ProductPresentation, $this>
     */
    public function presentations(): HasMany
    {
        return $this->hasMany(ProductPresentation::class, 'unit_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProductUnitKind::class,
            'allows_decimals' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
