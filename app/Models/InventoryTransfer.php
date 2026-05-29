<?php

namespace App\Models;

use App\Enums\InventoryTransferStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'from_branch_id',
    'to_branch_id',
    'user_id',
    'status',
    'notes',
    'confirmed_at',
])]
class InventoryTransfer extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (InventoryTransfer $transfer): void {
            $transfer->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<InventoryTransferItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InventoryTransferStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }
}
