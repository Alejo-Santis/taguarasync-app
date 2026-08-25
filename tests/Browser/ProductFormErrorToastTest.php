<?php

use App\Models\Laboratory;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A presentation name/quantity mismatch is exactly the kind of validation
 * error that can land on a field a page doesn't render inline, so it also
 * exercises the global toast fallback in FlashMessages.svelte, not just the
 * inline `invalid-feedback` markup.
 */
test('mismatched presentation name and quantity shows a visible error on submit', function () {
    app(RoleAndPermissionSeeder::class)->run();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create([
        'email' => 'admin@taguara.test',
        'password' => bcrypt('Password123!'),
    ]);
    $user->assignRole('owner');

    Laboratory::factory()->for($tenant)->create(['name' => 'Genfar', 'is_active' => true]);
    ProductCategory::factory()->for($tenant)->create(['name' => 'Analgesicos', 'is_active' => true]);
    $unit = ProductUnit::factory()->create(['name' => 'Unidad', 'code' => 'und', 'is_active' => true]);
    ProductUnit::factory()->create(['name' => 'Caja', 'code' => 'caj', 'is_active' => true]);

    $page = visit('/login');
    $page->fill('#email', 'admin@taguara.test')
        ->fill('#password', 'Password123!')
        ->click('Iniciar sesión')
        ->assertPathIsNot('/login');

    $page->navigate('/products/create');
    $page->assertSee('Registrar producto')
        ->fill('#commercial_name', 'Acetaminofen 500mg')
        ->fill('#internal_code', 'ACET-500')
        ->select('#laboratory_id', Laboratory::firstOrFail()->id)
        ->select('#product_category_id', ProductCategory::firstOrFail()->id)
        ->select('#minimum_unit_id', (string) $unit->id)
        ->fill('#purchase_price', '180')
        ->fill('#sale_price', '300')
        ->fill('#minimum_stock', '0')
        ->fill('#tax_rate', '0')
        ->fill('#presentation_name_0', 'Caja x 20')
        ->click('Guardar')
        ->wait(1)
        ->assertSee('se esperaba 20')
        ->assertNoJavaScriptErrors();
});
