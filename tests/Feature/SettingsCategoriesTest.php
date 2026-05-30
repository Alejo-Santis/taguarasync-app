<?php

use App\Models\ProductCategory;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from categorias to login', function () {
    $this->get('/settings/categories')->assertRedirect('/login');
});

test('authenticated users see their tenant categories only', function () {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    ProductCategory::factory()->for($tenant)->create(['name' => 'Analgesicos']);
    ProductCategory::factory()->for($other)->create(['name' => 'Antibioticos']);

    $this->actingAs($user)
        ->get('/settings/categories')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Categories/Index')
            ->has('items.data', 1)
            ->where('items.data.0.name', 'Analgesicos')
        );
});

test('authenticated users can create a category', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    $this->actingAs($user)
        ->post('/settings/categories', ['name' => 'Dermatologicos'])
        ->assertRedirect();

    $this->assertDatabaseHas('product_categories', [
        'name' => 'Dermatologicos',
        'tenant_id' => $tenant->id,
    ]);
});

test('category name must be unique within tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    ProductCategory::factory()->for($tenant)->create(['name' => 'Vitaminas']);

    $this->actingAs($user)
        ->post('/settings/categories', ['name' => 'Vitaminas'])
        ->assertSessionHasErrors('name');
});

test('same category name is allowed across tenants', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    ProductCategory::factory()->for($tenant1)->create(['name' => 'Vitaminas']);
    $user = createOwnerUser($tenant2);

    $this->actingAs($user)
        ->post('/settings/categories', ['name' => 'Vitaminas'])
        ->assertRedirect();

    expect(ProductCategory::withoutGlobalScopes()->count())->toBe(2);
});

test('authenticated users can update a category', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $category = ProductCategory::factory()->for($tenant)->create(['name' => 'Analgesicos']);

    $this->actingAs($user)
        ->put("/settings/categories/{$category->id}", ['name' => 'Analgesicos y antipireticos'])
        ->assertRedirect();

    expect($category->fresh()->name)->toBe('Analgesicos y antipireticos');
});

test('toggle changes category active status', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $category = ProductCategory::factory()->for($tenant)->create(['is_active' => true]);

    $this->actingAs($user)
        ->patch("/settings/categories/{$category->id}/toggle")
        ->assertRedirect();

    expect($category->fresh()->is_active)->toBeFalse();
});
