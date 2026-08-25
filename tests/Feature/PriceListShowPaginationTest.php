<?php

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('price list items are paginated instead of loading every row at once', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('settings.manage');
    $user->givePermissionTo('settings.manage');

    $unit = ProductUnit::factory()->create(['code' => 'plist-page-unit']);
    $category = ProductCategory::factory()->for($tenant)->create();
    $priceList = PriceList::create(['tenant_id' => $tenant->id, 'name' => 'Promocion', 'is_active' => true]);

    for ($i = 1; $i <= 30; $i++) {
        $product = Product::factory()
            ->for($tenant)
            ->for($unit, 'minimumUnit')
            ->for($category, 'category')
            ->create(['internal_code' => "PL-{$i}", 'sale_price' => 1000, 'active_ingredient_id' => null]);

        PriceListItem::create([
            'tenant_id' => $tenant->id,
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            'sale_price' => 900,
        ]);
    }

    $this->actingAs($user)
        ->get("/settings/price-lists/{$priceList->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/PriceLists/Show')
            ->has('items.data', 25)
            ->where('items.total', 30)
            ->where('items.last_page', 2)
        );
});
