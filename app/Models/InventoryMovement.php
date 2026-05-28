<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'inventory_lot_id',
    'product_id',
    'product_presentation_id',
    'user_id',
    'type',
    'quantity_delta',
    'quantity_before',
    'quantity_after',
    'unit_cost',
    'reference_type',
    'reference_id',
    'reference_code',
    'notes',
    'occurred_at',
    'server_id',
    'synced_at',
])]
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (InventoryMovement $inventoryMovement): void {
            $inventoryMovement->uuid ??= (string) Str::uuid();
            $inventoryMovement->server_id ??= config('sync.server_id', 'cloud');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<InventoryLot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'inventory_lot_id');
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<PurchaseReceiptItem, $this>
     */
    public function purchaseReceiptItem(): HasOne
    {
        return $this->hasOne(PurchaseReceiptItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
            'quantity_delta' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'unit_cost' => 'integer',
            'occurred_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /** Scope: movimientos pendientes de sync originados en este servidor. */
    public function scopePendingSync(Builder $query): Builder
    {
        return $query->where('server_id', config('sync.server_id'))->whereNull('synced_at');
    }
}
