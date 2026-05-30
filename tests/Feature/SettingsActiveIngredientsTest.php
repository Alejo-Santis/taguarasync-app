<?php

use App\Models\ActiveIngredient;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from principios activos to login', function () {
    $this->get('/settings/active-ingredients')->assertRedirect('/login');
});

test('authenticated users see active ingredients list', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    ActiveIngredient::factory()->create(['dci_name' => 'Acetaminofen']);

    $this->actingAs($user)
        ->get('/settings/active-ingredients')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/ActiveIngredients/Index')
            ->has('items.data', 1)
            ->where('items.data.0.dci_name', 'Acetaminofen')
        );
});

test('authenticated users can create an active ingredient', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    $this->actingAs($user)
        ->post('/settings/active-ingredients', [
            'dci_name' => 'Ibuprofeno',
            'pharmacological_group' => 'Analgesico',
            'atc_classification' => 'M01AE01',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('active_ingredients', ['dci_name' => 'Ibuprofeno']);
});

test('dci name must be unique', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    ActiveIngredient::factory()->create(['dci_name' => 'Loratadina']);

    $this->actingAs($user)
        ->post('/settings/active-ingredients', ['dci_name' => 'Loratadina'])
        ->assertSessionHasErrors('dci_name');
});

test('authenticated users can update an active ingredient', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $ingredient = ActiveIngredient::factory()->create(['dci_name' => 'Omeprazol']);

    $this->actingAs($user)
        ->put("/settings/active-ingredients/{$ingredient->id}", [
            'dci_name' => 'Omeprazol 20mg',
            'pharmacological_group' => 'Gastroprotector',
        ])
        ->assertRedirect();

    expect($ingredient->fresh()->dci_name)->toBe('Omeprazol 20mg');
});

test('authenticated users can delete an active ingredient', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $ingredient = ActiveIngredient::factory()->create();

    $this->actingAs($user)
        ->delete("/settings/active-ingredients/{$ingredient->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('active_ingredients', ['id' => $ingredient->id]);
});
