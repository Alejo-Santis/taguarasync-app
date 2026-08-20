<?php

use App\Models\CashRegister;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('certificate endpoint returns 503 when the qz key has not been generated', function () {
    $path = storage_path('app/qz-public.pem');
    File::delete($path);

    $this->get('/print/certificate')->assertStatus(503);
});

test('sign endpoint returns 503 when the qz private key has not been generated', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    File::delete(storage_path('app/qz-private.pem'));

    $this->actingAs($user)
        ->post('/print/sign', [], ['Content-Type' => 'text/plain'])
        ->assertStatus(503);
});

test('guests cannot see printer settings', function () {
    $this->get('/settings/printer')->assertRedirect('/login');
});

test('owners can list their cash registers with printer configuration', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    CashRegister::factory()->for($tenant)->create(['name' => 'Caja Principal']);

    $this->actingAs($user)
        ->get('/settings/printer')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Printer/Index')
            ->has('registers', 1)
            ->where('registers.0.name', 'Caja Principal')
        );
});

test('owners can save printer configuration for their own register', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $register = CashRegister::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->put("/settings/printer/{$register->id}", [
            'printer_name' => 'EPSON TM-T20',
            'paper_width' => '80mm',
            'copies' => 2,
            'auto_print' => true,
        ])
        ->assertRedirect();

    $register->refresh();

    expect($register->printer_name)->toBe('EPSON TM-T20')
        ->and($register->paper_width)->toBe('80mm')
        ->and($register->copies)->toBe(2)
        ->and($register->auto_print)->toBeTrue();
});

test('a register from another tenant cannot have its printer configured', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    $register = CashRegister::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->put("/settings/printer/{$register->id}", [
            'paper_width' => '58mm',
            'copies' => 1,
            'auto_print' => false,
        ])
        ->assertForbidden();
});
