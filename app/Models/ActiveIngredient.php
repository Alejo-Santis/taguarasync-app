<?php

namespace App\Models;

use Database\Factories\ActiveIngredientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['dci_name', 'pharmacological_group', 'atc_classification'])]
class ActiveIngredient extends Model
{
    /** @use HasFactory<ActiveIngredientFactory> */
    use HasFactory;

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
