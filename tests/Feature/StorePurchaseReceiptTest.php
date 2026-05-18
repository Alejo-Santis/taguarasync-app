<?php

use App\Models\InventoryLot;
use App\Models\InventoryMovement;
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

uses(RefreshDatabase::class);

function purchaseFormContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $supplier = Supplier::factory()->for($tenant)->create(['name' => 'Proveedor Caribe']);
    $unit = ProductUnit::factory()->create(['name' => 'Unidad', 'code' => "unit-store-purchase-{$tenant->id}"]);
    $product = Product::factory()
        ->for($tenant)
        ->for($unit, 'minimumUnit')
        ->create([
            'commercial_name' => 'Ibuprofeno 400mg',
            'purchase_price' => 250,
            'tax_rate' => 0,
        ]);
    $presentation = ProductPresentation::factory()
        ->for($tenant)
        ->for($product)
        ->for($unit, 'unit')
        ->create(['name' => 'Caja x 50', 'minimum_unit_quantity' => 50]);

    return [$tenant, $user, $supplier, $product, $presentation];
}

test('authenticated users can open the purchase receipt form', function () {
    [, $user, $supplier, $product] = purchaseFormContext();

    $this->actingAs($user)
        ->get('/purchases/create')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Purchases/Create')
            ->where('options.suppliers.0.id', $supplier->id)
            ->where('options.products.0.id', $product->id)
            ->has('options.products.0.presentations', 1)
            ->etc()
        );
});

test('authenticated users can store a purchase receipt and update inventory', function () {
    [, $user, $supplier, $product, $presentation] = purchaseFormContext();

    $this->actingAs($user)
        ->post('/purchases', [
            'supplier_id' => $supplier->id,
            'document_number' => 'REM-STORE-1001',
            'document_date' => '2026-05-18',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_presentation_id' => $presentation->id,
                    'description' => 'Ibuprofeno caja',
                    'lot_number' => 'LOT-STORE-01',
                    'expires_on' => '2027-12-31',
                    'quantity' => 50,
                    'unit_cost' => 250,
                    'tax_rate' => 0,
                ],
            ],
        ])
        ->assertRedirect('/purchases')
        ->assertSessionHas('success');

    expect(PurchaseReceipt::count())->toBe(1)
        ->and(PurchaseReceipt::first()?->document_number)->toBe('REM-STORE-1001')
        ->and(PurchaseReceipt::first()?->total)->toBe(12500)
        ->and(PurchaseReceiptItem::count())->toBe(1)
        ->and(InventoryLot::first()?->current_quantity)->toBe(50)
        ->and(InventoryMovement::first()?->reference_code)->toBe('REM-STORE-1001');
});

test('purchase receipt validation keeps presentations tied to their products', function () {
    [$tenant, $user, $supplier, $product] = purchaseFormContext();
    $otherProduct = Product::factory()
        ->for($tenant)
        ->for($product->minimumUnit, 'minimumUnit')
        ->create(['commercial_name' => 'Producto alterno']);
    $otherPresentation = ProductPresentation::factory()
        ->for($tenant)
        ->for($otherProduct)
        ->for($product->minimumUnit, 'unit')
        ->create(['name' => 'Blister x 10']);

    $this->actingAs($user)
        ->post('/purchases', [
            'supplier_id' => $supplier->id,
            'document_number' => 'REM-BAD-1001',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_presentation_id' => $otherPresentation->id,
                    'description' => 'Linea invalida',
                    'lot_number' => 'LOT-BAD-01',
                    'quantity' => 10,
                    'unit_cost' => 100,
                    'tax_rate' => 0,
                ],
            ],
        ])
        ->assertSessionHasErrors('items.0.product_presentation_id');
});
