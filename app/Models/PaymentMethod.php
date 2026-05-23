<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'name',
    'code',
    'type',
    'dian_payment_method_code',
    'payment_form',
    'requires_reference',
    'requires_bank_account',
    'allows_attachment',
    'affects_cash',
    'is_active',
    'sort_order',
])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_reference' => 'boolean',
            'requires_bank_account' => 'boolean',
            'allows_attachment' => 'boolean',
            'affects_cash' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
