<?php

namespace App\Models;

use App\Exceptions\FeResolutionExhaustedException;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'tenant_id',
    'resolution_id',
    'next_consecutive',
])]
class FeSend extends Model
{
    use BelongsToTenant;

    /**
     * @return BelongsTo<FeResolution, $this>
     */
    public function resolution(): BelongsTo
    {
        return $this->belongsTo(FeResolution::class, 'resolution_id');
    }

    /**
     * Atomically reserves and returns the next consecutive number.
     * Row-level lock prevents duplicate assignment under concurrency.
     *
     * @throws FeResolutionExhaustedException
     */
    public function consumeNextNumber(): int
    {
        return DB::transaction(function () {
            $fresh = static::withoutGlobalScopes()->lockForUpdate()->findOrFail($this->id);
            $resolution = $fresh->resolution;

            if ($fresh->next_consecutive >= $resolution->to_number) {
                throw new FeResolutionExhaustedException(
                    "La resolución {$resolution->code} no tiene números disponibles (máx: {$resolution->to_number})."
                );
            }

            $next = $fresh->next_consecutive + 1;
            $fresh->update(['next_consecutive' => $next]);

            return $next;
        });
    }

    public function hasRemainingNumbers(): bool
    {
        return $this->next_consecutive < $this->resolution->to_number;
    }
}
