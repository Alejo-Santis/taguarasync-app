<?php

namespace App\Models;

use App\Enums\FeStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'sale_id',
    'user_id',
    'prefix',
    'number',
    'discrepancy_reason_code',
    'subtotal',
    'tax_total',
    'total',
    'fe_cufe',
    'fe_qr_code',
    'fe_status',
    'fe_sent_at',
    'fe_accepted_at',
    'fe_error_message',
    'notes',
    'inventory_returned_at',
    'payments_reversed_at',
])]
class CreditNote extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (CreditNote $creditNote): void {
            $creditNote->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CreditNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    protected function casts(): array
    {
        return [
            'fe_status' => FeStatus::class,
            'fe_sent_at' => 'datetime',
            'fe_accepted_at' => 'datetime',
            'inventory_returned_at' => 'datetime',
            'payments_reversed_at' => 'datetime',
        ];
    }
}
