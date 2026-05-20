<?php

namespace App\Models;

use App\Enums\CashSessionStatus;
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
    'cash_register_id',
    'user_id',
    'closed_by_user_id',
    'opening_amount',
    'actual_closing_amount',
    'difference',
    'status',
    'notes',
    'opened_at',
    'closed_at',
])]
class CashSession extends Model
{
    use BelongsToTenant, HasFactory;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (CashSession $session): void {
            $session->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<CashRegister, $this>
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isOpen(): bool
    {
        return $this->status === CashSessionStatus::Open;
    }

    /** Total cash collected (only cash payment method sales). */
    public function cashSalesTotal(): int
    {
        return (int) $this->sales()->where('payment_method', 'cash')->sum('total');
    }

    /** Total of all sales in the session. */
    public function salesTotal(): int
    {
        return (int) $this->sales()->sum('total');
    }

    /** Expected closing = opening + cash collected. */
    public function expectedClosingAmount(): int
    {
        return $this->opening_amount + $this->cashSalesTotal();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CashSessionStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
