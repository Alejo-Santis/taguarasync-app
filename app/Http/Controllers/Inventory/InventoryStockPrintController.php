<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryLot;
use App\Models\Laboratory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class InventoryStockPrintController extends Controller
{
    public function __invoke(Request $request): View
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
                'product.laboratory:id,name,nit',
                'presentation:id,name',
            ])
            ->when($laboratory, fn (Builder $query) => $query->whereHas('product', fn (Builder $query) => $query->where('laboratory_id', $laboratory->id)))
            ->when(! $includeZero, fn (Builder $query) => $query->where('current_quantity', '>', 0))
            ->join('products', 'inventory_lots.product_id', '=', 'products.id')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->orderBy('laboratories.name')
            ->orderBy('products.commercial_name')
            ->orderByRaw('inventory_lots.expires_on is null')
            ->orderBy('inventory_lots.expires_on')
            ->select('inventory_lots.*')
            ->get();

        $groups = $lots
            ->groupBy(fn (InventoryLot $lot) => $lot->product?->laboratory?->name ?? 'Sin laboratorio')
            ->map(function ($lots, string $laboratoryName): array {
                $firstLot = $lots->first();

                return [
                    'laboratory' => [
                        'name' => $laboratoryName,
                        'nit' => $firstLot?->product?->laboratory?->nit,
                    ],
                    'total_units' => $lots->sum('current_quantity'),
                    'total_value' => $lots->sum(fn (InventoryLot $lot) => $lot->current_quantity * $lot->unit_cost),
                    'lots' => $lots->map(fn (InventoryLot $lot) => [
                        'internal_code' => $lot->product?->internal_code,
                        'barcode' => $lot->product?->barcode,
                        'product' => $lot->product?->commercial_name,
                        'generic_name' => $lot->product?->generic_name,
                        'concentration' => $lot->product?->concentration,
                        'form' => $lot->product?->pharmaceutical_form,
                        'presentation' => $lot->presentation?->name,
                        'lot_number' => $lot->lot_number,
                        'expires_on' => $lot->expires_on?->format('d/m/Y') ?? 'Sin venc.',
                        'current_quantity' => $lot->current_quantity,
                        'minimum_stock' => $lot->product?->minimum_stock,
                        'unit_cost' => $lot->unit_cost,
                        'inventory_value' => $lot->current_quantity * $lot->unit_cost,
                    ])->values(),
                ];
            })
            ->values();

        return view('inventory.print-stock-by-laboratory', [
            'tenant' => $request->user()?->tenant,
            'groups' => $groups,
            'laboratory' => $laboratory,
            'includeZero' => $includeZero,
            'printedAt' => now(),
        ]);
    }
}
