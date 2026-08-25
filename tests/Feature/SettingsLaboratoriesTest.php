<?php

use App\Models\Laboratory;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from laboratorios to login', function () {
    $this->get('/settings/laboratories')->assertRedirect('/login');
});

test('authenticated users see their tenant laboratorios only', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    Laboratory::factory()->for($tenant)->create(['name' => 'Genfar']);
    Laboratory::factory()->for($otherTenant)->create(['name' => 'Otro laboratorio']);

    $this->actingAs($user)
        ->get('/settings/laboratories')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Laboratories/Index')
            ->where('stats.total', 1)
            ->has('items.data', 1)
            ->where('items.data.0.name', 'Genfar')
        );
});

test('authenticated users can create a laboratory', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    $this->actingAs($user)
        ->post('/settings/laboratories', [
            'name' => 'Bayer Colombia',
            'nit' => '860015696-3',
            'country' => 'CO',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('laboratories', [
        'name' => 'Bayer Colombia',
        'tenant_id' => $tenant->id,
        'nit' => '8600156963',
    ]);
});

test('laboratory name must be unique within tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    Laboratory::factory()->for($tenant)->create(['name' => 'Genfar']);

    $this->actingAs($user)
        ->post('/settings/laboratories', ['name' => 'Genfar'])
        ->assertSessionHasErrors('name');
});

test('same name is allowed across different tenants', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    Laboratory::factory()->for($tenant1)->create(['name' => 'Genfar']);
    $user = createOwnerUser($tenant2);

    $this->actingAs($user)
        ->post('/settings/laboratories', ['name' => 'Genfar'])
        ->assertRedirect();

    expect(Laboratory::withoutGlobalScopes()->count())->toBe(2);
});

test('authenticated users can update a laboratory', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $lab = Laboratory::factory()->for($tenant)->create(['name' => 'Genfar']);

    $this->actingAs($user)
        ->put("/settings/laboratories/{$lab->id}", ['name' => 'Genfar S.A.', 'nit' => '800099953-1'])
        ->assertRedirect();

    expect($lab->fresh()->name)->toBe('Genfar S.A.')
        ->and($lab->fresh()->nit)->toBe('8000999531');
});

test('toggle changes laboratory active status', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $lab = Laboratory::factory()->for($tenant)->create(['is_active' => true]);

    $this->actingAs($user)
        ->patch("/settings/laboratories/{$lab->id}/toggle")
        ->assertRedirect();

    expect($lab->fresh()->is_active)->toBeFalse();
});

test('configuracion redirect goes to laboratorios', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    $this->actingAs($user)
        ->get('/settings')
        ->assertRedirect('/settings/laboratories');
});
