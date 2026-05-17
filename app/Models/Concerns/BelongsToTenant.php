<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::creating(function ($model): void {
            $currentTenant = app(CurrentTenant::class);

            if (! $model->tenant_id && $currentTenant->check()) {
                $model->tenant_id = $currentTenant->id();
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder): void {
            $currentTenant = app(CurrentTenant::class);

            if ($currentTenant->check()) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $currentTenant->id());
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
