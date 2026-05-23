<?php

namespace App\Models;

use App\Enums\FeEnvironment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'electronic_invoicing_enabled',
    'environment',
    'api_token',
    'software_id',
    'identification_type_code',
    'organization_type_code',
    'regime_type_code',
    'fiscal_responsibilities',
    'economic_activity_code',
    'merchant_registration',
    'municipality_api_id',
])]
class TenantFeConfig extends Model
{
    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected function casts(): array
    {
        return [
            'electronic_invoicing_enabled' => 'boolean',
            'environment' => FeEnvironment::class,
            'api_token' => 'encrypted',
            'fiscal_responsibilities' => 'array',
        ];
    }
}
