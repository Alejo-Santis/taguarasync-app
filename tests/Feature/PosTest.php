<?php

use App\Enums\CashSessionStatus;
use App\Enums\ProductStatus;
use App\Models\BankAccount;
use App\Models\BankAccountMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\InventoryLot;
use App\Models\PaymentMethod as PaymentMethodConfig;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function posSetup(): array
{
    app(RoleAndPermissionSeeder::class)->run();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $user->assignRole('cashier');
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
    app(RoleAndPermissionSeeder::class)->run();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $user->assignRole('cashier');

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

test('configured transfer payments require reference and bank account', function () {
    ['tenant' => $tenant, 'user' => $user, 'product' => $product, 'presentation' => $presentation] = posSetup();

    $method = PaymentMethodConfig::factory()->for($tenant)->transfer()->create();

    $this->actingAs($user)
        ->post('/pos/sales', [
            'payment_method' => 'transfer',
            'payments' => [[
                'payment_method_id' => $method->id,
                'amount' => 350,
            ]],
            'items' => [[
                'product_id' => $product->id,
                'product_presentation_id' => $presentation->id,
                'description' => 'Dolex 500mg',
                'quantity' => 1,
                'unit_price' => 350,
                'tax_rate' => 0,
            ]],
        ])
        ->assertSessionHasErrors([
            'payments.0.reference',
            'payments.0.bank_account_id',
        ]);

    expect(Sale::count())->toBe(0);
});

test('bank transfer sale records payment and pending bank movement', function () {
    ['tenant' => $tenant, 'user' => $user, 'product' => $product, 'presentation' => $presentation] = posSetup();

    $method = PaymentMethodConfig::factory()->for($tenant)->transfer()->create();
    $bankAccount = BankAccount::factory()->for($tenant)->create([
        'bank_name' => 'Bancolombia',
        'account_name' => 'Cuenta principal',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post('/pos/sales', [
            'payment_method' => 'transfer',
            'payments' => [[
                'payment_method_id' => $method->id,
                'bank_account_id' => $bankAccount->id,
                'amount' => 350,
                'reference' => 'TRX-123',
            ]],
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

    expect(Sale::count())->toBe(1)
        ->and(SalePayment::count())->toBe(1)
        ->and(BankAccountMovement::count())->toBe(1)
        ->and(BankAccountMovement::first()->reference)->toBe('TRX-123')
        ->and(BankAccountMovement::first()->status)->toBe('pending')
        ->and(BankAccountMovement::first()->bank_account_id)->toBe($bankAccount->id);
});

test('configured payments cannot exceed sale total', function () {
    ['tenant' => $tenant, 'user' => $user, 'product' => $product, 'presentation' => $presentation] = posSetup();

    $method = PaymentMethodConfig::factory()->for($tenant)->cash()->create();

    $this->actingAs($user)
        ->post('/pos/sales', [
            'payment_method' => 'cash',
            'payments' => [[
                'payment_method_id' => $method->id,
                'amount' => 500,
                'amount_tendered' => 500,
            ]],
            'items' => [[
                'product_id' => $product->id,
                'product_presentation_id' => $presentation->id,
                'description' => 'Dolex 500mg',
                'quantity' => 1,
                'unit_price' => 350,
                'tax_rate' => 0,
            ]],
        ])
        ->assertSessionHasErrors('payments');

    expect(Sale::count())->toBe(0);
});

test('sale with percentage discount reduces line total correctly', function () {
    ['user' => $user, 'product' => $product, 'presentation' => $presentation, 'lot' => $lot] = posSetup();

    $this->actingAs($user)
        ->post('/pos/sales', [
            'payment_method' => 'cash',
            'amount_tendered' => 10000,
            'items' => [[
                'product_id' => $product->id,
                'product_presentation_id' => $presentation->id,
                'description' => 'Dolex 500mg',
                'quantity' => 2,
                'unit_price' => 350,
                'discount_rate' => 10,
                'tax_rate' => 0,
            ]],
        ])
        ->assertRedirect(route('pos.index'));

    $sale = Sale::first();
    // gross = 2 × 350 = 700, discount 10% = 70, subtotal = 630, tax = 0, total = 630
    expect($sale->discount_total)->toBe(70)
        ->and($sale->subtotal)->toBe(630)
        ->and($sale->total)->toBe(630);

    $item = $sale->items->first();
    expect($item->discount_amount)->toBe(70)
        ->and($item->line_subtotal)->toBe(630)
        ->and($item->line_total)->toBe(630);
});

test('sale without discount has zero discount_total', function () {
    ['user' => $user, 'product' => $product, 'presentation' => $presentation] = posSetup();

    $this->actingAs($user)
        ->post('/pos/sales', [
            'payment_method' => 'cash',
            'amount_tendered' => 1000,
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

    expect(Sale::first()->discount_total)->toBe(0);
});

test('sale with controlled medication persists prescription fields', function () {
    ['tenant' => $tenant, 'user' => $user, 'presentation' => $presentation] = posSetup();

    $controlled = Product::factory()->for($tenant)->for(ProductUnit::factory()->create(['code' => 'cps', 'is_active' => true]), 'minimumUnit')->create([
        'commercial_name' => 'Rivotril 2mg',
        'sale_price' => 5000,
        'tax_rate' => 0,
        'status' => ProductStatus::Active,
        'is_controlled' => true,
    ]);

    $controlledPresentation = ProductPresentation::factory()->for($controlled)->for(ProductUnit::factory()->create(['code' => 'cps2', 'is_active' => true]), 'unit')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Cápsula',
        'sale_price' => 5000,
        'minimum_unit_quantity' => 1,
        'is_default' => true,
        'is_active' => true,
    ]);

    InventoryLot::factory()->for($tenant)->for($controlled)->create([
        'lot_number' => 'LOT-CTL-001',
        'current_quantity' => 50,
        'initial_quantity' => 50,
        'unit_cost' => 3000,
        'status' => 'available',
    ]);

    $this->actingAs($user)
        ->post('/pos/sales', [
            'payment_method' => 'cash',
            'amount_tendered' => 10000,
            'items' => [[
                'product_id' => $controlled->id,
                'product_presentation_id' => $controlledPresentation->id,
                'description' => 'Rivotril 2mg Cápsula',
                'quantity' => 1,
                'unit_price' => 5000,
                'tax_rate' => 0,
                'prescription_number' => 'RX-2026-001',
                'patient_id_number' => '1234567890',
                'patient_name' => 'Juan Pérez',
            ]],
        ])
        ->assertRedirect(route('pos.index'));

    $item = Sale::first()->items->first();
    expect($item->prescription_number)->toBe('RX-2026-001')
        ->and($item->patient_id_number)->toBe('1234567890')
        ->and($item->patient_name)->toBe('Juan Pérez');
});
