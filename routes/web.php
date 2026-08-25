<?php

use App\Http\Controllers\Admin\TenantAdminController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Fe\FeSubmissionsController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\InventoryStockExportController;
use App\Http\Controllers\Inventory\InventoryStockPrintController;
use App\Http\Controllers\Inventory\InventoryTransferController;
use App\Http\Controllers\Inventory\KardexController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Pos\CashSessionController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Print\PrintController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Purchases\PurchaseOrderController;
use App\Http\Controllers\Purchases\PurchaseReceiptController;
use App\Http\Controllers\Purchases\SupplierPayableController;
use App\Http\Controllers\Purchases\SupplierReturnController;
use App\Http\Controllers\Reports\CashSessionReportController;
use App\Http\Controllers\Reports\ExportReportController;
use App\Http\Controllers\Reports\FiscalReportController;
use App\Http\Controllers\Reports\InventoryReportController;
use App\Http\Controllers\Reports\ProfitabilityReportController;
use App\Http\Controllers\Reports\PurchasesReportController;
use App\Http\Controllers\Reports\SalesReportController;
use App\Http\Controllers\Sales\CreditNoteController;
use App\Http\Controllers\Sales\CustomerReceivableController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\SalePaymentAttachmentController;
use App\Http\Controllers\Settings\ActiveIngredientController;
use App\Http\Controllers\Settings\BankAccountController;
use App\Http\Controllers\Settings\BranchController;
use App\Http\Controllers\Settings\CashRegisterController;
use App\Http\Controllers\Settings\FeResolutionController;
use App\Http\Controllers\Settings\FeSettingsController;
use App\Http\Controllers\Settings\LaboratoryController;
use App\Http\Controllers\Settings\PriceListController;
use App\Http\Controllers\Settings\ProductCategoryController;
use App\Http\Controllers\Settings\ProductUnitController;
use App\Http\Controllers\Settings\SupplierController;
use App\Http\Controllers\Sync\SyncStatusController;
use App\Http\Controllers\Team\TeamController;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// ── Super admin — gestión de tenants ──────────────────────────────────────
Route::middleware(['auth', EnsureSuperAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/tenants', [TenantAdminController::class, 'index'])->name('tenants.index');
    Route::post('/tenants', [TenantAdminController::class, 'store'])->name('tenants.store');
    Route::patch('/tenants/{tenant}/toggle-status', [TenantAdminController::class, 'toggleStatus'])->name('tenants.toggle-status');
    Route::post('/tenants/{tenant}/record-payment', [TenantAdminController::class, 'recordPayment'])->name('tenants.record-payment');
    Route::get('/tenants/{tenant}/users', [TenantAdminController::class, 'users'])->name('tenants.users');
    Route::put('/tenants/{tenant}/users/{user}', [TenantAdminController::class, 'updateUser'])->name('tenants.users.update');
    Route::post('/tenants/{tenant}/users/{user}/reset-password', [TenantAdminController::class, 'resetUserPassword'])->name('tenants.users.reset-password');
});

// ── Acceso general (cualquier usuario autenticado) ─────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
});

// ── Dashboard y búsqueda ───────────────────────────────────────────────────
Route::middleware(['auth', 'permission:dashboard.view'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', GlobalSearchController::class)->name('search');
});

// ── POS — vender ──────────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:pos.sell'])->prefix('pos')->name('pos.')->group(function () {
    Route::get('/', [PosController::class, 'index'])->name('index');
    Route::get('products', [PosController::class, 'search'])->name('products');
    Route::get('customers', [PosController::class, 'searchCustomers'])->name('customers');
    Route::post('customers', [PosController::class, 'quickStoreCustomer'])->name('customers.store');
    Route::post('sales', [PosController::class, 'store'])->name('sales.store');
    Route::get('recent-sales', [PosController::class, 'recentSales'])->name('recent-sales');
});

// ── POS — apertura de caja ────────────────────────────────────────────────
Route::middleware(['auth', 'permission:cash.register.open'])->prefix('pos')->name('pos.')->group(function () {
    Route::get('session/open', [CashSessionController::class, 'open'])->name('session.open');
    Route::post('session', [CashSessionController::class, 'store'])->name('session.store');
});

// ── POS — cierre de caja ──────────────────────────────────────────────────
Route::middleware(['auth', 'permission:cash.register.close'])->prefix('pos')->name('pos.')->group(function () {
    Route::get('session/{session}/close', [CashSessionController::class, 'close'])->name('session.close');
    Route::post('session/{session}/close', [CashSessionController::class, 'update'])->name('session.update');
});

// ── Clientes (ventas y cajeros los necesitan para FE) ─────────────────────
Route::middleware(['auth', 'permission:sales.view'])->prefix('customers')->name('customers.')->group(function () {
    Route::get('/', [CustomerController::class, 'index'])->name('index');
    Route::post('/', [CustomerController::class, 'store'])->name('store');
    Route::put('{customer}', [CustomerController::class, 'update'])->name('update');
    Route::patch('{customer}/toggle', [CustomerController::class, 'toggle'])->name('toggle');
});

// ── Productos — consulta ──────────────────────────────────────────────────
Route::middleware(['auth', 'permission:products.view'])->group(function () {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
});

// ── Productos — gestión ───────────────────────────────────────────────────
Route::middleware(['auth', 'permission:products.manage'])->group(function () {
    Route::get('products/import/template', [ProductController::class, 'downloadTemplate'])->name('products.import.template');
    Route::get('products/import', [ProductController::class, 'importForm'])->name('products.import');
    Route::post('products/import', [ProductController::class, 'import'])->name('products.import.store');
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::patch('products/{product}', [ProductController::class, 'update']);
});

// ── Inventario — consulta ─────────────────────────────────────────────────
Route::middleware(['auth', 'permission:inventory.view'])->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/kardex', [KardexController::class, 'index'])->name('inventory.kardex');
    Route::get('/inventory/kardex/export', [KardexController::class, 'export'])->name('inventory.kardex.export');
    Route::get('/inventory/print/stock-by-laboratory', InventoryStockPrintController::class)->name('inventory.print.stock-by-laboratory');
    Route::get('/inventory/export/stock-by-laboratory', InventoryStockExportController::class)->name('inventory.export.stock-by-laboratory');
});

// ── Traslados de inventario ───────────────────────────────────────────────
Route::middleware(['auth', 'permission:inventory.transfer'])->prefix('inventory')->name('inventory.')->group(function () {
    Route::get('transfers', [InventoryTransferController::class, 'index'])->name('transfers.index');
    Route::get('transfers/create', [InventoryTransferController::class, 'create'])->name('transfers.create');
    Route::get('transfers/lots', [InventoryTransferController::class, 'lots'])->name('transfers.lots');
    Route::post('transfers', [InventoryTransferController::class, 'store'])->name('transfers.store');
    Route::get('transfers/{transfer}', [InventoryTransferController::class, 'show'])->name('transfers.show');
    Route::post('transfers/{transfer}/confirm', [InventoryTransferController::class, 'confirm'])->name('transfers.confirm');
    Route::post('transfers/{transfer}/cancel', [InventoryTransferController::class, 'cancel'])->name('transfers.cancel');
});

// ── Inventario — ajustes y carga inicial ──────────────────────────────────
Route::middleware(['auth', 'permission:inventory.adjust'])->group(function () {
    Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    Route::get('/inventory/opening', [InventoryController::class, 'openingForm'])->name('inventory.opening');
    Route::get('/inventory/opening/template', [InventoryController::class, 'openingTemplate'])->name('inventory.opening.template');
    Route::post('/inventory/opening/import', [InventoryController::class, 'parseOpeningImport'])->name('inventory.opening.import');
    Route::post('/inventory/opening', [InventoryController::class, 'storeOpening'])->name('inventory.opening.store');
});

// ── Compras — consulta ────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:purchases.view'])->group(function () {
    Route::get('purchases', [PurchaseReceiptController::class, 'index'])->name('purchases.index');
    Route::get('purchases/{purchase}/attachment', [PurchaseReceiptController::class, 'attachment'])->name('purchases.attachment');
});
// Note: purchases/{purchase} show must come after all specific sub-routes (create, orders, returns, payables)

// ── Compras — crear ───────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:purchases.create'])->group(function () {
    Route::get('purchases/create', [PurchaseReceiptController::class, 'create'])->name('purchases.create');
    Route::post('purchases', [PurchaseReceiptController::class, 'store'])->name('purchases.store');
    Route::patch('purchases/{purchase}/cufe', [PurchaseReceiptController::class, 'updateCufe'])->name('purchases.update-cufe');
    Route::post('purchases/{purchase}/validate-radian', [PurchaseReceiptController::class, 'validateRadian'])->name('purchases.validate-radian');
});

// ── Órdenes de compra ─────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:purchases.view'])->group(function () {
    Route::get('purchases/orders', [PurchaseOrderController::class, 'index'])->name('purchases.orders.index');
});
Route::middleware(['auth', 'permission:purchases.create'])->group(function () {
    Route::get('purchases/orders/create', [PurchaseOrderController::class, 'create'])->name('purchases.orders.create');
    Route::post('purchases/orders', [PurchaseOrderController::class, 'store'])->name('purchases.orders.store');
    Route::post('purchases/orders/{order}/send', [PurchaseOrderController::class, 'send'])->name('purchases.orders.send');
    Route::post('purchases/orders/{order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchases.orders.cancel');
});
Route::middleware(['auth', 'permission:purchases.view'])->group(function () {
    Route::get('purchases/orders/{order}', [PurchaseOrderController::class, 'show'])->name('purchases.orders.show');
});

// ── Devoluciones a proveedor ──────────────────────────────────────────────
Route::middleware(['auth', 'permission:purchases.view'])->group(function () {
    Route::get('purchases/returns', [SupplierReturnController::class, 'index'])->name('purchases.returns.index');
});
Route::middleware(['auth', 'permission:purchases.create'])->group(function () {
    Route::get('purchases/returns/create', [SupplierReturnController::class, 'create'])->name('purchases.returns.create');
    Route::post('purchases/returns', [SupplierReturnController::class, 'store'])->name('purchases.returns.store');
});
Route::middleware(['auth', 'permission:purchases.view'])->group(function () {
    Route::get('purchases/returns/{return}', [SupplierReturnController::class, 'show'])->name('purchases.returns.show');
});

// ── Cuentas por pagar a proveedores ──────────────────────────────────────
Route::middleware(['auth', 'permission:purchases.view'])->group(function () {
    Route::get('purchases/payables', [SupplierPayableController::class, 'index'])->name('purchases.payables.index');
    Route::get('purchases/payables/{supplier}', [SupplierPayableController::class, 'show'])->name('purchases.payables.show');
});
Route::middleware(['auth', 'permission:purchases.create'])->group(function () {
    Route::post('purchases/payables/{supplier}/payments', [SupplierPayableController::class, 'storePayment'])->name('purchases.payables.payment');
});
Route::middleware(['auth', 'permission:purchases.view'])->group(function () {
    Route::get('purchases/{purchase}', [PurchaseReceiptController::class, 'show'])->name('purchases.show');
});

// ── Cartera de clientes (cuentas por cobrar) ──────────────────────────────
Route::middleware(['auth', 'permission:sales.view'])->group(function () {
    Route::get('sales/receivables', [CustomerReceivableController::class, 'index'])->name('sales.receivables.index');
    Route::get('sales/receivables/{customer}', [CustomerReceivableController::class, 'show'])->name('sales.receivables.show');
});
Route::middleware(['auth', 'permission:sales.cancel'])->group(function () {
    Route::post('sales/receivables/{customer}/collections', [CustomerReceivableController::class, 'storeCollection'])->name('sales.receivables.collection');
});

// ── Ventas — consulta y recibo ────────────────────────────────────────────
Route::middleware(['auth', 'permission:sales.view'])->prefix('sales')->name('sales.')->group(function () {
    Route::get('/', [SaleController::class, 'index'])->name('index');
    Route::get('payments/{salePayment}/attachment', [SalePaymentAttachmentController::class, 'show'])->name('payments.attachment');
    Route::get('{sale}', [SaleController::class, 'show'])->name('show');
    Route::get('{sale}/receipt', [SaleController::class, 'receipt'])->name('receipt');
});

// ── Ventas — anulación ────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:sales.cancel'])->prefix('sales')->name('sales.')->group(function () {
    Route::post('{sale}/void', [SaleController::class, 'void'])->name('void');
});

// ── FE — panel de transmisiones ───────────────────────────────────────────
Route::middleware(['auth', 'permission:billing.view'])->prefix('fe')->name('fe.')->group(function () {
    Route::get('submissions', [FeSubmissionsController::class, 'index'])->name('submissions.index');
    Route::post('submissions/{submission}/retry', [FeSubmissionsController::class, 'retry'])->name('submissions.retry');
});

// ── Ventas — FE y notas crédito ───────────────────────────────────────────
Route::middleware(['auth', 'permission:billing.resend'])->prefix('sales')->name('sales.')->group(function () {
    Route::post('{sale}/retry-fe', [SaleController::class, 'retryFe'])->name('retry-fe');
    Route::get('{sale}/credit-notes/create', [CreditNoteController::class, 'create'])->name('credit-notes.create');
    Route::post('{sale}/credit-notes', [CreditNoteController::class, 'store'])->name('credit-notes.store');
});

// ── Reportes ──────────────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:reports.view'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('sales', [SalesReportController::class, 'index'])->name('sales');
    Route::get('fiscal', [FiscalReportController::class, 'index'])->name('fiscal');
    Route::get('fiscal/export', [ExportReportController::class, 'fiscal'])->name('fiscal.export');
    Route::get('banks/export', [ExportReportController::class, 'bankMovements'])->name('banks.export');
    Route::get('suppliers/{supplier}/statement', [ExportReportController::class, 'supplierStatement'])->name('suppliers.statement');
    Route::get('inventory', [InventoryReportController::class, 'index'])->name('inventory');
    Route::get('purchases', [PurchasesReportController::class, 'index'])->name('purchases');
    Route::get('cash-sessions', [CashSessionReportController::class, 'index'])->name('cash-sessions');
    Route::get('cash-sessions/{session}', [CashSessionReportController::class, 'show'])->name('cash-sessions.show');
    Route::get('profitability', [ProfitabilityReportController::class, 'index'])->name('profitability');
});

// ── Auditorías ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:reports.view'])->group(function () {
    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
});

// ── Equipo ────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:users.manage'])->prefix('team')->name('team.')->group(function () {
    Route::get('/', [TeamController::class, 'index'])->name('index');
    Route::post('/', [TeamController::class, 'store'])->name('store');
    Route::put('{member}', [TeamController::class, 'update'])->name('update');
    Route::post('{member}/reset-password', [TeamController::class, 'resetPassword'])->name('reset-password');
});

// ── Sucursales ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:settings.manage'])->prefix('settings')->name('settings.')->group(function () {
    Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
    Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
    Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::patch('branches/{branch}/toggle', [BranchController::class, 'toggle'])->name('branches.toggle');
});

// ── Configuración — catálogos ─────────────────────────────────────────────
Route::middleware(['auth', 'permission:settings.manage'])->prefix('settings')->name('settings.')->group(function () {
    Route::redirect('/', '/settings/laboratories');

    Route::get('laboratories', [LaboratoryController::class, 'index'])->name('laboratories.index');
    Route::post('laboratories', [LaboratoryController::class, 'store'])->name('laboratories.store');
    Route::put('laboratories/{laboratory}', [LaboratoryController::class, 'update'])->name('laboratories.update');
    Route::patch('laboratories/{laboratory}/toggle', [LaboratoryController::class, 'toggle'])->name('laboratories.toggle');

    Route::get('categories', [ProductCategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [ProductCategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [ProductCategoryController::class, 'update'])->name('categories.update');
    Route::patch('categories/{category}/toggle', [ProductCategoryController::class, 'toggle'])->name('categories.toggle');

    Route::get('units', [ProductUnitController::class, 'index'])->name('units.index');
    Route::post('units', [ProductUnitController::class, 'store'])->name('units.store');
    Route::put('units/{unit}', [ProductUnitController::class, 'update'])->name('units.update');
    Route::patch('units/{unit}/toggle', [ProductUnitController::class, 'toggle'])->name('units.toggle');

    Route::get('active-ingredients', [ActiveIngredientController::class, 'index'])->name('active-ingredients.index');
    Route::post('active-ingredients', [ActiveIngredientController::class, 'store'])->name('active-ingredients.store');
    Route::put('active-ingredients/{ingredient}', [ActiveIngredientController::class, 'update'])->name('active-ingredients.update');
    Route::delete('active-ingredients/{ingredient}', [ActiveIngredientController::class, 'destroy'])->name('active-ingredients.destroy');

    Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::patch('suppliers/{supplier}/toggle', [SupplierController::class, 'toggle'])->name('suppliers.toggle');

    Route::get('registers', [CashRegisterController::class, 'index'])->name('registers.index');
    Route::post('registers', [CashRegisterController::class, 'store'])->name('registers.store');
    Route::put('registers/{register}', [CashRegisterController::class, 'update'])->name('registers.update');
    Route::patch('registers/{register}/toggle', [CashRegisterController::class, 'toggle'])->name('registers.toggle');

    Route::get('banks', [BankAccountController::class, 'index'])->name('banks.index');
    Route::post('banks', [BankAccountController::class, 'store'])->name('banks.store');
    Route::put('banks/{bankAccount}', [BankAccountController::class, 'update'])->name('banks.update');
    Route::patch('banks/{bankAccount}/toggle', [BankAccountController::class, 'toggle'])->name('banks.toggle');
    Route::patch('banks/movements/{movement}/reconcile', [BankAccountController::class, 'reconcileMovement'])->name('banks.movements.reconcile');

    Route::get('price-lists', [PriceListController::class, 'index'])->name('price-lists.index');
    Route::post('price-lists', [PriceListController::class, 'store'])->name('price-lists.store');
    Route::put('price-lists/{priceList}', [PriceListController::class, 'update'])->name('price-lists.update');
    Route::patch('price-lists/{priceList}/toggle', [PriceListController::class, 'toggle'])->name('price-lists.toggle');
    Route::get('price-lists/{priceList}', [PriceListController::class, 'show'])->name('price-lists.show');
    Route::post('price-lists/{priceList}/items', [PriceListController::class, 'upsertItem'])->name('price-lists.items.upsert');
    Route::delete('price-lists/{priceList}/items/{item}', [PriceListController::class, 'removeItem'])->name('price-lists.items.remove');
    Route::get('price-lists/{priceList}/import', [PriceListController::class, 'importForm'])->name('price-lists.import');
    Route::get('price-lists/import/template', [PriceListController::class, 'downloadTemplate'])->name('price-lists.import.template');
    Route::post('price-lists/{priceList}/import', [PriceListController::class, 'import'])->name('price-lists.import.store');
});

// ── Impresión QZ Tray ─────────────────────────────────────────────────────
// certificate es público (QZ lo pide sin sesión); sign requiere auth
Route::get('/print/certificate', [PrintController::class, 'certificate'])->name('print.certificate');
Route::middleware('auth')->post('/print/sign', [PrintController::class, 'sign'])->name('print.sign');
Route::middleware(['auth', 'permission:settings.manage'])->group(function () {
    Route::get('/settings/printer', [PrintController::class, 'index'])->name('settings.printer.index');
    Route::put('/settings/printer/{register}', [PrintController::class, 'update'])->name('settings.printer.update');
});

// ── Sync — estado de conectividad para el frontend (modo local) ───────────
Route::middleware('auth')->get('/sync/status', SyncStatusController::class)->name('sync.status');

// ── Configuración — facturación electrónica ───────────────────────────────
Route::middleware(['auth', 'permission:billing.configure'])->prefix('settings')->name('settings.')->group(function () {
    Route::get('fe', [FeSettingsController::class, 'index'])->name('fe.index');
    Route::put('fe', [FeSettingsController::class, 'update'])->name('fe.update');
    Route::post('fe/test-connection', [FeSettingsController::class, 'testConnection'])->name('fe.test-connection');
    Route::post('fe/resolutions', [FeResolutionController::class, 'store'])->name('fe.resolutions.store');
    Route::put('fe/resolutions/{feResolution}', [FeResolutionController::class, 'update'])->name('fe.resolutions.update');
    Route::patch('fe/resolutions/{feResolution}/toggle', [FeResolutionController::class, 'toggle'])->name('fe.resolutions.toggle');
});
