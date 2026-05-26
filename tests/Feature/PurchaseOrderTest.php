<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function orderContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('purchases.view');
    Permission::findOrCreate('purchases.create');
    $user->givePermissionTo(['purchases.view', 'purchases.create']);

    $supplier = Supplier::factory()->for($tenant)->create(['name' => 'Proveedor OC']);
    $unit = ProductUnit::factory()->create(['code' => "oc-unit-{$tenant->id}"]);
    $product = Product::factory()->for($tenant)->for($unit, 'minimumUnit')->create([
        'commercial_name' => 'Paracetamol 1g',
        'purchase_price' => 500,
        'tax_rate' => 0,
    ]);

    return compact('tenant', 'user', 'supplier', 'product');
}

test('guests cannot access purchase orders', function () {
    $this->get('/purchases/orders')->assertRedirect('/login');
});

test('authenticated users can list purchase orders', function () {
    ['user' => $user] = orderContext();

    $this->actingAs($user)
        ->get('/purchases/orders')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Purchases/Orders/Index'));
});

test('authenticated users can open the purchase order form', function () {
    ['user' => $user, 'supplier' => $supplier, 'product' => $product] = orderContext();

    $this->actingAs($user)
        ->get('/purchases/orders/create')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Purchases/Orders/Create')
            ->where('options.suppliers.0.id', $supplier->id)
            ->where('options.products.0.id', $product->id)
        );
});

test('a valid purchase order is created with items and draft status', function () {
    ['user' => $user, 'supplier' => $supplier, 'product' => $product] = orderContext();

    $this->actingAs($user)
        ->post('/purchases/orders', [
            'supplier_id' => $supplier->id,
            'order_number' => 'OC-001',
            'order_date' => '2026-05-26',
            'expected_date' => '2026-06-05',
            'items' => [[
                'product_id' => $product->id,
                'description' => 'Paracetamol 1g',
                'quantity' => 100,
                'unit_cost' => 500,
                'tax_rate' => 0,
            ]],
        ])
        ->assertRedirect(route('purchases.orders.index'));

    expect(PurchaseOrder::count())->toBe(1);
    expect(PurchaseOrderItem::count())->toBe(1);

    $order = PurchaseOrder::first();
    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->total)->toBe(50000)
        ->and($order->order_number)->toBe('OC-001');
});

test('purchase order can be sent', function () {
    ['user' => $user, 'supplier' => $supplier, 'product' => $product] = orderContext();

    $this->actingAs($user)->post('/purchases/orders', [
        'supplier_id' => $supplier->id,
        'order_number' => 'OC-002',
        'order_date' => '2026-05-26',
        'items' => [['product_id' => $product->id, 'description' => 'Paracetamol 1g', 'quantity' => 10, 'unit_cost' => 500, 'tax_rate' => 0]],
    ]);

    $order = PurchaseOrder::first();

    $this->actingAs($user)
        ->post("/purchases/orders/{$order->uuid}/send")
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Sent);
});

test('purchase order detail page renders correctly', function () {
    ['user' => $user, 'supplier' => $supplier, 'product' => $product] = orderContext();

    $this->actingAs($user)->post('/purchases/orders', [
        'supplier_id' => $supplier->id,
        'order_number' => 'OC-003',
        'order_date' => '2026-05-26',
        'items' => [['product_id' => $product->id, 'description' => 'Paracetamol 1g', 'quantity' => 5, 'unit_cost' => 500, 'tax_rate' => 0]],
    ]);

    $order = PurchaseOrder::first();

    $this->actingAs($user)
        ->get("/purchases/orders/{$order->uuid}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Purchases/Orders/Show')
            ->where('order.order_number', 'OC-003')
            ->where('order.total', 2500)
            ->has('order.items', 1)
        );
});
