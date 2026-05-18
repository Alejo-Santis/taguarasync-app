<?php

use App\Actions\Purchases\ReceivePurchaseReceipt;
use App\Enums\InventoryMovementType;
use App\Enums\PurchaseReceiptStatus;
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

uses(RefreshDatabase::class);

function purchaseReceptionContext(): array
{
    $tenant = Tenant::factory()->create();
    $supplier = Supplier::factory()->for($tenant)->create(['name' => 'Drogueria Mayorista']);
    $unit = ProductUnit::factory()->create(['name' => 'Unidad', 'code' => "unit-purchase-{$tenant->id}"]);
    $product = Product::factory()
        ->for($tenant)
        ->for($unit, 'minimumUnit')
        ->create(['commercial_name' => 'Acetaminofen 500mg']);
    $presentation = ProductPresentation::factory()
        ->for($tenant)
        ->for($product)
        ->for($unit, 'unit')
        ->create(['name' => 'Caja x 100', 'minimum_unit_quantity' => 100]);
    $user = User::factory()->for($tenant)->create();

    return [$tenant, $supplier, $product, $presentation, $user];
}

test('purchase reception creates receipt items lots and inventory movements', function () {
    [$tenant, $supplier, $product, $presentation, $user] = purchaseReceptionContext();

    $receipt = app(ReceivePurchaseReceipt::class)->execute([
        'tenant_id' => $tenant->id,
        'supplier_id' => $supplier->id,
        'document_number' => 'REM-1001',
        'document_date' => '2026-05-18',
        'received_at' => now(),
        'notes' => 'Documento recibido en fisico',
        'items' => [
            [
                'product_id' => $product->id,
                'product_presentation_id' => $presentation->id,
                'description' => 'Acetaminofen caja',
                'lot_number' => 'LOT-ACET-01',
                'expires_on' => '2027-06-30',
                'quantity' => 200,
                'unit_cost' => 180,
                'tax_rate' => 0,
            ],
        ],
    ], $user);

    $lot = InventoryLot::firstOrFail();
    $movement = InventoryMovement::firstOrFail();

    expect($receipt)->toBeInstanceOf(PurchaseReceipt::class)
        ->and($receipt->status)->toBe(PurchaseReceiptStatus::Received)
        ->and($receipt->subtotal)->toBe(36000)
        ->and($receipt->total)->toBe(36000)
        ->and(PurchaseReceiptItem::count())->toBe(1)
        ->and($lot->current_quantity)->toBe(200)
        ->and($lot->lot_number)->toBe('LOT-ACET-01')
        ->and($movement->type)->toBe(InventoryMovementType::Purchase)
        ->and($movement->quantity_delta)->toBe(200)
        ->and($movement->reference_code)->toBe('REM-1001')
        ->and(PurchaseReceiptItem::first()?->inventory_movement_id)->toBe($movement->id);
});

test('purchase reception reuses existing lot and accumulates stock', function () {
    [$tenant, $supplier, $product, $presentation, $user] = purchaseReceptionContext();

    InventoryLot::factory()
        ->for($tenant)
        ->for($product)
        ->for($presentation, 'presentation')
        ->create([
            'lot_number' => 'LOT-REUSED',
            'current_quantity' => 50,
            'initial_quantity' => 50,
            'unit_cost' => 150,
        ]);

    app(ReceivePurchaseReceipt::class)->execute([
        'tenant_id' => $tenant->id,
        'supplier_id' => $supplier->id,
        'document_number' => 'REM-1002',
        'items' => [
            [
                'product_id' => $product->id,
                'product_presentation_id' => $presentation->id,
                'description' => 'Acetaminofen caja',
                'lot_number' => 'LOT-REUSED',
                'expires_on' => '2027-06-30',
                'quantity' => 25,
                'unit_cost' => 180,
                'tax_rate' => 0,
            ],
        ],
    ], $user);

    expect(InventoryLot::count())->toBe(1)
        ->and(InventoryLot::first()?->current_quantity)->toBe(75)
        ->and(InventoryMovement::first()?->quantity_before)->toBe(50)
        ->and(InventoryMovement::first()?->quantity_after)->toBe(75);
});
