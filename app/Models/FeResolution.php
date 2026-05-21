<?php

namespace App\Models;

use App\Enums\FeEnvironment;
use App\Enums\FeResolutionType;
use App\Exceptions\FeResolutionExhaustedException;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'tenant_id',
    'code',
    'type',
    'prefix',
    'resolution_number',
    'resolution_date',
    'technical_key',
    'from_number',
    'to_number',
    'current_number',
    'valid_from',
    'valid_until',
    'environment',
    'is_active',
])]
class FeResolution extends Model
{
    use BelongsToTenant;

    public function hasRemainingNumbers(): bool
    {
        return $this->current_number < $this->to_number;
    }

    public function isExpired(): bool
    {
        return now()->isAfter($this->valid_until);
    }

    /**
     * Atomically reserves and returns the next consecutive number.
     * Uses a row-level lock to prevent duplicate assignment under concurrency.
     *
     * @throws FeResolutionExhaustedException
     */
    public function consumeNextNumber(): int
    {
        return DB::transaction(function () {
            $fresh = static::withoutGlobalScopes()->lockForUpdate()->findOrFail($this->id);

            if ($fresh->current_number >= $fresh->to_number) {
                throw new FeResolutionExhaustedException(
                    "La resolución {$fresh->code} no tiene números disponibles (máx: {$fresh->to_number})."
                );
            }

            $next = $fresh->current_number + 1;
            $fresh->update(['current_number' => $next]);

            return $next;
        });
    }

    protected function casts(): array
    {
        return [
            'type' => FeResolutionType::class,
            'environment' => FeEnvironment::class,
            'resolution_date' => 'date',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
