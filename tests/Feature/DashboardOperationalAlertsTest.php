<?php

use App\Enums\PurchaseRadianStatus;
use App\Models\BankAccount;
use App\Models\BankAccountMovement;
use App\Models\PurchaseReceipt;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('dashboard shows operational risk counters', function () {
    app(RoleAndPermissionSeeder::class)->run();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $user->assignRole('owner');
    $account = BankAccount::factory()->for($tenant)->create();

    PurchaseReceipt::factory()->for($tenant)->for($user)->create([
        'radian_status' => PurchaseRadianStatus::Pending,
    ]);

    BankAccountMovement::factory()->for($tenant)->for($account)->create([
        'status' => 'difference',
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('alerts.radian_pending', 1)
            ->where('alerts.bank_differences', 1)
            ->etc()
        );
});
