<?php

use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function paymentForNewSale(Tenant $tenant, array $overrides = []): SalePayment
{
    $sale = Sale::withoutGlobalScopes()->create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'document_number' => 'FE-'.fake()->unique()->numberBetween(1000, 9999),
        'subtotal' => 10000,
        'tax_total' => 1900,
        'total' => 11900,
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);

    return SalePayment::factory()->for($tenant)->for($sale)->create($overrides);
}

test('guests cannot download a payment attachment', function () {
    $tenant = Tenant::factory()->create();
    $payment = paymentForNewSale($tenant, ['attachment_path' => 'attachments/receipt.pdf']);

    $this->get("/sales/payments/{$payment->id}/attachment")->assertRedirect('/login');
});

test('authenticated users can download an existing attachment', function () {
    Storage::fake('local');
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);

    $path = 'attachments/comprobante.pdf';
    Storage::put($path, UploadedFile::fake()->create('comprobante.pdf', 10)->get());

    $payment = paymentForNewSale($tenant, ['attachment_path' => $path]);

    $this->actingAs($user)
        ->get("/sales/payments/{$payment->id}/attachment")
        ->assertSuccessful()
        ->assertHeader('content-disposition');
});

test('requesting an attachment with no file recorded returns not found', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $payment = paymentForNewSale($tenant, ['attachment_path' => null]);

    $this->actingAs($user)
        ->get("/sales/payments/{$payment->id}/attachment")
        ->assertNotFound();
});

test('requesting an attachment whose file was deleted from storage returns not found', function () {
    Storage::fake('local');
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $payment = paymentForNewSale($tenant, ['attachment_path' => 'attachments/missing.pdf']);

    $this->actingAs($user)
        ->get("/sales/payments/{$payment->id}/attachment")
        ->assertNotFound();
});
