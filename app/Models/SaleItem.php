<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'sale_id',
    'product_id',
    'product_presentation_id',
    'inventory_lot_id',
    'inventory_movement_id',
    'description',
    'quantity',
    'unit_price',
    'discount_amount',
    'discount_rate',
    'prescription_number',
    'patient_id_number',
    'patient_name',
    'tax_rate',
    'line_subtotal',
    'line_tax',
    'line_total',
])]
class SaleItem extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<InventoryLot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'inventory_lot_id');
    }

    /**
     * @return BelongsTo<ProductPresentation, $this>
     */
    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'product_presentation_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
            'discount_rate' => 'decimal:2',
        ];
    }
}
