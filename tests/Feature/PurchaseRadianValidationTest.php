<?php

use App\Enums\PurchaseRadianStatus;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('purchase radian validation stores accepted api response', function () {
    Config::set('fe.api_url', 'https://nextpyme.test/api');
    Config::set('fe.api_token', 'secret-token');
    Config::set('fe.purchase_validation_path', '/radian/purchases/validate');
    Http::fake([
        'https://nextpyme.test/api/radian/purchases/validate' => Http::response([
            'valid' => true,
            'message' => 'Documento encontrado en RADIAN.',
        ]),
    ]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('purchases.create');
    $user->givePermissionTo('purchases.create');
    $supplier = Supplier::factory()->for($tenant)->create(['nit' => '900123456']);
    $receipt = PurchaseReceipt::factory()
        ->for($tenant)
        ->for($supplier)
        ->for($user)
        ->create(['document_number' => 'FE-PROV-1001']);

    $this->actingAs($user)
        ->post("/purchases/{$receipt->uuid}/validate-radian")
        ->assertRedirect();

    $receipt->refresh();

    expect($receipt->radian_status)->toBe(PurchaseRadianStatus::Validated)
        ->and($receipt->radian_checked_at)->not->toBeNull()
        ->and($receipt->radian_response['message'])->toBe('Documento encontrado en RADIAN.')
        ->and($receipt->radian_error_message)->toBeNull();

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer secret-token')
        && $request['document_number'] === 'FE-PROV-1001'
        && $request['supplier']['nit'] === '900123456');
});

test('purchase radian validation records configuration errors', function () {
    Config::set('fe.purchase_validation_path', null);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('purchases.create');
    $user->givePermissionTo('purchases.create');
    $receipt = PurchaseReceipt::factory()->for($tenant)->for($user)->create();

    $this->actingAs($user)
        ->post("/purchases/{$receipt->uuid}/validate-radian")
        ->assertRedirect();

    $receipt->refresh();

    expect($receipt->radian_status)->toBe(PurchaseRadianStatus::Error)
        ->and($receipt->radian_error_message)->toContain('FE_PURCHASE_VALIDATION_PATH');
});
