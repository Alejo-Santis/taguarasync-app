<?php

namespace App\Models;

use App\Enums\SupplierReturnStatus;
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
    'purchase_receipt_id',
    'user_id',
    'document_number',
    'return_date',
    'reason',
    'status',
    'subtotal',
    'tax_total',
    'total',
    'notes',
])]
class SupplierReturn extends Model
{
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (SupplierReturn $supplierReturn): void {
            $supplierReturn->uuid ??= (string) Str::uuid();
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
     * @return BelongsTo<PurchaseReceipt, $this>
     */
    public function purchaseReceipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    /**
     * @return HasMany<SupplierReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'status' => SupplierReturnStatus::class,
        ];
    }
}
