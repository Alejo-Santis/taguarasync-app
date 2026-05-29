<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'branch_id', 'name', 'code', 'is_active', 'printer_name', 'paper_width', 'copies', 'auto_print'])]
class CashRegister extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<CashSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    public function hasOpenSession(): bool
    {
        return $this->sessions()->where('status', 'open')->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'auto_print' => 'boolean',
            'copies' => 'integer',
        ];
    }
}
