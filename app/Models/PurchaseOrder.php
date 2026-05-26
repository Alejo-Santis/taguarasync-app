<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'supplier_id',
    'user_id',
    'order_number',
    'order_date',
    'expected_date',
    'status',
    'subtotal',
    'tax_total',
    'total',
    'notes',
])]
class PurchaseOrder extends Model
{
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $order): void {
            $order->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * @return HasMany<PurchaseReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'status' => PurchaseOrderStatus::class,
        ];
    }
}
