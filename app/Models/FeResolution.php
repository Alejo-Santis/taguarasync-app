<?php

namespace App\Models;

use App\Enums\FeEnvironment;
use App\Enums\FeResolutionType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tenant_id',
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

    public function nextNumber(): int
    {
        return $this->current_number + 1;
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
