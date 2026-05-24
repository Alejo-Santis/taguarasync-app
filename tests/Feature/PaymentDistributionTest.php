<?php

use App\Actions\Pos\CloseCashSession;
use App\Actions\Pos\ProcessSale;
use App\Enums\CashSessionStatus;
use App\Enums\ProductStatus;
use App\Models\BankAccount;
use App\Models\BankAccountMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\InventoryLot;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\SalePayment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function paymentDistributionSetup(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $unit = ProductUnit::factory()->create(['code' => 'und', 'is_active' => true]);

    $register = CashRegister::factory()->for($tenant)->create();
    $session = CashSession::create([
        'tenant_id' => $tenant->id,
        'cash_register_id' => $register->id,
        'user_id' => $user->id,
        'opening_amount' => 50000,
        'status' => CashSessionStatus::Open,
        'opened_at' => now(),
    ]);

    $product = Product::factory()->for($tenant)->for($unit, 'minimumUnit')->create([
        'sale_price' => 10000,
        'tax_rate' => 0,
        'status' => ProductStatus::Active,
    ]);

    $presentation = ProductPresentation::factory()->for($product)->for($unit, 'unit')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Unidad',
        'sale_price' => 10000,
        'minimum_unit_quantity' => 1,
        'is_default' => true,
        'is_active' => true,
    ]);

    $lot = InventoryLot::factory()->for($tenant)->for($product)->create([
        'product_presentation_id' => $presentation->id,
        'current_quantity' => 20,
        'initial_quantity' => 20,
        'status' => 'available',
    ]);

    $cashMethod = PaymentMethod::factory()->for($tenant)->cash()->create();
    $transferMethod = PaymentMethod::factory()->for($tenant)->transfer()->create();
    $bankAccount = BankAccount::factory()->for($tenant)->create(['is_default' => true]);

    return compact('bankAccount', 'cashMethod', 'lot', 'presentation', 'product', 'session', 'tenant', 'transferMethod', 'user');
}

test('legacy POS sales create a detailed sale payment without breaking the old payload', function () {
    ['presentation' => $presentation, 'product' => $product, 'session' => $session, 'user' => $user] = paymentDistributionSetup();

    $sale = app(ProcessSale::class)->execute([
        'payment_method' => 'cash',
        'amount_tendered' => 30000,
        'items' => [[
            'product_id' => $product->id,
            'product_presentation_id' => $presentation->id,
            'description' => $product->commercial_name,
            'quantity' => 2,
            'unit_price' => 10000,
            'tax_rate' => 0,
        ]],
    ], $user, $session->id);

    expect($sale->payments)->toHaveCount(1);
    expect($sale->payments->first())
        ->amount->toBe(20000)
        ->amount_tendered->toBe(30000)
        ->change_amount->toBe(10000);
});

test('mixed payments create payment rows and bank movements for banked money', function () {
    [
        'bankAccount' => $bankAccount,
        'cashMethod' => $cashMethod,
        'presentation' => $presentation,
        'product' => $product,
        'session' => $session,
        'transferMethod' => $transferMethod,
        'user' => $user,
    ] = paymentDistributionSetup();

    $sale = app(ProcessSale::class)->execute([
        'payment_method' => 'transfer',
        'payments' => [
            ['payment_method_id' => $cashMethod->id, 'amount' => 5000, 'amount_tendered' => 5000],
            ['payment_method_id' => $transferMethod->id, 'bank_account_id' => $bankAccount->id, 'amount' => 15000, 'reference' => 'TRX-123'],
        ],
        'items' => [[
            'product_id' => $product->id,
            'product_presentation_id' => $presentation->id,
            'description' => $product->commercial_name,
            'quantity' => 2,
            'unit_price' => 10000,
            'tax_rate' => 0,
        ]],
    ], $user, $session->id);

    expect(SalePayment::where('sale_id', $sale->id)->count())->toBe(2);
    expect(BankAccountMovement::where('reference', 'TRX-123')->first())
        ->not->toBeNull()
        ->amount->toBe(15000)
        ->status->toBe('pending');
});

test('cash session close stores payment counts by method', function () {
    [
        'cashMethod' => $cashMethod,
        'presentation' => $presentation,
        'product' => $product,
        'session' => $session,
        'user' => $user,
    ] = paymentDistributionSetup();

    app(ProcessSale::class)->execute([
        'payment_method' => 'cash',
        'payments' => [
            ['payment_method_id' => $cashMethod->id, 'amount' => 20000, 'amount_tendered' => 20000],
        ],
        'items' => [[
            'product_id' => $product->id,
            'product_presentation_id' => $presentation->id,
            'description' => $product->commercial_name,
            'quantity' => 2,
            'unit_price' => 10000,
            'tax_rate' => 0,
        ]],
    ], $user, $session->id);

    app(CloseCashSession::class)->execute($session, ['actual_closing_amount' => 70000], $user);

    $count = $session->paymentCounts()->where('payment_method_id', $cashMethod->id)->first();

    expect($count)
        ->not->toBeNull()
        ->expected_amount->toBe(20000)
        ->counted_amount->toBe(20000)
        ->difference->toBe(0);
});

test('transfer payments can store and download receipt attachments', function () {
    Storage::fake('local');

    [
        'bankAccount' => $bankAccount,
        'presentation' => $presentation,
        'product' => $product,
        'session' => $session,
        'tenant' => $tenant,
        'transferMethod' => $transferMethod,
        'user' => $user,
    ] = paymentDistributionSetup();

    app(RoleAndPermissionSeeder::class)->run();
    $user->assignRole('cashier');

    $sale = app(ProcessSale::class)->execute([
        'payment_method' => 'transfer',
        'payments' => [
            [
                'payment_method_id' => $transferMethod->id,
                'bank_account_id' => $bankAccount->id,
                'amount' => 20000,
                'reference' => 'TRX-ATTACH',
                'attachment' => UploadedFile::fake()->image('comprobante.jpg'),
            ],
        ],
        'items' => [[
            'product_id' => $product->id,
            'product_presentation_id' => $presentation->id,
            'description' => $product->commercial_name,
            'quantity' => 2,
            'unit_price' => 10000,
            'tax_rate' => 0,
        ]],
    ], $user, $session->id);

    $payment = $sale->payments()->firstOrFail();

    expect($payment->attachment_path)->not->toBeNull()
        ->and($payment->attachment_path)->toStartWith("tenants/{$tenant->id}/payment-receipts/");

    Storage::disk('local')->assertExists($payment->attachment_path);

    $this->actingAs($user)
        ->get(route('sales.payments.attachment', $payment))
        ->assertOk();
});
