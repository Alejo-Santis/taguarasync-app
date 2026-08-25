<?php

use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from proveedores to login', function () {
    $this->get('/settings/suppliers')->assertRedirect('/login');
});

test('authenticated users see their tenant proveedores only', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    Supplier::factory()->for($tenant)->create(['name' => 'Distribuidora ABC']);
    Supplier::factory()->for($otherTenant)->create(['name' => 'Otro proveedor']);

    $this->actingAs($user)
        ->get('/settings/suppliers')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Suppliers/Index')
            ->where('stats.total', 1)
            ->has('items.data', 1)
            ->where('items.data.0.name', 'Distribuidora ABC')
        );
});

test('authenticated users can create a proveedor', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    $this->actingAs($user)
        ->post('/settings/suppliers', [
            'name' => 'Distribuidora XYZ S.A.S.',
            'nit' => '900111222-3',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('suppliers', [
        'name' => 'Distribuidora XYZ S.A.S.',
        'tenant_id' => $tenant->id,
        'nit' => '9001112223',
    ]);
});

test('proveedor name must be unique within tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    Supplier::factory()->for($tenant)->create(['name' => 'Distribuidora ABC']);

    $this->actingAs($user)
        ->post('/settings/suppliers', ['name' => 'Distribuidora ABC'])
        ->assertSessionHasErrors('name');
});

test('toggle changes supplier active status', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $supplier = Supplier::factory()->for($tenant)->create(['is_active' => true]);

    $this->actingAs($user)
        ->patch("/settings/suppliers/{$supplier->uuid}/toggle")
        ->assertRedirect();

    expect($supplier->fresh()->is_active)->toBeFalse();
});
