<?php

use App\Enums\CashSessionStatus;
use App\Enums\ProductStatus;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function posSetup(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $unit = ProductUnit::factory()->create(['code' => 'und', 'is_active' => true]);

    $register = CashRegister::factory()->for($tenant)->create(['name' => 'Caja 1', 'code' => 'CJ-01', 'is_active' => true]);

    $session = CashSession::create([
        'tenant_id' => $tenant->id,
        'cash_register_id' => $register->id,
        'user_id' => $user->id,
        'opening_amount' => 50000,
        'status' => CashSessionStatus::Open,
        'opened_at' => now(),
    ]);

    $product = Product::factory()->for($tenant)->for($unit, 'minimumUnit')->create([
        'commercial_name' => 'Dolex 500mg',
        'sale_price' => 350,
        'tax_rate' => 0,
        'status' => ProductStatus::Active,
        'is_controlled' => false,
    ]);

    $presentation = ProductPresentation::factory()->for($product)->for($unit, 'unit')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Unidad',
        'sale_price' => 350,
        'minimum_unit_quantity' => 1,
        'is_default' => true,
        'is_active' => true,
    ]);

    $lot = InventoryLot::factory()->for($tenant)->for($product)->create([
        'lot_number' => 'LOT-001',
        'current_quantity' => 100,
        'initial_quantity' => 100,
        'unit_cost' => 180,
        'status' => 'available',
    ]);

    return compact('tenant', 'user', 'product', 'presentation', 'lot', 'session', 'register');
}

test('guests cannot access the POS', function () {
    $this->get('/pos')->assertRedirect('/login');
});

test('POS redirects to open session when no active session', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->get('/pos')
        ->assertRedirect(route('pos.session.open'));
});

test('authenticated users with open session can access the POS', function () {
    ['tenant' => $tenant, 'user' => $user] = posSetup();

    $this->actingAs($user)
        ->get('/pos')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Pos/Index'));
});

test('POS product search returns products with available stock', function () {
    ['tenant' => $tenant, 'user' => $user, 'product' => $product] = posSetup();

    $response = $this->actingAs($user)
        ->getJson('/pos/products?q=dolex')
        ->assertOk();

    expect($response->json())->toHaveCount(1)
        ->and($response->json('0.commercial_name'))->toBe('Dolex 500mg')
        ->and($response->json('0.available_units'))->toBe(100);
});

test('POS product search excludes products with no stock', function () {
    ['tenant' => $tenant, 'user' => $user, 'lot' => $lot] = posSetup();

    $lot->update(['current_quantity' => 0, 'status' => 'depleted']);

    $response = $this->actingAs($user)
        ->getJson('/pos/products?q=dolex')
        ->assertOk();

    expect($response->json())->toHaveCount(0);
});

test('valid sale creates sale record and reduces inventory', function () {
    ['tenant' => $tenant, 'user' => $user, 'product' => $product, 'presentation' => $presentation, 'lot' => $lot] = posSetup();

    $this->actingAs($user)
        ->post('/pos/sales', [
            'payment_method' => 'cash',
            'amount_tendered' => 2000,
            'items' => [[
                'product_id' => $product->id,
                'product_presentation_id' => $presentation->id,
                'description' => 'Dolex 500mg',
                'quantity' => 3,
                'unit_price' => 350,
                'tax_rate' => 0,
            ]],
        ])
        ->assertRedirect(route('pos.index'));

    expect(Sale::count())->toBe(1);
    expect($lot->fresh()->current_quantity)->toBe(97);
});

test('sale with insufficient stock returns error', function () {
    ['tenant' => $tenant, 'user' => $user, 'product' => $product, 'presentation' => $presentation, 'lot' => $lot] = posSetup();

    $lot->update(['current_quantity' => 2]);

    $this->actingAs($user)
        ->post('/pos/sales', [
            'payment_method' => 'cash',
            'amount_tendered' => 5000,
            'items' => [[
                'product_id' => $product->id,
                'product_presentation_id' => $presentation->id,
                'description' => 'Dolex 500mg',
                'quantity' => 10,
                'unit_price' => 350,
                'tax_rate' => 0,
            ]],
        ])
        ->assertRedirect();

    expect(Sale::count())->toBe(0);
});

test('cash sale validates tendered amount covers total', function () {
    ['tenant' => $tenant, 'user' => $user, 'product' => $product, 'presentation' => $presentation] = posSetup();

    $this->actingAs($user)
        ->post('/pos/sales', [
            'payment_method' => 'cash',
            'amount_tendered' => 100, // less than 350
            'items' => [[
                'product_id' => $product->id,
                'product_presentation_id' => $presentation->id,
                'description' => 'Dolex 500mg',
                'quantity' => 1,
                'unit_price' => 350,
                'tax_rate' => 0,
            ]],
        ])
        ->assertSessionHasErrors('amount_tendered');

    expect(Sale::count())->toBe(0);
});

test('card sale does not require amount tendered', function () {
    ['tenant' => $tenant, 'user' => $user, 'product' => $product, 'presentation' => $presentation] = posSetup();

    $this->actingAs($user)
        ->post('/pos/sales', [
            'payment_method' => 'card',
            'items' => [[
                'product_id' => $product->id,
                'product_presentation_id' => $presentation->id,
                'description' => 'Dolex 500mg',
                'quantity' => 1,
                'unit_price' => 350,
                'tax_rate' => 0,
            ]],
        ])
        ->assertRedirect(route('pos.index'));

    expect(Sale::count())->toBe(1);
    expect(Sale::first()->payment_method->value)->toBe('card');
});
