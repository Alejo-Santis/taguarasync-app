<?php

use App\Actions\Inventory\RegisterInventoryMovement;
use App\Enums\InventoryLotStatus;
use App\Enums\InventoryMovementType;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function inventoryLotWithStock(int $quantity = 0): array
{
    $tenant = Tenant::factory()->create();
    $unit = ProductUnit::factory()->create(['name' => 'Unidad', 'code' => "unit-{$tenant->id}"]);
    $product = Product::factory()->for($tenant)->for($unit, 'minimumUnit')->create();
    $presentation = ProductPresentation::factory()
        ->for($tenant)
        ->for($product)
        ->for($unit, 'unit')
        ->create(['minimum_unit_quantity' => 1, 'is_default' => true]);
    $lot = InventoryLot::factory()
        ->for($tenant)
        ->for($product)
        ->for($presentation, 'presentation')
        ->create([
            'initial_quantity' => $quantity,
            'current_quantity' => $quantity,
            'lot_number' => 'LOT-BASE',
        ]);
    $user = User::factory()->for($tenant)->create();

    return [$lot, $user];
}

test('purchase movements increase lot stock and record audit values', function () {
    [$lot, $user] = inventoryLotWithStock();

    $movement = app(RegisterInventoryMovement::class)->purchase($lot, 25, $user, [
        'reference_code' => 'REM-001',
        'notes' => 'Entrada por compra',
    ]);

    $lot->refresh();

    expect($lot->current_quantity)->toBe(25)
        ->and($lot->status)->toBe(InventoryLotStatus::Available)
        ->and($movement->type)->toBe(InventoryMovementType::Purchase)
        ->and($movement->quantity_before)->toBe(0)
        ->and($movement->quantity_after)->toBe(25)
        ->and($movement->quantity_delta)->toBe(25)
        ->and($movement->reference_code)->toBe('REM-001');
});

test('sale movements decrease lot stock and mark depleted lots', function () {
    [$lot, $user] = inventoryLotWithStock(10);

    $movement = app(RegisterInventoryMovement::class)->sale($lot, 10, $user, [
        'reference_code' => 'POS-001',
    ]);

    $lot->refresh();

    expect($lot->current_quantity)->toBe(0)
        ->and($lot->status)->toBe(InventoryLotStatus::Depleted)
        ->and($movement->type)->toBe(InventoryMovementType::Sale)
        ->and($movement->quantity_delta)->toBe(-10)
        ->and($movement->quantity_before)->toBe(10)
        ->and($movement->quantity_after)->toBe(0);
});

test('inventory movements cannot leave negative stock', function () {
    [$lot, $user] = inventoryLotWithStock(3);

    app(RegisterInventoryMovement::class)->sale($lot, 4, $user);
})->throws(InvalidArgumentException::class, 'negative stock');

test('tenant scope isolates inventory movements', function () {
    [$firstLot, $firstUser] = inventoryLotWithStock();
    [$secondLot, $secondUser] = inventoryLotWithStock();

    app(RegisterInventoryMovement::class)->purchase($firstLot, 5, $firstUser);
    app(RegisterInventoryMovement::class)->purchase($secondLot, 7, $secondUser);

    $this->actingAs($firstUser);
    app(CurrentTenant::class)->set($firstUser->tenant);

    expect(InventoryMovement::pluck('quantity_delta')->all())->toBe([5]);
});
