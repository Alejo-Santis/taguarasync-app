<?php

use App\Models\ActiveIngredient;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('authenticated users can open the product creation form', function () {
    $tenant = Tenant::factory()->create();
    Laboratory::factory()->for($tenant)->create(['name' => 'Genfar']);
    ProductCategory::factory()->for($tenant)->create(['name' => 'Analgesicos']);
    ProductUnit::factory()->create(['name' => 'Unidad', 'code' => 'unit']);

    $user = User::factory()->for($tenant)->create();

    $this
        ->actingAs($user)
        ->get('/products/create')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Create')
            ->has('options.laboratories', 1)
            ->has('options.categories', 1)
            ->has('options.units', 1)
            ->etc()
        );
});

test('authenticated users can create products with presentations', function () {
    $tenant = Tenant::factory()->create();
    $laboratory = Laboratory::factory()->for($tenant)->create();
    $category = ProductCategory::factory()->for($tenant)->create();
    $ingredient = ActiveIngredient::factory()->create(['dci_name' => 'Acetaminofen']);
    $unit = ProductUnit::factory()->create(['name' => 'Unidad', 'code' => 'unit']);
    $box = ProductUnit::factory()->create(['name' => 'Caja', 'code' => 'box']);
    $user = User::factory()->for($tenant)->create();

    $this
        ->actingAs($user)
        ->post('/products', [
            'laboratory_id' => $laboratory->id,
            'product_category_id' => $category->id,
            'active_ingredient_id' => $ingredient->id,
            'minimum_unit_id' => $unit->id,
            'internal_code' => 'ACET-500',
            'barcode' => '7700000000999',
            'commercial_name' => 'Acetaminofen 500mg',
            'generic_name' => 'Acetaminofen',
            'cum' => null,
            'health_registration' => null,
            'pharmaceutical_form' => 'Tableta',
            'concentration' => '500mg',
            'purchase_price' => 180,
            'sale_price' => 300,
            'regulated_price' => null,
            'tax_rate' => 0,
            'requires_invima_registration' => true,
            'is_controlled' => false,
            'control_level' => null,
            'status' => 'active',
            'notes' => null,
            'presentations' => [
                [
                    'unit_id' => $unit->id,
                    'name' => 'Unidad',
                    'barcode' => null,
                    'minimum_unit_quantity' => 1,
                    'sale_price' => 300,
                    'is_default' => true,
                    'is_active' => true,
                ],
                [
                    'unit_id' => $box->id,
                    'name' => 'Caja x 100',
                    'barcode' => null,
                    'minimum_unit_quantity' => 100,
                    'sale_price' => 28000,
                    'is_default' => false,
                    'is_active' => true,
                ],
            ],
        ])
        ->assertRedirect('/products')
        ->assertSessionHas('success');

    $product = Product::with('presentations')->firstOrFail();

    expect($product->tenant_id)->toBe($tenant->id)
        ->and($product->commercial_name)->toBe('Acetaminofen 500mg')
        ->and($product->presentations)->toHaveCount(2)
        ->and(ProductPresentation::where('is_default', true)->first()?->name)->toBe('Unidad');
});
