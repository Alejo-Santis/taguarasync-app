<?php

use App\Enums\InventoryLotStatus;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from inventory to login', function () {
    $this->get('/inventory')->assertRedirect('/login');
});

test('authenticated users only see inventory lots from their tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $unit = ProductUnit::factory()->create(['name' => 'Unidad', 'code' => 'unit-index']);
    $product = Product::factory()
        ->for($tenant)
        ->for($unit, 'minimumUnit')
        ->create(['commercial_name' => 'Producto visible', 'internal_code' => 'VISIBLE-001']);
    $presentation = ProductPresentation::factory()
        ->for($tenant)
        ->for($product)
        ->for($unit, 'unit')
        ->create(['name' => 'Unidad']);

    InventoryLot::factory()
        ->for($tenant)
        ->for($product)
        ->for($presentation, 'presentation')
        ->create([
            'lot_number' => 'LOT-VISIBLE',
            'current_quantity' => 18,
            'initial_quantity' => 18,
            'status' => InventoryLotStatus::Available,
            'expires_on' => now()->addDays(45)->toDateString(),
        ]);

    InventoryLot::factory()
        ->for($otherTenant)
        ->create(['lot_number' => 'LOT-HIDDEN', 'current_quantity' => 99]);

    $user = User::factory()->for($tenant)->create();

    $this
        ->actingAs($user)
        ->get('/inventory?q=visible')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Index')
            ->where('filters.q', 'visible')
            ->where('stats.lots', 1)
            ->where('stats.units', 18)
            ->where('stats.expiring', 1)
            ->has('lots.data', 1)
            ->where('lots.data.0.lot_number', 'LOT-VISIBLE')
            ->where('lots.data.0.product.name', 'Producto visible')
            ->where('lots.data.0.current_quantity', 18)
            ->etc()
        );
});
