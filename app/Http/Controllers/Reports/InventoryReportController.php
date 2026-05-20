<?php

namespace App\Http\Controllers\Reports;

use App\Enums\InventoryLotStatus;
use App\Models\InventoryLot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryReportController extends Controller
{
    public function index(Request $request): Response
    {
        $expiry = $request->string('expiry')->toString();

        $lots = InventoryLot::with(['product:id,commercial_name,internal_code', 'presentation:id,name'])
            ->when($expiry === 'expiring', fn ($q) => $q
                ->where('status', InventoryLotStatus::Available)
                ->whereBetween('expires_on', [today(), today()->addDays(90)])
            )
            ->when($expiry === 'expired', fn ($q) => $q
                ->where('status', InventoryLotStatus::Available)
                ->whereNotNull('expires_on')
                ->where('expires_on', '<', today())
            )
            ->when($expiry === '', fn ($q) => $q->where('status', InventoryLotStatus::Available))
            ->orderByRaw('expires_on IS NULL, expires_on ASC')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (InventoryLot $lot) => [
                'lot_number' => $lot->lot_number,
                'product' => $lot->product?->commercial_name ?? '—',
                'internal_code' => $lot->product?->internal_code ?? '—',
                'presentation' => $lot->presentation?->name ?? 'Unidad',
                'current_quantity' => $lot->current_quantity,
                'unit_cost' => $lot->unit_cost,
                'value' => $lot->current_quantity * $lot->unit_cost,
                'expires_on' => $lot->expires_on?->format('d/m/Y') ?? 'Sin vencimiento',
                'days_to_expiry' => $lot->expires_on ? today()->diffInDays($lot->expires_on, false) : null,
                'status' => $lot->status->label(),
            ]);

        $summary = [
            'total_units' => (int) InventoryLot::where('status', InventoryLotStatus::Available)->sum('current_quantity'),
            'total_value' => (int) InventoryLot::where('status', InventoryLotStatus::Available)
                ->selectRaw('SUM(current_quantity * unit_cost) as value')
                ->value('value'),
            'lots_count' => InventoryLot::where('status', InventoryLotStatus::Available)->count(),
            'expiring_30' => InventoryLot::where('status', InventoryLotStatus::Available)
                ->whereBetween('expires_on', [today(), today()->addDays(30)])
                ->count(),
            'expired' => InventoryLot::where('status', InventoryLotStatus::Available)
                ->whereNotNull('expires_on')
                ->where('expires_on', '<', today())
                ->count(),
        ];

        return Inertia::render('Reports/Inventory', [
            'filters' => ['expiry' => $expiry],
            'lots' => $lots,
            'summary' => $summary,
        ]);
    }
}
