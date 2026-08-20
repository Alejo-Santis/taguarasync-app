<?php

use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot access the inventory report', function () {
    $this->get('/reports/inventory')->assertRedirect('/login');
});

test('inventory report summarizes valued stock and expiring lots', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $product = Product::factory()->for($tenant)->create(['commercial_name' => 'Loratadina 10mg']);

    InventoryLot::factory()->for($tenant)->for($product)->create([
        'lot_number' => 'LOT-OK',
        'current_quantity' => 100,
        'unit_cost' => 500,
        'status' => 'available',
        'expires_on' => today()->addYear(),
    ]);

    InventoryLot::factory()->for($tenant)->for($product)->create([
        'lot_number' => 'LOT-EXPIRING',
        'current_quantity' => 20,
        'unit_cost' => 300,
        'status' => 'available',
        'expires_on' => today()->addDays(10),
    ]);

    // Depleted lots must not count toward available stock.
    InventoryLot::factory()->for($tenant)->for($product)->create([
        'lot_number' => 'LOT-DEPLETED',
        'current_quantity' => 0,
        'unit_cost' => 500,
        'status' => 'depleted',
    ]);

    $this->actingAs($user)
        ->get('/reports/inventory')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Inventory')
            ->where('summary.lots_count', 2)
            ->where('summary.total_units', 120)
            ->where('summary.total_value', 100 * 500 + 20 * 300)
            ->where('summary.expiring_30', 1)
        );
});

test('inventory report filters to only expiring lots', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $product = Product::factory()->for($tenant)->create();

    InventoryLot::factory()->for($tenant)->for($product)->create([
        'lot_number' => 'LOT-FAR',
        'current_quantity' => 10,
        'status' => 'available',
        'expires_on' => today()->addYear(),
    ]);

    InventoryLot::factory()->for($tenant)->for($product)->create([
        'lot_number' => 'LOT-SOON',
        'current_quantity' => 10,
        'status' => 'available',
        'expires_on' => today()->addDays(5),
    ]);

    $this->actingAs($user)
        ->get('/reports/inventory?expiry=expiring')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('lots.data', 1)
            ->where('lots.data.0.lot_number', 'LOT-SOON')
        );
});
