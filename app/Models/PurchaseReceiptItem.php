<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PurchaseReceiptItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'purchase_receipt_id',
    'product_id',
    'product_presentation_id',
    'inventory_lot_id',
    'inventory_movement_id',
    'description',
    'lot_number',
    'expires_on',
    'quantity',
    'unit_cost',
    'tax_rate',
    'line_subtotal',
    'line_tax',
    'line_total',
])]
class PurchaseReceiptItem extends Model
{
    /** @use HasFactory<PurchaseReceiptItemFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<PurchaseReceipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductPresentation, $this>
     */
    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'product_presentation_id');
    }

    /**
     * @return BelongsTo<InventoryLot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'inventory_lot_id');
    }

    /**
     * @return BelongsTo<InventoryMovement, $this>
     */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'tax_rate' => 'decimal:2',
            'line_subtotal' => 'integer',
            'line_tax' => 'integer',
            'line_total' => 'integer',
        ];
    }
}
