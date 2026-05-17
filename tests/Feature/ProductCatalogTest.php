<?php

use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('product catalog seeder creates base units and demo presentations', function () {
    Tenant::factory()->create();

    $this->seed(ProductCatalogSeeder::class);

    $product = Product::with('presentations.unit')->firstOrFail();

    expect(ProductUnit::count())->toBe(9)
        ->and($product->presentations)->toHaveCount(3)
        ->and($product->presentations->firstWhere('name', 'Caja x 100 tabletas')->minimum_unit_quantity)->toBe(100);
});

test('tenant scope isolates product catalog records for authenticated users', function () {
    $firstTenant = Tenant::factory()->create();
    $secondTenant = Tenant::factory()->create();

    Product::factory()->for($firstTenant)->create(['commercial_name' => 'Producto visible']);
    Product::factory()->for($secondTenant)->create(['commercial_name' => 'Producto oculto']);

    $user = User::factory()->for($firstTenant)->create();

    app(CurrentTenant::class)->set($user->tenant);

    expect(Product::pluck('commercial_name')->all())->toBe(['Producto visible']);
});

test('product presentations convert commercial packages to minimum units', function () {
    $tenant = Tenant::factory()->create();
    $unit = ProductUnit::factory()->create(['name' => 'Unidad', 'code' => 'unit']);
    $box = ProductUnit::factory()->create(['name' => 'Caja', 'code' => 'box']);
    $product = Product::factory()->for($tenant)->for($unit, 'minimumUnit')->create();

    $presentation = ProductPresentation::factory()
        ->for($tenant)
        ->for($product)
        ->for($box, 'unit')
        ->create([
            'name' => 'Caja x 100',
            'minimum_unit_quantity' => 100,
        ]);

    expect($presentation->minimum_unit_quantity)->toBe(100)
        ->and($presentation->unit->code)->toBe('box')
        ->and($presentation->product->minimumUnit->code)->toBe('unit');
});
