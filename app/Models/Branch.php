<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'name', 'address', 'phone', 'is_main', 'is_active'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return HasMany<CashRegister, $this>
     */
    public function registers(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }

    /**
     * @return HasMany<InventoryLot, $this>
     */
    public function inventoryLots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    /**
     * @return HasMany<InventoryTransfer, $this>
     */
    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(InventoryTransfer::class, 'from_branch_id');
    }

    /**
     * @return HasMany<InventoryTransfer, $this>
     */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(InventoryTransfer::class, 'to_branch_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
