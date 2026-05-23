<?php

use App\Actions\Inventory\RegisterInventoryMovement;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function kardexSetup(): array
{
    $tenant = Tenant::factory()->create();
    $unit = ProductUnit::factory()->create(['name' => 'Unidad', 'code' => "kardex-unit-{$tenant->id}"]);
    $product = Product::factory()
        ->for($tenant)
        ->for($unit, 'minimumUnit')
        ->create(['commercial_name' => 'Producto Kardex Visible', 'internal_code' => 'KDX-001']);
    $presentation = ProductPresentation::factory()
        ->for($tenant)
        ->for($product)
        ->for($unit, 'unit')
        ->create(['name' => 'Unidad', 'minimum_unit_quantity' => 1]);
    $lot = InventoryLot::factory()
        ->for($tenant)
        ->for($product)
        ->for($presentation, 'presentation')
        ->create([
            'lot_number' => 'LOT-KDX',
            'initial_quantity' => 0,
            'current_quantity' => 0,
            'unit_cost' => 250,
        ]);
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('inventory.view');
    $user->givePermissionTo('inventory.view');

    return compact('tenant', 'unit', 'product', 'presentation', 'lot', 'user');
}

test('kardex lists inventory movements with balances', function () {
    ['tenant' => $tenant, 'lot' => $lot, 'user' => $user] = kardexSetup();

    app(RegisterInventoryMovement::class)->purchase($lot, 12, $user, [
        'reference_code' => 'RC-001',
        'notes' => 'Compra inicial',
        'occurred_at' => now()->subHour(),
    ]);

    app(RegisterInventoryMovement::class)->sale($lot->fresh(), 4, $user, [
        'reference_code' => 'VTA-001',
        'notes' => 'Venta POS',
        'occurred_at' => now(),
    ]);

    $otherTenant = Tenant::factory()->create();
    InventoryLot::factory()->for($otherTenant)->create(['lot_number' => 'LOT-HIDDEN']);

    $this->actingAs($user)
        ->get('/inventory/kardex?q=KDX-001')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Kardex')
            ->where('summary.movements_count', 2)
            ->where('summary.entries', 12)
            ->where('summary.exits', 4)
            ->where('summary.net', 8)
            ->has('movements.data', 2)
            ->where('movements.data.0.reference_code', 'VTA-001')
            ->where('movements.data.0.quantity_before', 12)
            ->where('movements.data.0.quantity_after', 8)
            ->where('movements.data.1.reference_code', 'RC-001')
            ->etc()
        );
});

test('kardex can filter by movement type', function () {
    ['lot' => $lot, 'user' => $user] = kardexSetup();

    app(RegisterInventoryMovement::class)->purchase($lot, 8, $user, [
        'reference_code' => 'RC-002',
    ]);

    app(RegisterInventoryMovement::class)->sale($lot->fresh(), 3, $user, [
        'reference_code' => 'VTA-002',
    ]);

    $this->actingAs($user)
        ->get('/inventory/kardex?type=sale')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Kardex')
            ->where('filters.type', 'sale')
            ->where('summary.movements_count', 1)
            ->where('summary.entries', 0)
            ->where('summary.exits', 3)
            ->has('movements.data', 1)
            ->where('movements.data.0.reference_code', 'VTA-002')
            ->etc()
        );
});
