<?php

use App\Models\ActiveIngredient;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('authenticated users can open the product edit form', function () {
    $tenant = Tenant::factory()->create();
    $unit = ProductUnit::factory()->create(['name' => 'Unidad', 'code' => 'unit']);
    $product = Product::factory()
        ->for($tenant)
        ->for($unit, 'minimumUnit')
        ->create(['commercial_name' => 'Producto editable']);
    ProductPresentation::factory()
        ->for($tenant)
        ->for($product)
        ->for($unit, 'unit')
        ->create(['name' => 'Unidad', 'is_default' => true]);
    $user = createAdminUser($tenant);

    $this
        ->actingAs($user)
        ->get("/products/{$product->uuid}/edit")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Edit')
            ->where('product.uuid', $product->uuid)
            ->where('product.commercial_name', 'Producto editable')
            ->has('product.presentations', 1)
            ->etc()
        );
});

test('authenticated users can update products and replace presentations', function () {
    $tenant = Tenant::factory()->create();
    $laboratory = Laboratory::factory()->for($tenant)->create();
    $category = ProductCategory::factory()->for($tenant)->create();
    $ingredient = ActiveIngredient::factory()->create(['dci_name' => 'Ibuprofeno']);
    $unit = ProductUnit::factory()->create(['name' => 'Unidad', 'code' => 'unit']);
    $box = ProductUnit::factory()->create(['name' => 'Caja', 'code' => 'box']);
    $product = Product::factory()
        ->for($tenant)
        ->for($laboratory)
        ->for($category, 'category')
        ->for($ingredient, 'activeIngredient')
        ->for($unit, 'minimumUnit')
        ->create(['internal_code' => 'OLD-001']);
    ProductPresentation::factory()
        ->for($tenant)
        ->for($product)
        ->for($unit, 'unit')
        ->create(['name' => 'Presentacion anterior', 'is_default' => true]);
    $user = createAdminUser($tenant);

    $this
        ->actingAs($user)
        ->put("/products/{$product->uuid}", [
            'laboratory_id' => $laboratory->id,
            'product_category_id' => $category->id,
            'active_ingredient_id' => $ingredient->id,
            'minimum_unit_id' => $unit->id,
            'internal_code' => 'IBU-400',
            'barcode' => '7700000000888',
            'commercial_name' => 'Ibuprofeno 400mg',
            'generic_name' => 'Ibuprofeno',
            'cum' => null,
            'health_registration' => null,
            'pharmaceutical_form' => 'Tableta',
            'concentration' => '400mg',
            'purchase_price' => 250,
            'sale_price' => 500,
            'minimum_stock' => 0,
            'regulated_price' => null,
            'tax_rate' => 0,
            'requires_invima_registration' => true,
            'is_controlled' => false,
            'control_level' => null,
            'status' => 'active',
            'notes' => 'Actualizado desde prueba',
            'presentations' => [
                [
                    'unit_id' => $unit->id,
                    'name' => 'Unidad',
                    'barcode' => null,
                    'minimum_unit_quantity' => 1,
                    'sale_price' => 500,
                    'is_default' => true,
                    'is_active' => true,
                ],
                [
                    'unit_id' => $box->id,
                    'name' => 'Caja x 50',
                    'barcode' => null,
                    'minimum_unit_quantity' => 50,
                    'sale_price' => 24000,
                    'is_default' => false,
                    'is_active' => true,
                ],
            ],
        ])
        ->assertRedirect('/products')
        ->assertSessionHas('success');

    $product->refresh();

    expect($product->internal_code)->toBe('IBU-400')
        ->and($product->commercial_name)->toBe('Ibuprofeno 400mg')
        ->and($product->presentations()->pluck('name')->sort()->values()->all())->toBe(['Caja x 50', 'Unidad'])
        ->and($product->presentations()->where('is_default', true)->value('name'))->toBe('Unidad');
});
