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

$fakeCufe = str_repeat('a', 96);

test('purchase radian validation sends events 030 and 032 when cufe is set', function () use ($fakeCufe) {
    Config::set('fe.api_url', 'https://nextpyme.test/api');
    Config::set('fe.ubl_prefix', '/ubl2.1');
    Config::set('fe.api_token', 'secret-token');

    // Mock: checkRadianEvents → no events yet (status 67)
    Http::fake([
        "https://nextpyme.test/api/ubl2.1/status/events-document/{$fakeCufe}" => Http::response([
            'success' => false,
            'message' => 'no tiene eventos asociados',
        ]),
        // sendRadianEvent event_id=1 (030)
        'https://nextpyme.test/api/ubl2.1/send-event-data' => Http::sequence()
            ->push(['ResponseDian' => ['Envelope' => ['Body' => ['SendEventUpdateStatusResponse' => ['SendEventUpdateStatusResult' => ['IsValid' => 'true', 'XmlDocumentKey' => str_repeat('b', 96)]]]]]])
            ->push(['ResponseDian' => ['Envelope' => ['Body' => ['SendEventUpdateStatusResponse' => ['SendEventUpdateStatusResult' => ['IsValid' => 'true', 'XmlDocumentKey' => str_repeat('c', 96)]]]]]]),
    ]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('purchases.create');
    $user->givePermissionTo('purchases.create');
    $supplier = Supplier::factory()->for($tenant)->create();
    $receipt = PurchaseReceipt::factory()
        ->for($tenant)
        ->for($supplier)
        ->for($user)
        ->create(['supplier_cufe' => $fakeCufe]);

    $this->actingAs($user)
        ->post("/purchases/{$receipt->uuid}/validate-radian")
        ->assertRedirect();

    $receipt->refresh();

    expect($receipt->radian_status)->toBe(PurchaseRadianStatus::Validated)
        ->and($receipt->radian_checked_at)->not->toBeNull()
        ->and($receipt->radian_error_message)->toBeNull();
});

test('purchase radian validation marks as already validated when 030 and 032 already exist', function () use ($fakeCufe) {
    Config::set('fe.api_url', 'https://nextpyme.test/api');
    Config::set('fe.ubl_prefix', '/ubl2.1');
    Config::set('fe.api_token', 'secret-token');

    Http::fake([
        "https://nextpyme.test/api/ubl2.1/status/events-document/{$fakeCufe}" => Http::response([
            'success' => true,
            'events' => [
                ['dian_code' => '030', 'cude' => str_repeat('b', 96), 'date' => '2026-01-01'],
                ['dian_code' => '032', 'cude' => str_repeat('c', 96), 'date' => '2026-01-01'],
            ],
        ]),
    ]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('purchases.create');
    $user->givePermissionTo('purchases.create');
    $supplier = Supplier::factory()->for($tenant)->create();
    $receipt = PurchaseReceipt::factory()
        ->for($tenant)
        ->for($supplier)
        ->for($user)
        ->create(['supplier_cufe' => $fakeCufe]);

    $this->actingAs($user)
        ->post("/purchases/{$receipt->uuid}/validate-radian")
        ->assertRedirect();

    $receipt->refresh();

    expect($receipt->radian_status)->toBe(PurchaseRadianStatus::Validated)
        ->and($receipt->radian_error_message)->toBeNull();

    // No debe haber llamado al endpoint de envío de eventos
    Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), 'send-event-data'));
});

test('purchase radian validation marks pending with message when supplier cufe is missing', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('purchases.create');
    $user->givePermissionTo('purchases.create');
    $supplier = Supplier::factory()->for($tenant)->create();
    $receipt = PurchaseReceipt::factory()
        ->for($tenant)
        ->for($supplier)
        ->for($user)
        ->create(['supplier_cufe' => null]);

    $this->actingAs($user)
        ->post("/purchases/{$receipt->uuid}/validate-radian")
        ->assertRedirect();

    $receipt->refresh();

    expect($receipt->radian_status)->toBe(PurchaseRadianStatus::Pending)
        ->and($receipt->radian_error_message)->toContain('CUFE');
});
