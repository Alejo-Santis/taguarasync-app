<?php

use App\Models\BankAccount;
use App\Models\BankAccountMovement;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function bankSettingsUser(Tenant $tenant): User
{
    $user = User::factory()->for($tenant)->create();
    app(RoleAndPermissionSeeder::class)->run();
    $user->assignRole('owner');

    return $user;
}

test('authenticated users can see bank accounts and movement totals', function () {
    $tenant = Tenant::factory()->create();
    $user = bankSettingsUser($tenant);
    $account = BankAccount::factory()->for($tenant)->create([
        'bank_name' => 'Bancolombia',
        'account_name' => 'Ventas POS',
    ]);

    BankAccountMovement::factory()->for($tenant)->for($account)->create([
        'type' => 'inflow',
        'amount' => 25000,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get('/settings/banks')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Banks/Index')
            ->where('stats.balance', 25000)
            ->where('stats.pending', 25000)
            ->where('items.data.0.bank_name', 'Bancolombia')
        );
});

test('authenticated users can create bank accounts', function () {
    $tenant = Tenant::factory()->create();
    $user = bankSettingsUser($tenant);

    $this->actingAs($user)
        ->post('/settings/banks', [
            'bank_name' => 'Nequi',
            'account_name' => 'Billetera ventas',
            'account_number' => '3001234567',
            'type' => 'wallet',
            'is_default' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('bank_accounts', [
        'tenant_id' => $tenant->id,
        'bank_name' => 'Nequi',
        'account_number' => '3001234567',
        'is_default' => true,
    ]);
});

test('setting a default bank account clears the previous default', function () {
    $tenant = Tenant::factory()->create();
    $user = bankSettingsUser($tenant);
    $first = BankAccount::factory()->for($tenant)->create(['is_default' => true]);
    $second = BankAccount::factory()->for($tenant)->create(['is_default' => false]);

    $this->actingAs($user)
        ->put("/settings/banks/{$second->id}", [
            'bank_name' => $second->bank_name,
            'account_name' => $second->account_name,
            'account_number' => $second->account_number,
            'type' => $second->type,
            'is_default' => true,
        ])
        ->assertRedirect();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});
