<?php

namespace App\Models;

use App\Enums\PurchaseRadianStatus;
use App\Enums\PurchaseReceiptStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PurchaseReceiptFactory;
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
    'purchase_order_id',
    'user_id',
    'document_number',
    'document_date',
    'received_at',
    'subtotal',
    'tax_total',
    'total',
    'status',
    'radian_status',
    'radian_checked_at',
    'radian_response',
    'radian_error_message',
    'source_file_path',
    'notes',
    'supplier_cufe',
])]
class PurchaseReceipt extends Model
{
    /** @use HasFactory<PurchaseReceiptFactory> */
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (PurchaseReceipt $purchaseReceipt): void {
            $purchaseReceipt->uuid ??= (string) Str::uuid();
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
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PurchaseReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'received_at' => 'datetime',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'status' => PurchaseReceiptStatus::class,
            'radian_status' => PurchaseRadianStatus::class,
            'radian_checked_at' => 'datetime',
            'radian_response' => 'array',
        ];
    }
}
