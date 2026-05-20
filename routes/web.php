<?php

use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Pos\CashSessionController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Purchases\PurchaseReceiptController;
use App\Http\Controllers\Reports\InventoryReportController;
use App\Http\Controllers\Reports\PurchasesReportController;
use App\Http\Controllers\Reports\SalesReportController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Settings\ActiveIngredientController;
use App\Http\Controllers\Settings\CashRegisterController;
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

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');
Route::get('/profile', [ProfileController::class, 'index'])->middleware(['auth'])->name('profile');

Route::middleware(['auth'])->group(function () {
    Route::get('products/import/template', [ProductController::class, 'downloadTemplate'])->name('products.import.template');
    Route::get('products/import', [ProductController::class, 'importForm'])->name('products.import');
    Route::post('products/import', [ProductController::class, 'import'])->name('products.import.store');
});

Route::resource('products', ProductController::class)
    ->only('index', 'create', 'store', 'edit', 'update')
    ->middleware(['auth']);

Route::middleware(['auth'])->prefix('pos')->name('pos.')->group(function () {
    Route::get('/', [PosController::class, 'index'])->name('index');
    Route::get('products', [PosController::class, 'search'])->name('products');
    Route::post('sales', [PosController::class, 'store'])->name('sales.store');

    Route::get('session/open', [CashSessionController::class, 'open'])->name('session.open');
    Route::post('session', [CashSessionController::class, 'store'])->name('session.store');
    Route::get('session/{session}/close', [CashSessionController::class, 'close'])->name('session.close');
    Route::post('session/{session}/close', [CashSessionController::class, 'update'])->name('session.update');
});

Route::get('/inventory', [InventoryController::class, 'index'])
    ->middleware(['auth'])
    ->name('inventory.index');

Route::middleware(['auth'])->prefix('settings')->name('settings.')->group(function () {
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

Route::middleware(['auth'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('sales', [SalesReportController::class, 'index'])->name('sales');
    Route::get('inventory', [InventoryReportController::class, 'index'])->name('inventory');
    Route::get('purchases', [PurchasesReportController::class, 'index'])->name('purchases');
});

Route::middleware(['auth'])->prefix('team')->name('team.')->group(function () {
    Route::get('/', [TeamController::class, 'index'])->name('index');
    Route::post('/', [TeamController::class, 'store'])->name('store');
    Route::put('{member}', [TeamController::class, 'update'])->name('update');
    Route::post('{member}/reset-password', [TeamController::class, 'resetPassword'])->name('reset-password');
});

Route::resource('purchases', PurchaseReceiptController::class)
    ->only('index', 'create', 'store')
    ->middleware(['auth']);

Route::middleware(['auth'])->prefix('sales')->name('sales.')->group(function () {
    Route::get('/', [SaleController::class, 'index'])->name('index');
    Route::get('{sale}', [SaleController::class, 'show'])->name('show');
    Route::post('{sale}/void', [SaleController::class, 'void'])->name('void');
    Route::get('{sale}/receipt', [SaleController::class, 'receipt'])->name('receipt');
});
