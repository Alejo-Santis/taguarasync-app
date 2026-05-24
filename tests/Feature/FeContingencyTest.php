<?php

use App\Actions\Fe\GenerateFeTransmissionAlerts;
use App\Enums\FeStatus;
use App\Enums\PaymentForm;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Jobs\EmitElectronicInvoiceJob;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function feContingencyUser(Tenant $tenant, string $role = 'owner'): User
{
    app(RoleAndPermissionSeeder::class)->run();

    $user = User::factory()->for($tenant)->create();
    $user->assignRole($role);

    return $user;
}

function feContingencySale(Tenant $tenant, User $user, FeStatus $status, array $overrides = []): Sale
{
    return Sale::withoutGlobalScopes()->create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'document_number' => 'FE-'.fake()->unique()->numberBetween(1000, 9999),
        'subtotal' => 10000,
        'tax_total' => 1900,
        'total' => 11900,
        'payment_method' => PaymentMethod::Cash,
        'payment_form' => PaymentForm::Cash,
        'status' => SaleStatus::Completed,
        'fe_status' => $status,
        'fe_error_message' => null,
        ...$overrides,
    ]);
}

test('contingency invoices are queued for retry and reset to pending', function () {
    Queue::fake();

    $tenant = Tenant::factory()->create();
    $user = feContingencyUser($tenant);
    $sale = feContingencySale($tenant, $user, FeStatus::Contingency, [
        'fe_error_message' => 'No se pudo conectar con NextPyme.',
    ]);

    $this->artisan('fe:retry-contingency')
        ->expectsOutputToContain('Facturas FE reintentadas: 1.')
        ->assertSuccessful();

    $sale->refresh();

    expect($sale->fe_status)->toBe(FeStatus::Pending)
        ->and($sale->fe_error_message)->toBeNull();

    Queue::assertPushed(
        EmitElectronicInvoiceJob::class,
        fn (EmitElectronicInvoiceJob $job): bool => $job->saleId === $sale->id && $job->tenantId === $tenant->id
    );
});

test('failed electronic invoice jobs leave the sale in contingency', function () {
    $tenant = Tenant::factory()->create();
    $user = feContingencyUser($tenant);
    $sale = feContingencySale($tenant, $user, FeStatus::Pending);

    (new EmitElectronicInvoiceJob($sale->id, $tenant->id))->failed(new RuntimeException('NextPyme no responde.'));

    $sale->refresh();

    expect($sale->fe_status)->toBe(FeStatus::Contingency)
        ->and($sale->fe_error_message)->toBe('NextPyme no responde.');
});

test('fe alert generation notifies billing users about contingency invoices', function () {
    $tenant = Tenant::factory()->create();
    $user = feContingencyUser($tenant, 'accountant');

    feContingencySale($tenant, $user, FeStatus::Contingency, [
        'document_number' => 'FE-9201',
        'fe_error_message' => 'Timeout consultando DIAN.',
    ]);

    $summary = app(GenerateFeTransmissionAlerts::class)->execute();
    $notification = $user->fresh()->unreadNotifications->first();

    expect($summary['alerts'])->toBe(1)
        ->and($summary['recipients'])->toBe(1)
        ->and($notification)->not->toBeNull()
        ->and($notification->data['title'])->toBe('Factura en contingencia')
        ->and($notification->data['href'])->toBe('/fe/submissions?status=contingency')
        ->and($notification->data['body'])->toContain('FE-9201')
        ->and($notification->data['body'])->toContain('Timeout consultando DIAN.');
});
