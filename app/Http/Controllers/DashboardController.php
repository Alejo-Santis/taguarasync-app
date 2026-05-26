<?php

namespace App\Http\Controllers;

use App\Enums\InventoryLotStatus;
use App\Enums\PurchaseRadianStatus;
use App\Enums\PurchaseReceiptStatus;
use App\Models\BankAccountMovement;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $today = today();
        $monthStart = now()->startOfMonth();

        $salesLast7 = collect(range(6, 0))->map(function (int $daysAgo) {
            $date = today()->subDays($daysAgo);

            return [
                'label' => $date->isoFormat('ddd'),
                'total' => (int) Sale::whereDate('created_at', $date)->sum('total'),
                'count' => Sale::whereDate('created_at', $date)->count(),
            ];
        })->values();

        return Inertia::render('Dashboard', [
            'kpis' => [
                'sales_today' => (int) Sale::whereDate('created_at', $today)->sum('total'),
                'sales_today_count' => Sale::whereDate('created_at', $today)->count(),
                'inventory_units' => (int) InventoryLot::where('status', InventoryLotStatus::Available)->sum('current_quantity'),
                'inventory_lots' => InventoryLot::where('status', InventoryLotStatus::Available)->count(),
                'expiring_soon' => InventoryLot::where('status', InventoryLotStatus::Available)
                    ->whereBetween('expires_on', [$today, $today->copy()->addDays(90)])
                    ->count(),
                'purchases_month' => PurchaseReceipt::where('status', PurchaseReceiptStatus::Received)
                    ->where('created_at', '>=', $monthStart)
                    ->count(),
                'purchases_month_total' => (int) PurchaseReceipt::where('status', PurchaseReceiptStatus::Received)
                    ->where('created_at', '>=', $monthStart)
                    ->sum('total'),
            ],
            'alerts' => [
                'expiring_lots' => InventoryLot::where('status', InventoryLotStatus::Available)
                    ->whereBetween('expires_on', [$today, $today->copy()->addDays(30)])
                    ->count(),
                'expired_lots' => InventoryLot::where('status', InventoryLotStatus::Available)
                    ->whereNotNull('expires_on')
                    ->where('expires_on', '<', $today)
                    ->count(),
                'low_stock' => Product::where('minimum_stock', '>', 0)
                    ->whereRaw(
                        'minimum_stock > (SELECT COALESCE(SUM(il.current_quantity), 0) FROM inventory_lots il WHERE il.product_id = products.id AND il.status = ?)',
                        [InventoryLotStatus::Available->value]
                    )
                    ->count(),
                'radian_pending' => PurchaseReceipt::where('radian_status', PurchaseRadianStatus::Pending)->count(),
                'bank_differences' => BankAccountMovement::where('status', 'difference')->count(),
            ],
            'salesLast7' => $salesLast7,
            'recentSales' => Sale::with('cashSession.register')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Sale $s) => [
                    'document_number' => $s->document_number,
                    'total' => $s->total,
                    'payment_method' => $s->payment_method->label(),
                    'at' => $s->created_at->format('H:i d/m'),
                ]),
        ]);
    }
}
