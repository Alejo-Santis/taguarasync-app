<?php

use App\Enums\ProductStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('guests cannot access global search', function () {
    $this->getJson('/search?q=test')->assertUnauthorized();
});

test('short queries return empty results', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    $this->actingAs($user)
        ->getJson('/search?q=a')
        ->assertOk()
        ->assertJson(['results' => []]);
});

test('search finds products by commercial name', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    Product::factory()->for($tenant)->create([
        'commercial_name' => 'Acetaminofen 500mg',
        'status' => ProductStatus::Active,
    ]);

    $response = $this->actingAs($user)
        ->getJson('/search?q=Acetaminofen')
        ->assertOk();

    $types = collect($response->json('results'))->pluck('type');
    expect($types)->toContain('product');
});

test('search finds products by barcode', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    Product::factory()->for($tenant)->create([
        'barcode' => '7702001234567',
        'status' => ProductStatus::Active,
    ]);

    $response = $this->actingAs($user)
        ->getJson('/search?q=7702001234567')
        ->assertOk();

    $types = collect($response->json('results'))->pluck('type');
    expect($types)->toContain('product');
});

test('search finds sales by document number', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    Sale::create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'document_number' => 'FV-0001',
        'payment_method' => 'cash',
        'subtotal' => 10000,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => 10000,
    ]);

    $response = $this->actingAs($user)
        ->getJson('/search?q=FV-0001')
        ->assertOk();

    $types = collect($response->json('results'))->pluck('type');
    expect($types)->toContain('sale');
});

test('search finds purchase receipts by document number', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $supplier = Supplier::factory()->for($tenant)->create();

    PurchaseReceipt::factory()->for($tenant)->for($supplier)->create(['document_number' => 'FAC-999']);

    $response = $this->actingAs($user)
        ->getJson('/search?q=FAC-999')
        ->assertOk();

    $types = collect($response->json('results'))->pluck('type');
    expect($types)->toContain('receipt');
});

test('search finds customers by name', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    Customer::create([
        'tenant_id' => $tenant->id,
        'uuid' => (string) Str::uuid(),
        'identification_type_code' => 'CC',
        'identification_number' => '123456789',
        'first_name' => 'Maria',
        'last_name' => 'Gonzalez',
    ]);

    $response = $this->actingAs($user)
        ->getJson('/search?q=Maria')
        ->assertOk();

    $types = collect($response->json('results'))->pluck('type');
    expect($types)->toContain('customer');
});

test('search finds suppliers by name', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    Supplier::factory()->for($tenant)->create(['name' => 'Drogueria del Valle']);

    $response = $this->actingAs($user)
        ->getJson('/search?q=Drogueria')
        ->assertOk();

    $types = collect($response->json('results'))->pluck('type');
    expect($types)->toContain('supplier');
});

test('search does not return results from other tenants', function () {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    Product::factory()->for($other)->create([
        'commercial_name' => 'ProductoSecreto',
        'status' => ProductStatus::Active,
    ]);

    $response = $this->actingAs($user)
        ->getJson('/search?q=ProductoSecreto')
        ->assertOk();

    expect($response->json('results'))->toBeEmpty();
});
