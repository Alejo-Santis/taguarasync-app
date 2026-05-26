<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SupplierPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupplierPayment extends Model
{
    /** @use HasFactory<SupplierPaymentFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'supplier_id',
        'bank_account_id',
        'user_id',
        'payment_date',
        'amount',
        'reference',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupplierPayment $payment): void {
            $payment->uuid ??= (string) Str::uuid();
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
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'integer',
        ];
    }
}
