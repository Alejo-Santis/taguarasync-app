<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'supplier_return_id',
    'product_id',
    'inventory_lot_id',
    'inventory_movement_id',
    'description',
    'lot_number',
    'quantity',
    'unit_cost',
    'tax_rate',
    'line_subtotal',
    'line_tax',
    'line_total',
])]
class SupplierReturnItem extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<SupplierReturn, $this>
     */
    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
        ];
    }
}
