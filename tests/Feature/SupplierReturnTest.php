<?php

use App\Enums\InventoryLotStatus;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function returnContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('purchases.view');
    Permission::findOrCreate('purchases.create');
    $user->givePermissionTo(['purchases.view', 'purchases.create']);

    $supplier = Supplier::factory()->for($tenant)->create(['name' => 'Proveedor Devoluciones']);
    $unit = ProductUnit::factory()->create(['code' => "ret-unit-{$tenant->id}"]);
    $product = Product::factory()->for($tenant)->for($unit, 'minimumUnit')->create([
        'commercial_name' => 'Amoxicilina 500mg',
        'purchase_price' => 800,
        'tax_rate' => 0,
    ]);
    $lot = InventoryLot::factory()->for($tenant)->for($product)->create([
        'lot_number' => 'LOT-DEV-001',
        'current_quantity' => 50,
        'initial_quantity' => 50,
        'unit_cost' => 800,
        'status' => InventoryLotStatus::Available,
    ]);

    return compact('tenant', 'user', 'supplier', 'product', 'lot');
}

test('guests cannot access supplier returns', function () {
    $this->get('/purchases/returns')->assertRedirect('/login');
});

test('authenticated users can list supplier returns', function () {
    ['user' => $user] = returnContext();

    $this->actingAs($user)
        ->get('/purchases/returns')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Purchases/Returns/Index'));
});

test('authenticated users can open the supplier return form', function () {
    ['user' => $user, 'supplier' => $supplier, 'product' => $product] = returnContext();

    $this->actingAs($user)
        ->get('/purchases/returns/create')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Purchases/Returns/Create')
            ->where('options.suppliers.0.id', $supplier->id)
            ->where('options.products.0.id', $product->id)
        );
});

test('a valid supplier return reduces inventory and creates movement', function () {
    ['tenant' => $tenant, 'user' => $user, 'supplier' => $supplier, 'product' => $product, 'lot' => $lot] = returnContext();

    $this->actingAs($user)
        ->post('/purchases/returns', [
            'supplier_id' => $supplier->id,
            'document_number' => 'DEV-001',
            'return_date' => '2026-05-26',
            'reason' => 'Producto vencido',
            'items' => [[
                'product_id' => $product->id,
                'inventory_lot_id' => $lot->id,
                'description' => 'Amoxicilina 500mg',
                'lot_number' => 'LOT-DEV-001',
                'quantity' => 10,
                'unit_cost' => 800,
                'tax_rate' => 0,
            ]],
        ])
        ->assertRedirect(route('purchases.returns.index'));

    expect(SupplierReturn::count())->toBe(1);
    expect(SupplierReturnItem::count())->toBe(1);
    expect($lot->fresh()->current_quantity)->toBe(40);

    $movement = InventoryMovement::latest()->first();
    expect($movement->quantity_delta)->toBe(-10)
        ->and($movement->quantity_after)->toBe(40);

    $return = SupplierReturn::first();
    expect($return->total)->toBe(8000)
        ->and($return->reason)->toBe('Producto vencido');
});

test('supplier return fails with insufficient stock', function () {
    ['user' => $user, 'supplier' => $supplier, 'product' => $product, 'lot' => $lot] = returnContext();

    $this->actingAs($user)
        ->post('/purchases/returns', [
            'supplier_id' => $supplier->id,
            'document_number' => 'DEV-002',
            'return_date' => '2026-05-26',
            'items' => [[
                'product_id' => $product->id,
                'inventory_lot_id' => $lot->id,
                'description' => 'Amoxicilina 500mg',
                'lot_number' => 'LOT-DEV-001',
                'quantity' => 999,
                'unit_cost' => 800,
                'tax_rate' => 0,
            ]],
        ])
        ->assertStatus(500);

    expect(SupplierReturn::count())->toBe(0);
    expect($lot->fresh()->current_quantity)->toBe(50);
});

test('supplier return detail page shows items correctly', function () {
    ['tenant' => $tenant, 'user' => $user, 'supplier' => $supplier, 'product' => $product, 'lot' => $lot] = returnContext();

    $this->actingAs($user)->post('/purchases/returns', [
        'supplier_id' => $supplier->id,
        'document_number' => 'DEV-003',
        'return_date' => '2026-05-26',
        'items' => [[
            'product_id' => $product->id,
            'inventory_lot_id' => $lot->id,
            'description' => 'Amoxicilina 500mg',
            'lot_number' => 'LOT-DEV-001',
            'quantity' => 5,
            'unit_cost' => 800,
            'tax_rate' => 0,
        ]],
    ]);

    $return = SupplierReturn::first();

    $this->actingAs($user)
        ->get("/purchases/returns/{$return->uuid}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Purchases/Returns/Show')
            ->where('return.document_number', 'DEV-003')
            ->where('return.total', 4000)
            ->has('return.items', 1)
        );
});
