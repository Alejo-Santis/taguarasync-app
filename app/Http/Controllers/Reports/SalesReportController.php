<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalesReportController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $request->date('from', 'Y-m-d') ?? today()->startOfMonth();
        $to = $request->date('to', 'Y-m-d') ?? today();

        $salesQuery = Sale::whereBetween(DB::raw('DATE(created_at)'), [$from, $to]);

        $totals = [
            'sales_count' => (int) $salesQuery->count(),
            'gross_total' => (int) $salesQuery->sum('total'),
            'subtotal' => (int) $salesQuery->sum('subtotal'),
            'tax_total' => (int) $salesQuery->sum('tax_total'),
            'by_method' => collect(PaymentMethod::cases())->map(fn ($m) => [
                'method' => $m->label(),
                'count' => (int) (clone $salesQuery)->where('payment_method', $m)->count(),
                'total' => (int) (clone $salesQuery)->where('payment_method', $m)->sum('total'),
            ])->values(),
        ];

        $byDay = Sale::whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date' => $r->date,
                'count' => (int) $r->count,
                'total' => (int) $r->total,
            ]);

        $topProducts = SaleItem::whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->selectRaw('description, SUM(quantity) as qty_sold, SUM(line_total) as revenue')
            ->groupBy('description')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'description' => $r->description,
                'qty_sold' => (int) $r->qty_sold,
                'revenue' => (int) $r->revenue,
            ]);

        return Inertia::render('Reports/Sales', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => $totals,
            'byDay' => $byDay,
            'topProducts' => $topProducts,
        ]);
    }
}
