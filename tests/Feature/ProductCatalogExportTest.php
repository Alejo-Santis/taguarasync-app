<?php

use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('product catalog export streams a csv with the expected columns and rows', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('products.view');
    $user->givePermissionTo('products.view');

    $unit = ProductUnit::factory()->create(['code' => 'export-cat-unit']);
    $laboratory = Laboratory::factory()->for($tenant)->create(['name' => 'Genfar']);
    Product::factory()
        ->for($tenant)
        ->for($laboratory)
        ->for($unit, 'minimumUnit')
        ->create([
            'commercial_name' => 'Acetaminofen 500mg',
            'internal_code' => 'ACET-500-EXP',
            'purchase_price' => 180,
            'sale_price' => 300,
        ]);

    $content = $this->actingAs($user)
        ->get('/products/export')
        ->assertSuccessful()
        ->streamedContent();

    expect($content)->toContain('codigo_interno')
        ->toContain('nombre_comercial')
        ->toContain('Acetaminofen 500mg')
        ->toContain('ACET-500-EXP')
        ->toContain('Genfar');
});
