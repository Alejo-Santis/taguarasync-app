<?php

use App\Models\DianIdentificationType;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function identificationTypeForSanitizationTest(): DianIdentificationType
{
    return DianIdentificationType::firstOrCreate(
        ['code' => '13'],
        ['name' => 'Cédula de ciudadanía', 'is_active' => true]
    );
}

test('creating a customer strips dots and dashes from the identification number', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    identificationTypeForSanitizationTest();

    $this->actingAs($user)
        ->post('/customers', [
            'identification_type_code' => '13',
            'identification_number' => '79.123.456',
            'first_name' => 'Carlos',
            'last_name' => 'Ramirez',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $tenant->id,
        'identification_number' => '79123456',
    ]);
});

test('quick customer creation from the pos strips dots and dashes from the identification number', function () {
    $tenant = Tenant::factory()->create();
    $user = createOwnerUser($tenant);
    identificationTypeForSanitizationTest();

    $this->actingAs($user)
        ->postJson('/pos/customers', [
            'identification_type_code' => '13',
            'identification_number' => '79.123.456',
            'first_name' => 'Carlos',
            'last_name' => 'Ramirez',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $tenant->id,
        'identification_number' => '79123456',
    ]);
});
