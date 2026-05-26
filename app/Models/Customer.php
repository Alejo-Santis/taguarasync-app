<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Seeders\ConsumidorFinalSeeder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'price_list_id',
    'identification_type_code',
    'identification_number',
    'merchant_registration',
    'verification_digit',
    'first_name',
    'last_name',
    'business_name',
    'organization_type_code',
    'regime_type_code',
    'fiscal_responsibilities',
    'email',
    'phone',
    'address',
    'municipality_code',
    'is_active',
    'notes',
])]
class Customer extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            $customer->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function getFullNameAttribute(): string
    {
        if ($this->business_name) {
            return $this->business_name;
        }

        return trim("{$this->first_name} {$this->last_name}");
    }

    public function isConsumidorFinal(): bool
    {
        return $this->identification_number === ConsumidorFinalSeeder::IDENTIFICATION_NUMBER;
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeConsumidorFinal(Builder $query): Builder
    {
        return $query->where('identification_number', ConsumidorFinalSeeder::IDENTIFICATION_NUMBER);
    }

    /**
     * Obtiene o crea el cliente Consumidor Final para un tenant.
     */
    public static function getConsumidorFinal(int $tenantId): self
    {
        return ConsumidorFinalSeeder::createForTenant($tenantId);
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    protected function casts(): array
    {
        return [
            'fiscal_responsibilities' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
