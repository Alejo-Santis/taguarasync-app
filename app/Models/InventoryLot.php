<?php

namespace App\Models;

use App\Enums\InventoryLotStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InventoryLotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'product_id',
    'product_presentation_id',
    'lot_number',
    'expires_on',
    'initial_quantity',
    'current_quantity',
    'unit_cost',
    'status',
    'received_at',
    'notes',
])]
class InventoryLot extends Model
{
    /** @use HasFactory<InventoryLotFactory> */
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (InventoryLot $inventoryLot): void {
            $inventoryLot->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
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
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * @return HasMany<PurchaseReceiptItem, $this>
     */
    public function purchaseReceiptItems(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'initial_quantity' => 'integer',
            'current_quantity' => 'integer',
            'unit_cost' => 'integer',
            'status' => InventoryLotStatus::class,
            'received_at' => 'datetime',
        ];
    }
}
