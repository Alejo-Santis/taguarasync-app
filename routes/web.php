<?php

use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Purchases\PurchaseReceiptController;
use App\Http\Controllers\Settings\ActiveIngredientController;
use App\Http\Controllers\Settings\LaboratoryController;
use App\Http\Controllers\Settings\ProductCategoryController;
use App\Http\Controllers\Settings\ProductUnitController;
use App\Http\Controllers\Settings\SupplierController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth'])->name('dashboard');

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
});

Route::resource('purchases', PurchaseReceiptController::class)
    ->only('index', 'create', 'store')
    ->middleware(['auth']);
