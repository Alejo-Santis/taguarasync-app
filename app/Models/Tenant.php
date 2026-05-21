<?php

namespace App\Models;

use App\Enums\FeEnvironment;
use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'name',
    'slug',
    'legal_name',
    'nit',
    'verification_digit',
    'identification_type_code',
    'organization_type_code',
    'regime_type_code',
    'fiscal_responsibilities',
    'email',
    'phone',
    'city',
    'municipality_code',
    'department',
    'address',
    'economic_activity_code',
    'timezone',
    'status',
    'fe_environment',
    'trial_ends_at',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Laboratory, $this>
     */
    public function laboratories(): HasMany
    {
        return $this->hasMany(Laboratory::class);
    }

    /**
     * @return HasMany<ProductCategory, $this>
     */
    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<ProductPresentation, $this>
     */
    public function productPresentations(): HasMany
    {
        return $this->hasMany(ProductPresentation::class);
    }

    /**
     * @return HasMany<FeResolution, $this>
     */
    public function feResolutions(): HasMany
    {
        return $this->hasMany(FeResolution::class);
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'fe_environment' => FeEnvironment::class,
            'fiscal_responsibilities' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }
}
