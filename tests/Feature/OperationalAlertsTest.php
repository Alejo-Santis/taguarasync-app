<?php

use App\Actions\Operations\GenerateOperationalAlerts;
use App\Enums\PurchaseRadianStatus;
use App\Models\BankAccount;
use App\Models\BankAccountMovement;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('operational alerts notify users about radian pending purchases and bank differences', function () {
    app(RoleAndPermissionSeeder::class)->run();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $user->assignRole('owner');
    $supplier = Supplier::factory()->for($tenant)->create(['name' => 'Proveedor RADIAN']);
    $account = BankAccount::factory()->for($tenant)->create(['bank_name' => 'Nequi']);

    PurchaseReceipt::factory()->for($tenant)->for($supplier)->for($user)->create([
        'document_number' => 'FAC-PEND-001',
        'radian_status' => PurchaseRadianStatus::Pending,
    ]);

    BankAccountMovement::factory()->for($tenant)->for($account)->create([
        'reference' => 'TRX-DIFF-001',
        'status' => 'difference',
        'amount' => 25000,
    ]);

    $summary = app(GenerateOperationalAlerts::class)->execute();

    $notifications = $user->fresh()->unreadNotifications;

    expect($summary['alerts'])->toBe(2)
        ->and($summary['recipients'])->toBe(2)
        ->and($notifications)->toHaveCount(2)
        ->and($notifications->pluck('data.title')->all())->toContain('Compra pendiente RADIAN')
        ->and($notifications->pluck('data.title')->all())->toContain('Movimiento bancario con diferencia');
});
