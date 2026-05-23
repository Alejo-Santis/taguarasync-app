<?php

namespace App\Models;

use App\Enums\FeEnvironment;
use App\Enums\FeResolutionType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    'valid_from',
    'valid_until',
    'environment',
    'is_active',
])]
class FeResolution extends Model
{
    use BelongsToTenant;

    /**
     * @return HasOne<FeSend, $this>
     */
    public function send(): HasOne
    {
        return $this->hasOne(FeSend::class, 'resolution_id');
    }

    public function hasRemainingNumbers(): bool
    {
        return ($this->send?->next_consecutive ?? 0) < $this->to_number;
    }

    public function isExpired(): bool
    {
        return now()->isAfter($this->valid_until);
    }

    /**
     * Delegates consecutive number reservation to the FeSend record.
     * Atomic via row-level lock in FeSend::consumeNextNumber().
     */
    public function consumeNextNumber(): int
    {
        $send = $this->send ?? FeSend::create([
            'tenant_id' => $this->tenant_id,
            'resolution_id' => $this->id,
            'next_consecutive' => $this->from_number - 1,
        ]);

        return $send->consumeNextNumber();
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
