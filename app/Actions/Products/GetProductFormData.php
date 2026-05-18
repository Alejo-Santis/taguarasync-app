<?php

namespace App\Actions\Products;

use App\Models\Product;

class GetProductFormData
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Product $product): array
    {
        $product->load([
            'presentations' => fn ($query) => $query
                ->orderByDesc('is_default')
                ->orderBy('name'),
        ]);

        return [
            'uuid' => $product->uuid,
            'laboratory_id' => $product->laboratory_id,
            'product_category_id' => $product->product_category_id,
            'active_ingredient_id' => $product->active_ingredient_id,
            'minimum_unit_id' => $product->minimum_unit_id,
            'internal_code' => $product->internal_code,
            'barcode' => $product->barcode,
            'commercial_name' => $product->commercial_name,
            'generic_name' => $product->generic_name,
            'cum' => $product->cum,
            'health_registration' => $product->health_registration,
            'pharmaceutical_form' => $product->pharmaceutical_form,
            'concentration' => $product->concentration,
            'purchase_price' => $product->purchase_price,
            'sale_price' => $product->sale_price,
            'regulated_price' => $product->regulated_price,
            'tax_rate' => $product->tax_rate,
            'requires_invima_registration' => $product->requires_invima_registration,
            'is_controlled' => $product->is_controlled,
            'control_level' => $product->control_level,
            'status' => $product->status->value,
            'notes' => $product->notes,
            'presentations' => $product->presentations->map(fn ($presentation) => [
                'unit_id' => $presentation->unit_id,
                'name' => $presentation->name,
                'barcode' => $presentation->barcode,
                'minimum_unit_quantity' => $presentation->minimum_unit_quantity,
                'sale_price' => $presentation->sale_price,
                'is_default' => $presentation->is_default,
                'is_active' => $presentation->is_active,
            ])->values()->all(),
        ];
    }
}
