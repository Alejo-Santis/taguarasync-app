<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'bank_name',
    'account_name',
    'account_number',
    'type',
    'is_default',
    'is_active',
    'notes',
])]
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * @return HasMany<BankAccountMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(BankAccountMovement::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
