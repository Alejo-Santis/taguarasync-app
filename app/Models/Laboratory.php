<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LaboratoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'name',
    'nit',
    'country',
    'contact_name',
    'contact_email',
    'contact_phone',
    'is_active',
])]
class Laboratory extends Model
{
    /** @use HasFactory<LaboratoryFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
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
