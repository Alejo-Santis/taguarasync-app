<?php

namespace App\Models;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'uuid',
    'name',
    'slug',
    'legal_name',
    'nit',
    'merchant_registration',
    'verification_digit',
    'email',
    'phone',
    'city',
    'municipality_code',
    'department',
    'address',
    'timezone',
    'status',
    'trial_ends_at',
    'plan',
    'billing_cycle',
    'subscribed_until',
    'last_payment_at',
    'max_users',
    'max_cash_registers',
    'offline_sync_enabled',
    'printer_settings',
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
     * @return HasOne<TenantFeConfig, $this>
     */
    public function feConfig(): HasOne
    {
        return $this->hasOne(TenantFeConfig::class);
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
     * @return HasMany<FeSubmission, $this>
     */
    public function feSubmissions(): HasMany
    {
        return $this->hasMany(FeSubmission::class);
    }

    public function canAddUser(int $currentCount): bool
    {
        return is_null($this->max_users) || $currentCount < $this->max_users;
    }

    public function canAddCashRegister(int $currentCount): bool
    {
        return is_null($this->max_cash_registers) || $currentCount < $this->max_cash_registers;
    }

    /**
     * Returns a simple billing status key for frontend display.
     *
     * - suspended: tenant explicitly blocked
     * - trial: no paid subscription, trial period still active
     * - active: subscribed_until > now (>7 days remaining)
     * - expiring_soon: subscribed_until within next 7 days
     * - grace: subscribed_until passed within the last 5 days
     * - expired: subscribed_until passed more than 5 days ago
     * - no_subscription: never subscribed and no active trial
     */
    public function billingStatus(): string
    {
        if ($this->status === TenantStatus::Suspended) {
            return 'suspended';
        }

        if (is_null($this->subscribed_until)) {
            if ($this->trial_ends_at?->isFuture()) {
                return 'trial';
            }

            return 'no_subscription';
        }

        $daysLeft = now()->diffInDays($this->subscribed_until, false);

        return match (true) {
            $daysLeft > 7 => 'active',
            $daysLeft >= 0 => 'expiring_soon',
            $daysLeft >= -5 => 'grace',
            default => 'expired',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'plan' => TenantPlan::class,
            'trial_ends_at' => 'datetime',
            'subscribed_until' => 'datetime',
            'last_payment_at' => 'datetime',
            'offline_sync_enabled' => 'boolean',
            'printer_settings' => 'array',
        ];
    }
}
