<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProduct
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $presentations = $data['presentations'];

            $product = Product::create([
                ...Arr::except($data, ['presentations']),
                'uuid' => (string) Str::uuid(),
                'active_ingredient_id' => $data['active_ingredient_id'] ?? null,
                'regulated_price' => $data['regulated_price'] ?? null,
                'control_level' => $data['is_controlled'] ? $data['control_level'] : null,
            ]);

            $product->presentations()->createMany(
                collect($presentations)
                    ->map(fn (array $presentation) => [
                        'unit_id' => $presentation['unit_id'],
                        'name' => $presentation['name'],
                        'barcode' => $presentation['barcode'] ?? null,
                        'minimum_unit_quantity' => $presentation['minimum_unit_quantity'],
                        'sale_price' => $presentation['sale_price'] ?? null,
                        'is_default' => $presentation['is_default'],
                        'is_active' => $presentation['is_active'],
                    ])
                    ->all()
            );

            return $product;
        });
    }
}
