<?php

namespace App\Actions\Reports;

use App\Enums\SaleStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BuildProfitabilityReport
{
    /**
     * @return array{
     *     summary: array{revenue: int, cost: int, margin: int, margin_pct: float, units_sold: int, products_count: int},
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function execute(int $tenantId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('sale_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->leftJoin('inventory_lots as il', 'il.id', '=', 'si.inventory_lot_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.product_category_id')
            ->where('si.tenant_id', $tenantId)
            ->where('s.status', SaleStatus::Completed->value)
            ->whereDate('s.created_at', '>=', $from->toDateString())
            ->whereDate('s.created_at', '<=', $to->toDateString())
            ->groupBy('p.id', 'p.commercial_name', 'p.generic_name', 'p.pharmaceutical_form', 'p.concentration', 'pc.name')
            ->orderByRaw('SUM(si.line_subtotal) - SUM(si.quantity * COALESCE(il.unit_cost, 0)) DESC')
            ->select([
                'p.commercial_name',
                'p.generic_name',
                'p.pharmaceutical_form',
                'p.concentration',
                'pc.name as category',
            ])
            ->selectRaw('COUNT(DISTINCT si.sale_id) as invoice_count')
            ->selectRaw('SUM(si.quantity) as units_sold')
            ->selectRaw('SUM(si.line_subtotal) as revenue')
            ->selectRaw('SUM(si.quantity * COALESCE(il.unit_cost, 0)) as cost')
            ->selectRaw('SUM(si.line_subtotal) - SUM(si.quantity * COALESCE(il.unit_cost, 0)) as margin')
            ->get()
            ->map(function (object $row): array {
                $revenue = (int) $row->revenue;
                $cost = (int) $row->cost;
                $margin = $revenue - $cost;

                return [
                    'product' => $row->commercial_name,
                    'generic_name' => $row->generic_name,
                    'form' => implode(' · ', array_filter([$row->pharmaceutical_form, $row->concentration])),
                    'category' => $row->category,
                    'invoice_count' => (int) $row->invoice_count,
                    'units_sold' => (int) $row->units_sold,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'margin' => $margin,
                    'margin_pct' => $revenue > 0 ? round(($margin / $revenue) * 100, 1) : 0.0,
                ];
            });

        $totalRevenue = (int) $rows->sum('revenue');
        $totalCost = (int) $rows->sum('cost');
        $totalMargin = $totalRevenue - $totalCost;

        return [
            'summary' => [
                'revenue' => $totalRevenue,
                'cost' => $totalCost,
                'margin' => $totalMargin,
                'margin_pct' => $totalRevenue > 0 ? round(($totalMargin / $totalRevenue) * 100, 1) : 0.0,
                'units_sold' => (int) $rows->sum('units_sold'),
                'products_count' => $rows->count(),
            ],
            'rows' => $rows->values()->all(),
        ];
    }
}
