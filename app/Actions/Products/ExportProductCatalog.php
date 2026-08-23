<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ExportProductCatalog
{
    /** @return list<string> */
    public static function columns(): array
    {
        return [
            'codigo_interno',
            'codigo_barras',
            'nombre_comercial',
            'nombre_generico',
            'laboratorio',
            'categoria',
            'principio_activo',
            'forma_farmaceutica',
            'concentracion',
            'precio_compra',
            'precio_venta',
            'iva_porcentaje',
            'stock_minimo',
            'controlado',
            'estado',
        ];
    }

    public function query(): Builder
    {
        // No custom orderBy: chunkById() manages its own ordering by id to
        // paginate safely, and row order doesn't matter for a CSV export.
        return Product::query()
            ->with(['laboratory:id,name', 'category:id,name', 'activeIngredient:id,dci_name']);
    }

    /** @return list<string|int|null> */
    public function row(Product $product): array
    {
        return [
            $product->internal_code,
            $product->barcode,
            $product->commercial_name,
            $product->generic_name,
            $product->laboratory?->name,
            $product->category?->name,
            $product->activeIngredient?->dci_name,
            $product->pharmaceutical_form,
            $product->concentration,
            $product->purchase_price,
            $product->sale_price,
            (string) $product->tax_rate,
            $product->minimum_stock,
            $product->is_controlled ? 'si' : 'no',
            $product->status->label(),
        ];
    }
}
