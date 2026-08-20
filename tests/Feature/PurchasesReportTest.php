<?php

use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot access the purchases report', function () {
    $this->get('/reports/purchases')->assertRedirect('/login');
});

test('purchases report only counts received receipts within the date range', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $supplier = Supplier::factory()->for($tenant)->create(['name' => 'Distribuidora Salud SAS']);

    PurchaseReceipt::factory()->for($tenant)->for($user)->for($supplier)->create([
        'document_number' => 'COMP-100',
        'received_at' => '2026-06-15 10:00:00',
        'status' => 'received',
        'subtotal' => 100000,
        'tax_total' => 19000,
        'total' => 119000,
    ]);

    // Draft receipts must not count toward the report.
    PurchaseReceipt::factory()->for($tenant)->for($user)->for($supplier)->create([
        'document_number' => 'COMP-DRAFT',
        'received_at' => '2026-06-16 10:00:00',
        'status' => 'draft',
        'total' => 999999,
    ]);

    // Outside the filtered range.
    PurchaseReceipt::factory()->for($tenant)->for($user)->for($supplier)->create([
        'document_number' => 'COMP-OLD',
        'received_at' => '2026-01-01 10:00:00',
        'status' => 'received',
        'total' => 888888,
    ]);

    $this->actingAs($user)
        ->get('/reports/purchases?from=2026-06-01&to=2026-06-30')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Purchases')
            ->where('summary.count', 1)
            ->where('summary.total', 119000)
            ->where('summary.tax_total', 19000)
            ->has('receipts.data', 1)
            ->where('receipts.data.0.document_number', 'COMP-100')
            ->where('receipts.data.0.supplier', 'Distribuidora Salud SAS')
        );
});
