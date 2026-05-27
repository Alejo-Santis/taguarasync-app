<?php

namespace App\Actions\Inventory;

use App\Enums\ProductStatus;
use App\Models\Product;

class GenerateOpeningTemplate
{
    /**
     * Returns the CSV contents as a string.
     * Each active product × active presentation gets one pre-filled row.
     * The user only needs to fill in: no_lote, fecha_vencimiento, cantidad, costo_unitario.
     */
    public function execute(): string
    {
        $products = Product::query()
            ->select(['id', 'commercial_name', 'internal_code', 'purchase_price'])
            ->with([
                'presentations' => fn ($q) => $q
                    ->where('is_active', true)
                    ->select(['id', 'product_id', 'name'])
                    ->orderByDesc('is_default')
                    ->orderBy('name'),
            ])
            ->where('status', ProductStatus::Active)
            ->orderBy('commercial_name')
            ->get();

        $output = fopen('php://temp', 'r+');

        // Instruction row — clearly marked so the parser knows to skip it
        fputcsv($output, [
            '# No modifiques codigo_interno, nombre_producto ni presentacion. Llena: no_lote (opcional), fecha_vencimiento (YYYY-MM-DD, opcional), cantidad* y costo_unitario. Omite filas con cantidad 0 o en blanco.',
        ]);

        fputcsv($output, [
            'codigo_interno',
            'nombre_producto',
            'presentacion',
            'no_lote',
            'fecha_vencimiento',
            'cantidad',
            'costo_unitario',
        ]);

        foreach ($products as $product) {
            foreach ($product->presentations as $presentation) {
                fputcsv($output, [
                    $product->internal_code ?? '',
                    $product->commercial_name,
                    $presentation->name,
                    '',                            // no_lote — user fills in
                    '',                            // fecha_vencimiento — user fills in
                    '',                            // cantidad — user fills in
                    $product->purchase_price ?? 0, // costo_unitario — pre-filled, user may override
                ]);
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
