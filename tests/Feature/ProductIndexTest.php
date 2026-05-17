<?php

use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from product catalog to login', function () {
    $this->get('/products')->assertRedirect('/login');
});

test('authenticated users only see products from their tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $unit = ProductUnit::factory()->create(['name' => 'Unidad', 'code' => 'unit']);

    $visibleProduct = Product::factory()
        ->for($tenant)
        ->for($unit, 'minimumUnit')
        ->create(['commercial_name' => 'Producto visible']);

    ProductPresentation::factory()
        ->for($tenant)
        ->for($visibleProduct)
        ->for($unit, 'unit')
        ->create(['name' => 'Blister x 10', 'minimum_unit_quantity' => 10]);

    Product::factory()
        ->for($otherTenant)
        ->for($unit, 'minimumUnit')
        ->create(['commercial_name' => 'Producto oculto']);

    $user = User::factory()->for($tenant)->create();

    $this
        ->actingAs($user)
        ->get('/products?q=visible')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Index')
            ->where('filters.q', 'visible')
            ->where('stats.total', 1)
            ->where('stats.presentations', 1)
            ->has('products.data', 1)
            ->where('products.data.0.commercial_name', 'Producto visible')
            ->where('products.data.0.presentations_count', 1)
            ->where('products.data.0.presentations.0.name', 'Blister x 10')
            ->etc()
        );
});
