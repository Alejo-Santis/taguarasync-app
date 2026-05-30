<?php

use App\Models\ProductUnit;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from unidades to login', function () {
    $this->get('/settings/units')->assertRedirect('/login');
});

test('authenticated users see units list', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    ProductUnit::factory()->create(['name' => 'Tableta']);

    $this->actingAs($user)
        ->get('/settings/units')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Units/Index')
            ->has('items.data', 1)
            ->where('items.data.0.name', 'Tableta')
        );
});

test('authenticated users can create a unit', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    $this->actingAs($user)
        ->post('/settings/units', [
            'name' => 'Ampolla',
            'code' => 'AMP',
            'kind' => 'minimum',
            'allows_decimals' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('product_units', ['name' => 'Ampolla', 'code' => 'AMP']);
});

test('unit code must be unique', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    ProductUnit::factory()->create(['code' => 'TAB']);

    $this->actingAs($user)
        ->post('/settings/units', ['name' => 'Tableta2', 'code' => 'TAB', 'kind' => 'minimum', 'allows_decimals' => false])
        ->assertSessionHasErrors('code');
});

test('authenticated users can update a unit', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $unit = ProductUnit::factory()->create(['name' => 'Caja']);

    $this->actingAs($user)
        ->put("/settings/units/{$unit->id}", ['name' => 'Caja x 24', 'code' => $unit->code, 'kind' => $unit->kind->value, 'allows_decimals' => false])
        ->assertRedirect();

    expect($unit->fresh()->name)->toBe('Caja x 24');
});

test('toggle changes unit active status', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $unit = ProductUnit::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->patch("/settings/units/{$unit->id}/toggle")
        ->assertRedirect();

    expect($unit->fresh()->is_active)->toBeFalse();
});
