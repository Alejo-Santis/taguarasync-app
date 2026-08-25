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
            ->where('stats.difference', 0)
            ->where('items.data.0.bank_name', 'Bancolombia')
            ->has('movementStatuses', 3)
            ->where('movements.data.0.status_label', 'Pendiente')
        );
});

test('authenticated users can reconcile bank movements', function () {
    $tenant = Tenant::factory()->create();
    $user = bankSettingsUser($tenant);
    $account = BankAccount::factory()->for($tenant)->create();
    $movement = BankAccountMovement::factory()->for($tenant)->for($account)->create([
        'type' => 'inflow',
        'amount' => 87000,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->patch("/settings/banks/movements/{$movement->id}/reconcile", [
            'status' => 'confirmed',
            'reconciliation_notes' => 'Cruza con extracto bancario.',
        ])
        ->assertRedirect();

    $movement->refresh();

    expect($movement->status)->toBe('confirmed')
        ->and($movement->reconciled_at)->not->toBeNull()
        ->and($movement->reconciled_by_user_id)->toBe($user->id)
        ->and($movement->reconciliation_notes)->toBe('Cruza con extracto bancario.');

    $this->actingAs($user)
        ->patch("/settings/banks/movements/{$movement->id}/reconcile", [
            'status' => 'pending',
        ])
        ->assertRedirect();

    $movement->refresh();

    expect($movement->status)->toBe('pending')
        ->and($movement->reconciled_at)->toBeNull()
        ->and($movement->reconciled_by_user_id)->toBeNull();
});

test('bank movement difference status is reflected in totals and filters', function () {
    $tenant = Tenant::factory()->create();
    $user = bankSettingsUser($tenant);
    $account = BankAccount::factory()->for($tenant)->create(['bank_name' => 'Davivienda']);

    BankAccountMovement::factory()->for($tenant)->for($account)->create([
        'amount' => 42000,
        'status' => 'difference',
    ]);
    BankAccountMovement::factory()->for($tenant)->for($account)->create([
        'amount' => 10000,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get('/settings/banks?movement_status=difference')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Banks/Index')
            ->where('filters.movement_status', 'difference')
            ->where('stats.difference', 42000)
            ->has('movements.data', 1)
            ->where('movements.data.0.status', 'difference')
            ->where('movements.data.0.status_label', 'Con diferencia')
        );
});

test('authenticated users can export bank movements as csv', function () {
    $tenant = Tenant::factory()->create();
    $user = bankSettingsUser($tenant);
    $account = BankAccount::factory()->for($tenant)->create([
        'bank_name' => 'Bancolombia',
        'account_name' => 'Ventas POS',
    ]);

    BankAccountMovement::factory()->for($tenant)->for($account)->create([
        'amount' => 31500,
        'reference' => 'TRX-CSV',
        'status' => 'confirmed',
        'occurred_at' => '2026-05-20 10:00:00',
    ]);

    $response = $this->actingAs($user)
        ->get('/reports/banks/export?from=2026-05-01&to=2026-05-31')
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())
        ->toContain('TRX-CSV')
        ->toContain('Bancolombia');
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
