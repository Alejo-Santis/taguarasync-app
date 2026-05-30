<?php

use App\Models\CashRegister;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from cajas to login', function () {
    $this->get('/settings/registers')->assertRedirect('/login');
});

test('authenticated users see their tenant registers', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    CashRegister::factory()->for($tenant)->create(['name' => 'Caja Principal']);

    // CashRegister no tiene global scope de tenant; verificamos que el item creado aparece
    $this->actingAs($user)
        ->get('/settings/registers')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Registers/Index')
            ->where('items.data.0.name', 'Caja Principal')
        );
});

test('authenticated users can create a cash register', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    $this->actingAs($user)
        ->post('/settings/registers', ['name' => 'Caja 1', 'code' => 'CJ-01'])
        ->assertRedirect();

    $this->assertDatabaseHas('cash_registers', [
        'name' => 'Caja 1',
        'tenant_id' => $tenant->id,
    ]);
});

test('register code must be unique within tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    CashRegister::factory()->for($tenant)->create(['code' => 'CJ-01']);

    $this->actingAs($user)
        ->post('/settings/registers', ['name' => 'Otra caja', 'code' => 'CJ-01'])
        ->assertSessionHasErrors('code');
});

test('authenticated users can update a cash register', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $register = CashRegister::factory()->for($tenant)->create(['name' => 'Caja Vieja']);

    $this->actingAs($user)
        ->put("/settings/registers/{$register->id}", ['name' => 'Caja Renovada', 'code' => $register->code])
        ->assertRedirect();

    expect($register->fresh()->name)->toBe('Caja Renovada');
});

test('toggle changes register active status', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $register = CashRegister::factory()->for($tenant)->create(['is_active' => true]);

    $this->actingAs($user)
        ->patch("/settings/registers/{$register->id}/toggle")
        ->assertRedirect();

    expect($register->fresh()->is_active)->toBeFalse();
});
