<?php

use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function priceListImportCsv(array $rows): UploadedFile
{
    $output = fopen('php://temp', 'r+');
    fputcsv($output, ['codigo_interno', 'precio_especial']);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    rewind($output);
    $content = stream_get_contents($output);
    fclose($output);

    return UploadedFile::fake()->createWithContent('precios.csv', $content);
}

test('price list import upserts items from an uploaded csv', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('settings.manage');
    $user->givePermissionTo('settings.manage');

    $unit = ProductUnit::factory()->create(['code' => 'price-import-unit']);
    $product = Product::factory()
        ->for($tenant)
        ->for($unit, 'minimumUnit')
        ->create(['internal_code' => 'ACET-500', 'sale_price' => 3000]);

    $priceList = PriceList::create([
        'tenant_id' => $tenant->id,
        'name' => 'Promocion',
        'is_active' => true,
    ]);

    $file = priceListImportCsv([['ACET-500', '2500']]);

    $this->actingAs($user)
        ->post("/settings/price-lists/{$priceList->id}/import", ['file' => $file])
        ->assertRedirect(route('settings.price-lists.show', $priceList));

    $this->assertDatabaseHas('price_list_items', [
        'price_list_id' => $priceList->id,
        'product_id' => $product->id,
        'sale_price' => 2500,
    ]);
});
