<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'credit_note_id',
    'product_id',
    'product_presentation_id',
    'description',
    'quantity',
    'unit_price',
    'dian_unit_measure_code',
    'tax_rate',
    'discount_amount',
    'discount_rate',
    'line_subtotal',
    'line_tax',
    'line_total',
])]
class CreditNoteItem extends Model
{
    use BelongsToTenant;

    /**
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
            'discount_rate' => 'decimal:2',
        ];
    }
}
