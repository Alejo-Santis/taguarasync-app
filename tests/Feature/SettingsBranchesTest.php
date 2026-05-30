<?php

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from sucursales to login', function () {
    $this->get('/settings/branches')->assertRedirect('/login');
});

test('authenticated users can see their tenant branches', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    Branch::factory()->for($tenant)->create(['name' => 'Sucursal Norte']);

    $this->actingAs($user)
        ->get('/settings/branches')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Branches/Index')
            ->has('branches.data')
        );

    $this->assertDatabaseHas('branches', ['name' => 'Sucursal Norte', 'tenant_id' => $tenant->id]);
});

test('authenticated users can create a branch', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    $this->actingAs($user)
        ->post('/settings/branches', [
            'name' => 'Sucursal Centro',
            'address' => 'Calle 10 # 5-20',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('branches', [
        'name' => 'Sucursal Centro',
        'tenant_id' => $tenant->id,
    ]);
});

test('authenticated users can update a branch', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $branch = Branch::factory()->for($tenant)->create(['name' => 'Sucursal Vieja']);

    $this->actingAs($user)
        ->put("/settings/branches/{$branch->id}", ['name' => 'Sucursal Renovada', 'address' => $branch->address])
        ->assertRedirect();

    expect($branch->fresh()->name)->toBe('Sucursal Renovada');
});

test('toggle changes branch active status', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    // Se necesitan al menos dos sucursales activas para poder desactivar una
    $keep = Branch::factory()->for($tenant)->create(['is_active' => true]);
    $toggled = Branch::factory()->for($tenant)->create(['is_active' => true]);

    $this->actingAs($user)
        ->patch("/settings/branches/{$toggled->id}/toggle")
        ->assertRedirect();

    expect($toggled->fresh()->is_active)->toBeFalse();
});

test('no se puede desactivar la unica sucursal activa', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $branch = Branch::factory()->for($tenant)->create(['is_active' => true]);

    $this->actingAs($user)
        ->patch("/settings/branches/{$branch->id}/toggle")
        ->assertStatus(422);

    expect($branch->fresh()->is_active)->toBeTrue();
});
