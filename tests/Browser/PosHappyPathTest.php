<?php

use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Recorrido manual (automatizado en navegador real) de la ruta feliz del
 * piloto: login → abrir turno → vender → cerrar turno. No reemplaza a los
 * tests de Feature — verifica que las pantallas realmente se vean y
 * funcionen para un usuario, no solo que el backend responda bien.
 */
test('cashier can log in, open a shift, sell a product and close the shift', function () {
    app(RoleAndPermissionSeeder::class)->run();

    $tenant = Tenant::factory()->create(['name' => 'Farmacia El Caribe']);
    $user = User::factory()->for($tenant)->create([
        'email' => 'piloto@taguara.test',
        'password' => bcrypt('Piloto2026!'),
    ]);
    $user->assignRole('owner');

    $unit = ProductUnit::factory()->create(['code' => 'und', 'name' => 'Unidad', 'is_active' => true]);

    $product = Product::factory()->for($tenant)->for($unit, 'minimumUnit')->create([
        'commercial_name' => 'Acetaminofen 500mg',
        'sale_price' => 3500,
        'tax_rate' => 0,
        'status' => 'active',
        'is_controlled' => false,
    ]);

    ProductPresentation::factory()->for($product)->for($unit, 'unit')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Caja x 10',
        'sale_price' => 3500,
        'minimum_unit_quantity' => 1,
        'is_default' => true,
        'is_active' => true,
    ]);

    InventoryLot::factory()->for($tenant)->for($product)->create([
        'lot_number' => 'LOT-PILOTO-1',
        'current_quantity' => 50,
        'initial_quantity' => 50,
        'unit_cost' => 1800,
        'status' => 'available',
        'expires_on' => now()->addYear(),
    ]);

    $register = CashRegister::factory()->for($tenant)->create([
        'name' => 'Caja Principal',
        'code' => 'CJ-01',
        'is_active' => true,
    ]);

    // ── 1. Login ─────────────────────────────────────────────────────────
    $page = visit('/login');
    $page->assertSee('Iniciar sesión')
        ->fill('#email', 'piloto@taguara.test')
        ->fill('#password', 'Piloto2026!')
        ->click('Iniciar sesión')
        ->assertPathIsNot('/login')
        ->screenshot(filename: 'pos-01-after-login');

    // ── 2. Abrir turno de caja ───────────────────────────────────────────
    $page->navigate('/pos/session/open');
    $page->assertSee('Abrir turno')
        ->select('#register', (string) $register->id)
        ->fill('#opening', '50000')
        ->screenshot(filename: 'pos-02-open-session-form')
        ->click('Abrir turno y comenzar')
        ->assertPathIs('/pos')
        ->screenshot(filename: 'pos-03-pos-terminal');

    expect(CashSession::where('cash_register_id', $register->id)->where('status', 'open')->exists())->toBeTrue();

    // ── 3. Buscar el producto y agregarlo al carrito ────────────────────
    $page->fill('input[type="search"]', 'Acetaminofen')
        ->wait(1)
        ->assertSee('Acetaminofen 500mg')
        ->screenshot(filename: 'pos-04-search-results')
        ->click('.taguara-pos-pres-btn')
        ->assertSee('Cobrar')
        ->screenshot(filename: 'pos-05-cart-with-item');

    // ── 4. Cobrar en efectivo ────────────────────────────────────────────
    $page->click('button:has-text("Cobrar")')
        ->assertSee('Efectivo')
        ->click('Efectivo')
        ->fill('#tendered', '10000')
        ->screenshot(filename: 'pos-06-payment-modal')
        ->click('Confirmar venta')
        ->wait(1)
        ->screenshot(filename: 'pos-07-after-sale')
        ->assertNoJavaScriptErrors();

    expect(Sale::where('tenant_id', $tenant->id)->where('status', 'completed')->count())->toBe(1);

    $sale = Sale::where('tenant_id', $tenant->id)->first();

    // ── 5. Verificar la venta en el listado ─────────────────────────────
    $page->navigate('/sales');
    $page->assertSee($sale->document_number)
        ->screenshot(filename: 'pos-08-sales-index');

    // ── 6. Cerrar el turno de caja ───────────────────────────────────────
    $session = CashSession::where('cash_register_id', $register->id)->where('status', 'open')->firstOrFail();

    $page->navigate("/pos/session/{$session->uuid}/close");
    $page->assertSee('Cerrar turno')
        ->fill('#actual', '13500')
        ->screenshot(filename: 'pos-09-close-session-form')
        ->click('Confirmar cierre de turno')
        ->wait(1)
        ->screenshot(filename: 'pos-10-after-close')
        ->assertNoJavaScriptErrors();

    expect($session->fresh()->status)->toBe('closed');
});
