<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryLot;
use App\Models\Laboratory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryStockExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'laboratory_id' => ['nullable', 'integer'],
            'include_zero' => ['nullable', 'boolean'],
        ]);

        $laboratory = null;
        if (! empty($validated['laboratory_id'])) {
            $laboratory = Laboratory::query()->findOrFail($validated['laboratory_id']);
        }

        $includeZero = (bool) ($validated['include_zero'] ?? false);

        $lots = InventoryLot::query()
            ->with([
                'product:id,laboratory_id,internal_code,barcode,commercial_name,generic_name,concentration,pharmaceutical_form,minimum_stock',
                'product.laboratory:id,name',
                'presentation:id,name',
                'branch:id,name',
            ])
            ->when($laboratory, fn (Builder $query) => $query->whereHas('product', fn (Builder $query) => $query->where('laboratory_id', $laboratory->id)))
            ->when(! $includeZero, fn (Builder $query) => $query->where('current_quantity', '>', 0));

        $filename = 'inventario'.($laboratory ? '-'.str($laboratory->name)->slug() : '-todos-laboratorios').'-'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($lots): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Laboratorio', 'Codigo interno', 'Codigo barras', 'Producto', 'Nombre generico',
                'Concentracion', 'Forma', 'Presentacion', 'Lote', 'Vencimiento', 'Sucursal',
                'Cantidad', 'Stock minimo', 'Costo unitario', 'Valor inventario',
            ]);

            $lots->chunkById(500, function ($chunk) use ($handle): void {
                foreach ($chunk as $lot) {
                    fputcsv($handle, [
                        $lot->product?->laboratory?->name,
                        $lot->product?->internal_code,
                        $lot->product?->barcode,
                        $lot->product?->commercial_name,
                        $lot->product?->generic_name,
                        $lot->product?->concentration,
                        $lot->product?->pharmaceutical_form,
                        $lot->presentation?->name,
                        $lot->lot_number,
                        $lot->expires_on?->format('Y-m-d') ?? '',
                        $lot->branch?->name,
                        $lot->current_quantity,
                        $lot->product?->minimum_stock,
                        $lot->unit_cost,
                        $lot->current_quantity * $lot->unit_cost,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
