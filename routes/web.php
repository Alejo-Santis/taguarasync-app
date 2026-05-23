<?php

use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Fe\FeSubmissionsController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\InventoryStockPrintController;
use App\Http\Controllers\Inventory\KardexController;
use App\Http\Controllers\Pos\CashSessionController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Purchases\PurchaseReceiptController;
use App\Http\Controllers\Reports\CashSessionReportController;
use App\Http\Controllers\Reports\InventoryReportController;
use App\Http\Controllers\Reports\PurchasesReportController;
use App\Http\Controllers\Reports\SalesReportController;
use App\Http\Controllers\Sales\CreditNoteController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Settings\ActiveIngredientController;
use App\Http\Controllers\Settings\CashRegisterController;
use App\Http\Controllers\Settings\FeResolutionController;
use App\Http\Controllers\Settings\FeSettingsController;
use App\Http\Controllers\Settings\LaboratoryController;
use App\Http\Controllers\Settings\ProductCategoryController;
use App\Http\Controllers\Settings\ProductUnitController;
use App\Http\Controllers\Settings\SupplierController;
use App\Http\Controllers\Team\TeamController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// ── Acceso general (cualquier usuario autenticado) ─────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
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
    Route::get('/inventory/print/stock-by-laboratory', InventoryStockPrintController::class)->name('inventory.print.stock-by-laboratory');
});

// ── Inventario — ajustes ──────────────────────────────────────────────────
Route::middleware(['auth', 'permission:inventory.adjust'])->group(function () {
    Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
});

// ── Compras — consulta ────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:purchases.view'])->group(function () {
    Route::get('purchases', [PurchaseReceiptController::class, 'index'])->name('purchases.index');
});

// ── Compras — crear ───────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:purchases.create'])->group(function () {
    Route::get('purchases/create', [PurchaseReceiptController::class, 'create'])->name('purchases.create');
    Route::post('purchases', [PurchaseReceiptController::class, 'store'])->name('purchases.store');
});

// ── Ventas — consulta y recibo ────────────────────────────────────────────
Route::middleware(['auth', 'permission:sales.view'])->prefix('sales')->name('sales.')->group(function () {
    Route::get('/', [SaleController::class, 'index'])->name('index');
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
    Route::get('inventory', [InventoryReportController::class, 'index'])->name('inventory');
    Route::get('purchases', [PurchasesReportController::class, 'index'])->name('purchases');
    Route::get('cash-sessions', [CashSessionReportController::class, 'index'])->name('cash-sessions');
    Route::get('cash-sessions/{session}', [CashSessionReportController::class, 'show'])->name('cash-sessions.show');
});

// ── Equipo ────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:users.manage'])->prefix('team')->name('team.')->group(function () {
    Route::get('/', [TeamController::class, 'index'])->name('index');
    Route::post('/', [TeamController::class, 'store'])->name('store');
    Route::put('{member}', [TeamController::class, 'update'])->name('update');
    Route::post('{member}/reset-password', [TeamController::class, 'resetPassword'])->name('reset-password');
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
});

// ── Configuración — facturación electrónica ───────────────────────────────
Route::middleware(['auth', 'permission:billing.configure'])->prefix('settings')->name('settings.')->group(function () {
    Route::get('fe', [FeSettingsController::class, 'index'])->name('fe.index');
    Route::put('fe', [FeSettingsController::class, 'update'])->name('fe.update');
    Route::post('fe/resolutions', [FeResolutionController::class, 'store'])->name('fe.resolutions.store');
    Route::put('fe/resolutions/{feResolution}', [FeResolutionController::class, 'update'])->name('fe.resolutions.update');
    Route::patch('fe/resolutions/{feResolution}/toggle', [FeResolutionController::class, 'toggle'])->name('fe.resolutions.toggle');
});
