<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'default_rate', 'description'])]
class DianTaxType extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'default_rate' => 'decimal:2',
        ];
    }
}
