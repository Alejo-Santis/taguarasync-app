<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'identification_type_code',
    'identification_number',
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
