<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BankAccountMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'bank_account_id',
    'sale_payment_id',
    'credit_note_id',
    'user_id',
    'type',
    'amount',
    'reference',
    'status',
    'reconciled_at',
    'reconciled_by_user_id',
    'reconciliation_notes',
    'occurred_at',
    'description',
])]
class BankAccountMovement extends Model
{
    /** @use HasFactory<BankAccountMovementFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<SalePayment, $this>
     */
    public function salePayment(): BelongsTo
    {
        return $this->belongsTo(SalePayment::class);
    }

    /**
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
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
    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }
}
