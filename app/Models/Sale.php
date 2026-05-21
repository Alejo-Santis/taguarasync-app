<?php

namespace App\Models;

use App\Enums\FeStatus;
use App\Enums\PaymentForm;
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
    'customer_id',
    'cash_session_id',
    'document_number',
    'invoice_prefix',
    'subtotal',
    'tax_total',
    'total',
    'payment_method',
    'payment_form',
    'payment_due_date',
    'amount_tendered',
    'change_amount',
    'status',
    'fe_cufe',
    'fe_qr_code',
    'fe_status',
    'fe_sent_at',
    'fe_accepted_at',
    'fe_error_message',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
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
            'payment_form' => PaymentForm::class,
            'payment_due_date' => 'date',
            'status' => SaleStatus::class,
            'fe_status' => FeStatus::class,
            'fe_sent_at' => 'datetime',
            'fe_accepted_at' => 'datetime',
        ];
    }
}
