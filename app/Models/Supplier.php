<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'name',
    'nit',
    'contact_name',
    'contact_email',
    'contact_phone',
    'is_active',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier): void {
            $supplier->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<PurchaseReceipt, $this>
     */
    public function purchaseReceipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    /**
     * @return HasMany<SupplierPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * @return HasMany<SupplierReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SupplierReturn::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
