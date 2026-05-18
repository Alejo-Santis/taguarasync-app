<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'tenant_id',
    'user_id',
    'document_number',
    'subtotal',
    'tax_total',
    'total',
    'payment_method',
    'amount_tendered',
    'change_amount',
    'status',
    'notes',
])]
class Sale extends Model
{
    use BelongsToTenant, HasFactory;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'status' => SaleStatus::class,
        ];
    }
}
