<?php

use App\Actions\Products\GetProductImportTemplate;
use App\Models\ActiveIngredient;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeCsv(array $rows, array $headers = []): UploadedFile
{
    if (empty($headers)) {
        $headers = GetProductImportTemplate::columns();
    }

    $output = fopen('php://temp', 'r+');
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    rewind($output);
    $content = stream_get_contents($output);
    fclose($output);

    return UploadedFile::fake()->createWithContent('products.csv', $content);
}

function defaultRow(array $overrides = []): array
{
    return array_values(array_replace([
        'nombre_comercial' => 'Acetaminofen 500mg',
        'nombre_generico' => 'Acetaminofen',
        'codigo_interno' => 'ACET-500',
        'codigo_barras' => '',
        'laboratorio' => 'Genfar',
        'categoria' => 'Analgesicos',
        'principio_activo' => '',
        'forma_farmaceutica' => 'Tableta',
        'concentracion' => '500mg',
        'unidad_minima_codigo' => 'und',
        'precio_compra' => '180',
        'precio_venta' => '300',
        'iva_porcentaje' => '0',
        'es_controlado' => 'no',
        'estado' => 'activo',
        'presentacion_nombre' => 'Unidad',
        'presentacion_unidad_codigo' => 'und',
        'presentacion_cantidad' => '1',
        'presentacion_precio' => '300',
    ], $overrides));
}

test('guests are redirected from import page to login', function () {
    $this->get('/products/import')->assertRedirect('/login');
});

test('guests cannot post to import endpoint', function () {
    $this->post('/products/import')->assertRedirect('/login');
});

test('authenticated users can open the import page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->get('/products/import')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Products/Import'));
});

test('authenticated users can download the import template', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();

    $response = $this->actingAs($user)->get('/products/import/template');

    $response->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload('plantilla-productos.csv');
});

test('valid csv imports products and redirects to index', function () {
    $tenant = Tenant::factory()->create();
    $unit = ProductUnit::factory()->create(['code' => 'und', 'is_active' => true]);
    Laboratory::factory()->for($tenant)->create(['name' => 'Genfar', 'is_active' => true]);
    ProductCategory::factory()->for($tenant)->create(['name' => 'Analgesicos', 'is_active' => true]);
    $user = User::factory()->for($tenant)->create();

    $file = makeCsv([defaultRow()]);

    $this->actingAs($user)
        ->post('/products/import', ['file' => $file])
        ->assertRedirect(route('products.index'));

    $this->assertDatabaseHas('products', [
        'commercial_name' => 'Acetaminofen 500mg',
        'internal_code' => 'ACET-500',
        'tenant_id' => $tenant->id,
    ]);

    expect(Product::where('commercial_name', 'Acetaminofen 500mg')->first()->presentations)->toHaveCount(1);
});

test('csv with wrong headers returns file error', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();

    $file = makeCsv([['col_a', 'col_b']], ['col_a', 'col_b']);

    $this->actingAs($user)
        ->post('/products/import', ['file' => $file])
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Import')
            ->where('importErrors.0.row', 0)
        );
});

test('csv with non-existent laboratory returns row error', function () {
    $tenant = Tenant::factory()->create();
    ProductUnit::factory()->create(['code' => 'und', 'is_active' => true]);
    ProductCategory::factory()->for($tenant)->create(['name' => 'Analgesicos', 'is_active' => true]);
    $user = User::factory()->for($tenant)->create();

    $file = makeCsv([defaultRow(['laboratorio' => 'Laboratorio Inexistente'])]);

    $this->actingAs($user)
        ->post('/products/import', ['file' => $file])
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Import')
            ->where('importErrors.0.row', 2)
        );

    $this->assertDatabaseMissing('products', ['commercial_name' => 'Acetaminofen 500mg']);
});

test('csv with duplicate internal code vs existing product returns error', function () {
    $tenant = Tenant::factory()->create();
    $unit = ProductUnit::factory()->create(['code' => 'und', 'is_active' => true]);
    $lab = Laboratory::factory()->for($tenant)->create(['name' => 'Genfar', 'is_active' => true]);
    ProductCategory::factory()->for($tenant)->create(['name' => 'Analgesicos', 'is_active' => true]);
    $user = User::factory()->for($tenant)->create();

    Product::factory()->for($tenant)->for($unit, 'minimumUnit')->for($lab)->create(['internal_code' => 'ACET-500']);

    $file = makeCsv([defaultRow()]);

    $this->actingAs($user)
        ->post('/products/import', ['file' => $file])
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Import')
            ->where('importErrors.0.row', 2)
        );
});

test('csv with duplicate internal code within batch returns error for second occurrence', function () {
    $tenant = Tenant::factory()->create();
    ProductUnit::factory()->create(['code' => 'und', 'is_active' => true]);
    Laboratory::factory()->for($tenant)->create(['name' => 'Genfar', 'is_active' => true]);
    ProductCategory::factory()->for($tenant)->create(['name' => 'Analgesicos', 'is_active' => true]);
    $user = User::factory()->for($tenant)->create();

    $file = makeCsv([
        defaultRow(),
        defaultRow(['nombre_comercial' => 'Otro producto']),
    ]);

    $response = $this->actingAs($user)
        ->post('/products/import', ['file' => $file]);

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Import')
            ->where('importErrors.0.row', 3)
        );

    $this->assertDatabaseMissing('products', ['commercial_name' => 'Acetaminofen 500mg']);
});

test('csv with invalid status returns row error', function () {
    $tenant = Tenant::factory()->create();
    ProductUnit::factory()->create(['code' => 'und', 'is_active' => true]);
    Laboratory::factory()->for($tenant)->create(['name' => 'Genfar', 'is_active' => true]);
    ProductCategory::factory()->for($tenant)->create(['name' => 'Analgesicos', 'is_active' => true]);
    $user = User::factory()->for($tenant)->create();

    $file = makeCsv([defaultRow(['estado' => 'vigente'])]);

    $this->actingAs($user)
        ->post('/products/import', ['file' => $file])
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Import')
            ->where('importErrors.0.row', 2)
        );
});

test('importing resolves active ingredient by dci name when it exists', function () {
    $tenant = Tenant::factory()->create();
    ProductUnit::factory()->create(['code' => 'und', 'is_active' => true]);
    Laboratory::factory()->for($tenant)->create(['name' => 'Genfar', 'is_active' => true]);
    ProductCategory::factory()->for($tenant)->create(['name' => 'Analgesicos', 'is_active' => true]);
    $ingredient = ActiveIngredient::factory()->create(['dci_name' => 'Acetaminofen']);
    $user = User::factory()->for($tenant)->create();

    $file = makeCsv([defaultRow(['principio_activo' => 'Acetaminofen'])]);

    $this->actingAs($user)->post('/products/import', ['file' => $file]);

    expect(Product::where('internal_code', 'ACET-500')->first()->active_ingredient_id)
        ->toBe($ingredient->id);
});
