<?php

use App\Actions\Fe\EmitCreditNote;
use App\Actions\Fe\EmitElectronicInvoice;
use App\Enums\FeStatus;
use App\Enums\PaymentForm;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\CreditNote;
use App\Models\FeResolution;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\TenantFeConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function consecutiveTenant(): Tenant
{
    Config::set('fe.api_url', 'https://api.example.test/api');
    Config::set('fe.ubl_prefix', '/ubl2.1');

    $tenant = Tenant::factory()->create();

    TenantFeConfig::create([
        'tenant_id' => $tenant->id,
        'api_token' => 'tenant-token',
        'environment' => 'test',
    ]);

    return $tenant;
}

function consecutiveResolution(Tenant $tenant, string $type, array $overrides = []): FeResolution
{
    return FeResolution::create(array_merge([
        'tenant_id' => $tenant->id,
        'code' => 'RES-'.fake()->unique()->numberBetween(1000, 9999),
        'type' => $type,
        'prefix' => $type === 'invoice' ? 'FV' : 'NC',
        'resolution_number' => '18760000001',
        'resolution_date' => '2026-01-01',
        'technical_key' => 'technical-key',
        'from_number' => 1,
        'to_number' => 10,
        'valid_from' => '2026-01-01',
        'valid_until' => '2027-01-01',
        'environment' => 'test',
        'is_active' => true,
    ], $overrides));
}

function consecutiveSale(Tenant $tenant, array $overrides = []): Sale
{
    return Sale::withoutGlobalScopes()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'document_number' => 'VTA-'.fake()->unique()->numberBetween(1000, 9999),
        'subtotal' => 10000,
        'tax_total' => 1900,
        'total' => 11900,
        'payment_method' => PaymentMethod::Cash,
        'payment_form' => PaymentForm::Cash,
        'status' => SaleStatus::Completed,
        'fe_status' => FeStatus::Pending,
    ], $overrides));
}

function acceptedNextpymeResponse(): array
{
    return [
        'cufe' => 'fake-cufe-1234567890',
        'QRStr' => 'fake-qr',
        'ResponseDian' => ['Envelope' => ['Body' => ['SendBillSyncResponse' => ['SendBillSyncResult' => [
            'IsValid' => 'true',
            'StatusCode' => '00',
            'XmlDocumentKey' => 'fake-cufe-1234567890',
        ]]]]],
    ];
}

test('a retried invoice reuses the same consecutive instead of consuming a new one', function () {
    $tenant = consecutiveTenant();
    $resolution = consecutiveResolution($tenant, 'invoice');
    $sale = consecutiveSale($tenant);

    Http::fake([
        'api.example.test/*' => Http::sequence()
            // NextpymeClient retries transient HTTP failures internally (2 attempts)
            // before giving up, so the first execute() call consumes 2 failures.
            ->push(['message' => 'Timeout'], 500)
            ->push(['message' => 'Timeout'], 500)
            ->push(acceptedNextpymeResponse()),
    ]);

    try {
        app(EmitElectronicInvoice::class)->execute($sale, $tenant);
    } catch (Throwable) {
        // Expected: recoverable failure is rethrown so the queue job retries.
    }

    $sale->refresh();
    expect($sale->fe_status)->toBe(FeStatus::Contingency)
        ->and($sale->invoice_number)->toBe(1)
        ->and($sale->fe_resolution_id)->toBe($resolution->id);
    expect($resolution->send()->first()->next_consecutive)->toBe(1);

    app(EmitElectronicInvoice::class)->execute($sale, $tenant);

    $sale->refresh();
    expect($sale->fe_status)->toBe(FeStatus::Accepted)
        ->and($sale->invoice_number)->toBe(1)
        ->and($sale->fe_resolution_id)->toBe($resolution->id);
    expect($resolution->send()->first()->next_consecutive)->toBe(1);
});

test('invoice emission is put in contingency when the only active resolution is exhausted', function () {
    $tenant = consecutiveTenant();
    $resolution = consecutiveResolution($tenant, 'invoice', ['from_number' => 1, 'to_number' => 1]);
    $resolution->consumeNextNumber();

    $sale = consecutiveSale($tenant);

    app(EmitElectronicInvoice::class)->execute($sale, $tenant);

    $sale->refresh();
    expect($sale->fe_status)->toBe(FeStatus::Contingency)
        ->and($sale->fe_error_message)->toContain('agotada')
        ->and($sale->invoice_number)->toBeNull();
});

test('invoice emission deterministically skips an exhausted resolution in favor of one with room', function () {
    $tenant = consecutiveTenant();
    $exhausted = consecutiveResolution($tenant, 'invoice', [
        'code' => 'RES-EXHAUSTED',
        'prefix' => 'FA',
        'from_number' => 1,
        'to_number' => 1,
        'valid_from' => '2026-01-01',
    ]);
    $exhausted->consumeNextNumber();

    $withRoom = consecutiveResolution($tenant, 'invoice', [
        'code' => 'RES-ROOM',
        'prefix' => 'FB',
        'from_number' => 1,
        'to_number' => 10,
        'valid_from' => '2026-02-01',
    ]);

    $sale = consecutiveSale($tenant);

    Http::fake(['api.example.test/*' => Http::response(acceptedNextpymeResponse())]);

    app(EmitElectronicInvoice::class)->execute($sale, $tenant);

    $sale->refresh();
    expect($sale->fe_status)->toBe(FeStatus::Accepted)
        ->and($sale->fe_resolution_id)->toBe($withRoom->id)
        ->and($sale->invoice_prefix)->toBe('FB');
});

test('a retried credit note reuses the same consecutive instead of consuming a new one', function () {
    $tenant = consecutiveTenant();
    $resolution = consecutiveResolution($tenant, 'credit_note');
    $sale = consecutiveSale($tenant, ['fe_status' => FeStatus::Accepted, 'fe_cufe' => 'sale-cufe']);

    $creditNote = CreditNote::create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'sale_id' => $sale->id,
        'number' => (string) time(),
        'discrepancy_reason_code' => '1',
        'subtotal' => 10000,
        'tax_total' => 1900,
        'total' => 11900,
        'fe_status' => FeStatus::Pending,
    ]);

    Http::fake([
        'api.example.test/*' => Http::sequence()
            // NextpymeClient retries transient HTTP failures internally (2 attempts)
            // before giving up, so the first execute() call consumes 2 failures.
            ->push(['message' => 'Timeout'], 500)
            ->push(['message' => 'Timeout'], 500)
            ->push(acceptedNextpymeResponse()),
    ]);

    try {
        app(EmitCreditNote::class)->execute($creditNote, $tenant);
    } catch (Throwable) {
        // Expected: recoverable failure is rethrown so the queue job retries.
    }

    $creditNote->refresh();
    expect($creditNote->fe_status)->toBe(FeStatus::Contingency)
        ->and($creditNote->number)->toBe('1')
        ->and($creditNote->fe_resolution_id)->toBe($resolution->id);

    app(EmitCreditNote::class)->execute($creditNote, $tenant);

    $creditNote->refresh();
    expect($creditNote->fe_status)->toBe(FeStatus::Accepted)
        ->and($creditNote->number)->toBe('1')
        ->and($creditNote->fe_resolution_id)->toBe($resolution->id);
    expect($resolution->send()->first()->next_consecutive)->toBe(1);
});

test('credit note emission is put in contingency when the only active resolution is exhausted', function () {
    $tenant = consecutiveTenant();
    $resolution = consecutiveResolution($tenant, 'credit_note', ['from_number' => 1, 'to_number' => 1]);
    $resolution->consumeNextNumber();

    $sale = consecutiveSale($tenant, ['fe_status' => FeStatus::Accepted, 'fe_cufe' => 'sale-cufe']);
    $creditNote = CreditNote::create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'sale_id' => $sale->id,
        'number' => (string) time(),
        'discrepancy_reason_code' => '1',
        'subtotal' => 10000,
        'tax_total' => 1900,
        'total' => 11900,
        'fe_status' => FeStatus::Pending,
    ]);

    app(EmitCreditNote::class)->execute($creditNote, $tenant);

    $creditNote->refresh();
    expect($creditNote->fe_status)->toBe(FeStatus::Contingency)
        ->and($creditNote->fe_error_message)->toContain('agotada')
        ->and($creditNote->fe_resolution_id)->toBeNull();
});
