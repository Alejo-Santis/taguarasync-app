<?php

use App\Enums\PurchaseRadianStatus;
use App\Enums\PurchaseReceiptStatus;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('guest users are redirected away from purchases', function () {
    $this->get('/purchases')->assertRedirect('/login');
});

test('authenticated users can list purchase receipts for their tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create(['name' => 'Admin Compras']);
    Permission::findOrCreate('purchases.view');
    $user->givePermissionTo('purchases.view');
    $supplier = Supplier::factory()->for($tenant)->create([
        'name' => 'Drogueria Mayorista Caribe',
        'nit' => '900123456-7',
    ]);
    $otherSupplier = Supplier::factory()->for($otherTenant)->create();
    $unit = ProductUnit::factory()->create([
        'name' => 'Unidad',
        'code' => 'unit-purchase-index',
    ]);
    $product = Product::factory()
        ->for($tenant)
        ->for($unit, 'minimumUnit')
        ->create(['commercial_name' => 'Acetaminofen 500mg']);
    $presentation = ProductPresentation::factory()
        ->for($tenant)
        ->for($product)
        ->for($unit, 'unit')
        ->create(['name' => 'Caja x 100', 'minimum_unit_quantity' => 100]);
    $receipt = PurchaseReceipt::factory()
        ->for($tenant)
        ->for($supplier)
        ->for($user)
        ->create([
            'document_number' => 'REM-INDEX-1001',
            'document_date' => '2026-05-18',
            'subtotal' => 36000,
            'tax_total' => 0,
            'total' => 36000,
            'status' => PurchaseReceiptStatus::Received,
            'radian_status' => PurchaseRadianStatus::Pending,
            'notes' => 'Documento fisico recibido',
        ]);

    PurchaseReceiptItem::create([
        'tenant_id' => $tenant->id,
        'purchase_receipt_id' => $receipt->id,
        'product_id' => $product->id,
        'product_presentation_id' => $presentation->id,
        'description' => 'Acetaminofen caja',
        'lot_number' => 'LOT-INDEX-01',
        'quantity' => 200,
        'unit_cost' => 180,
        'tax_rate' => 0,
        'line_subtotal' => 36000,
        'line_tax' => 0,
        'line_total' => 36000,
    ]);

    PurchaseReceipt::factory()
        ->for($otherTenant)
        ->for($otherSupplier)
        ->create(['document_number' => 'REM-HIDDEN-1001']);

    $this->actingAs($user)
        ->get('/purchases?q=REM-INDEX')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Purchases/Index')
            ->where('filters.q', 'REM-INDEX')
            ->where('stats.receipts', 1)
            ->where('stats.total', 36000)
            ->where('stats.items', 1)
            ->where('stats.radian_pending', 1)
            ->has('statuses', 3)
            ->has('radianStatuses', 4)
            ->has('receipts.data', 1)
            ->where('receipts.data.0.document_number', 'REM-INDEX-1001')
            ->where('receipts.data.0.radian_status.value', 'pending')
            ->where('receipts.data.0.supplier.name', 'Drogueria Mayorista Caribe')
            ->where('receipts.data.0.items.0.description', 'Acetaminofen caja')
            ->etc()
        );
});
