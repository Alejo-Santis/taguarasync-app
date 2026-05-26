<?php

use App\Enums\FeStatus;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseReceiptStatus;
use App\Enums\SaleStatus;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('fiscal report consolidates sales credit notes purchases and vat', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $product = Product::factory()->for($tenant)->create(['commercial_name' => 'Acetaminofen 500mg']);

    Permission::findOrCreate('reports.view');
    $user->givePermissionTo('reports.view');

    $sale = Sale::withoutGlobalScopes()->create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'document_number' => 'FE-1001',
        'subtotal' => 100000,
        'tax_total' => 19000,
        'total' => 119000,
        'payment_method' => PaymentMethod::Cash,
        'status' => SaleStatus::Completed,
        'fe_status' => FeStatus::Accepted,
        'created_at' => Carbon::parse('2026-05-10 10:00:00'),
        'updated_at' => Carbon::parse('2026-05-10 10:00:00'),
    ]);

    SaleItem::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'description' => 'Acetaminofen 500mg',
        'quantity' => 1,
        'unit_price' => 100000,
        'tax_rate' => 19,
        'line_subtotal' => 100000,
        'line_tax' => 19000,
        'line_total' => 119000,
    ]);

    $creditNote = CreditNote::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'sale_id' => $sale->id,
        'user_id' => $user->id,
        'prefix' => 'NC',
        'number' => '10',
        'discrepancy_reason_code' => '1',
        'subtotal' => 20000,
        'tax_total' => 3800,
        'total' => 23800,
        'fe_status' => FeStatus::Accepted,
        'created_at' => Carbon::parse('2026-05-11 09:00:00'),
        'updated_at' => Carbon::parse('2026-05-11 09:00:00'),
    ]);

    CreditNoteItem::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'credit_note_id' => $creditNote->id,
        'product_id' => $product->id,
        'description' => 'Acetaminofen 500mg',
        'quantity' => 1,
        'unit_price' => 20000,
        'tax_rate' => 19,
        'line_subtotal' => 20000,
        'line_tax' => 3800,
        'line_total' => 23800,
    ]);

    $purchase = PurchaseReceipt::factory()->for($tenant)->for($user)->create([
        'document_number' => 'COMP-55',
        'received_at' => Carbon::parse('2026-05-12 14:00:00'),
        'subtotal' => 30000,
        'tax_total' => 5700,
        'total' => 35700,
        'status' => PurchaseReceiptStatus::Received,
    ]);

    PurchaseReceiptItem::factory()->for($tenant)->for($purchase, 'receipt')->for($product)->create([
        'product_presentation_id' => null,
        'inventory_lot_id' => null,
        'inventory_movement_id' => null,
        'description' => 'Acetaminofen 500mg',
        'lot_number' => 'L-IVA',
        'quantity' => 1,
        'unit_cost' => 30000,
        'tax_rate' => 19,
        'line_subtotal' => 30000,
        'line_tax' => 5700,
        'line_total' => 35700,
    ]);

    Sale::withoutGlobalScopes()->create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $otherTenant->id,
        'document_number' => 'FE-OTHER',
        'subtotal' => 999999,
        'tax_total' => 999999,
        'total' => 999999,
        'payment_method' => PaymentMethod::Cash,
        'status' => SaleStatus::Completed,
        'created_at' => Carbon::parse('2026-05-10 10:00:00'),
        'updated_at' => Carbon::parse('2026-05-10 10:00:00'),
    ]);

    $this->actingAs($user)
        ->get('/reports/fiscal?from=2026-05-01&to=2026-05-31')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Fiscal')
            ->where('summary.sales.count', 1)
            ->where('summary.sales.tax_total', 19000)
            ->where('summary.credit_notes.tax_total', 3800)
            ->where('summary.purchases.tax_total', 5700)
            ->where('summary.vat.generated', 15200)
            ->where('summary.vat.discounted', 5700)
            ->where('summary.vat.payable', 9500)
            ->where('tax_breakdown.0.rate', '19.00')
            ->where('tax_breakdown.0.net_generated_tax', 15200)
            ->has('documents', 3)
            ->etc()
        );
});

test('authenticated users can export fiscal book as csv', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('reports.view');
    $user->givePermissionTo('reports.view');

    Sale::withoutGlobalScopes()->create([
        'uuid' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'document_number' => 'FE-CSV-1001',
        'subtotal' => 50000,
        'tax_total' => 9500,
        'total' => 59500,
        'payment_method' => PaymentMethod::Cash,
        'status' => SaleStatus::Completed,
        'fe_status' => FeStatus::Accepted,
        'created_at' => Carbon::parse('2026-05-10 10:00:00'),
        'updated_at' => Carbon::parse('2026-05-10 10:00:00'),
    ]);

    $response = $this->actingAs($user)
        ->get('/reports/fiscal/export?from=2026-05-01&to=2026-05-31')
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())
        ->toContain('FE-CSV-1001')
        ->toContain('Factura venta');
});
