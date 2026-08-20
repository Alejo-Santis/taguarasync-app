<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot access team management', function () {
    $this->get('/team')->assertRedirect('/login');
});

test('users without users.manage permission cannot see the team', function () {
    $tenant = Tenant::factory()->create();
    app(RoleAndPermissionSeeder::class)->run();
    $cashier = User::factory()->for($tenant)->create();
    $cashier->assignRole('cashier');

    $this->actingAs($cashier)
        ->from('/dashboard')
        ->get('/team')
        ->assertRedirect('/dashboard')
        ->assertSessionHas('error');
});

test('owners can list team members scoped to their tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $owner = createOwnerUser($tenant);
    $cashier = User::factory()->for($tenant)->create();
    $cashier->assignRole('cashier');
    User::factory()->for($otherTenant)->create();

    $this->actingAs($owner)
        ->get('/team')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Team/Index')
            ->has('members.data', 2)
        );
});

test('owners can invite a new team member with a role', function () {
    $tenant = Tenant::factory()->create(['max_users' => null]);
    $owner = createOwnerUser($tenant);

    $this->actingAs($owner)
        ->post('/team', [
            'name' => 'Nueva Cajera',
            'email' => 'cajera@example.com',
            'role' => 'cashier',
        ])
        ->assertRedirect();

    $member = User::where('email', 'cajera@example.com')->sole();

    expect($member->tenant_id)->toBe($tenant->id)
        ->and($member->hasRole('cashier'))->toBeTrue();
});

test('inviting a member beyond the plan limit is rejected', function () {
    $tenant = Tenant::factory()->create(['max_users' => 1]);
    $owner = createOwnerUser($tenant);

    $this->actingAs($owner)
        ->post('/team', [
            'name' => 'Otro Usuario',
            'email' => 'otro@example.com',
            'role' => 'cashier',
        ])
        ->assertSessionHasErrors('email');

    expect(User::where('email', 'otro@example.com')->exists())->toBeFalse();
});

test('owners can update a member name and role', function () {
    $tenant = Tenant::factory()->create();
    $owner = createOwnerUser($tenant);
    $member = User::factory()->for($tenant)->create(['name' => 'Antes']);
    $member->assignRole('cashier');

    $this->actingAs($owner)
        ->put("/team/{$member->id}", ['name' => 'Despues', 'role' => 'warehouse'])
        ->assertRedirect();

    $member->refresh();

    expect($member->name)->toBe('Despues')
        ->and($member->hasRole('warehouse'))->toBeTrue()
        ->and($member->hasRole('cashier'))->toBeFalse();
});

test('owners can reset a member password', function () {
    $tenant = Tenant::factory()->create();
    $owner = createOwnerUser($tenant);
    $member = User::factory()->for($tenant)->create();

    $originalPassword = $member->password;

    $this->actingAs($owner)
        ->post("/team/{$member->id}/reset-password")
        ->assertRedirect();

    expect($member->fresh()->password)->not->toBe($originalPassword);
});
