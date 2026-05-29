<?php

namespace App\Http\Controllers;

use App\Enums\InventoryLotStatus;
use App\Enums\PaymentForm;
use App\Enums\PurchaseRadianStatus;
use App\Enums\PurchaseReceiptStatus;
use App\Enums\SaleStatus;
use App\Models\BankAccountMovement;
use App\Models\CashRegister;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\TenantFeConfig;
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
                'credit_overdue' => Sale::where('payment_form', PaymentForm::Credit)
                    ->where('status', SaleStatus::Completed)
                    ->whereDate('payment_due_date', '<', $today)
                    ->count(),
            ],
            'setup' => $this->setupChecklist(),
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

    /**
     * @return array{complete: bool, steps: array<int, array{key: string, label: string, description: string, done: bool, href: string}>}
     */
    private function setupChecklist(): array
    {
        $steps = [
            [
                'key' => 'cash_register',
                'label' => 'Crear caja registradora',
                'description' => 'Necesaria para abrir el POS. Asígnala a una sucursal.',
                'done' => CashRegister::where('is_active', true)->exists(),
                'href' => '/settings/registers',
            ],
            [
                'key' => 'supplier',
                'label' => 'Agregar un proveedor',
                'description' => 'Indispensable para recibir compras e inventario.',
                'done' => Supplier::where('is_active', true)->exists(),
                'href' => '/settings/suppliers',
            ],
            [
                'key' => 'products',
                'label' => 'Cargar el catálogo de productos',
                'description' => 'Crea productos manualmente o importa desde CSV.',
                'done' => Product::exists(),
                'href' => '/products',
            ],
            [
                'key' => 'first_stock',
                'label' => 'Cargar inventario inicial',
                'description' => 'Registra tu stock existente o recibe tu primera compra. Selecciona la sucursal destino.',
                'done' => InventoryLot::where('current_quantity', '>', 0)->exists(),
                'href' => '/inventory/opening',
            ],
            [
                'key' => 'first_sale',
                'label' => 'Realizar la primera venta',
                'description' => 'Abre una sesión POS y procesa tu primera transacción.',
                'done' => Sale::exists(),
                'href' => '/pos',
            ],
            [
                'key' => 'printer',
                'label' => 'Configurar impresora térmica',
                'description' => 'Conecta QZ Tray y asigna la impresora a tu caja para imprimir recibos.',
                'done' => CashRegister::whereNotNull('printer_name')->exists(),
                'href' => '/settings/printer',
            ],
            [
                'key' => 'fe_config',
                'label' => 'Configurar facturación electrónica',
                'description' => 'Ingresa tus credenciales DIAN/Nextpyme para emitir facturas electrónicas.',
                'done' => TenantFeConfig::whereNotNull('api_token')->exists(),
                'href' => '/settings/fe',
            ],
        ];

        return [
            'complete' => collect($steps)->every(fn (array $s) => $s['done']),
            'steps' => $steps,
        ];
    }
}
