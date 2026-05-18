<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\GetProductFormData;
use App\Actions\Products\GetProductFormOptions;
use App\Actions\Products\GetProductImportTemplate;
use App\Actions\Products\ImportProducts;
use App\Actions\Products\ListProducts;
use App\Actions\Products\UpdateProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ImportProductsRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function index(Request $request, ListProducts $listProducts): Response
    {
        return Inertia::render('Products/Index', $listProducts->execute($request));
    }

    public function create(GetProductFormOptions $getProductFormOptions): Response
    {
        return Inertia::render('Products/Create', [
            'options' => $getProductFormOptions->execute(),
        ]);
    }

    public function store(StoreProductRequest $request, CreateProduct $createProduct): RedirectResponse
    {
        $product = $createProduct->execute($request->validated());

        return to_route('products.index')
            ->with('success', "Producto {$product->commercial_name} creado correctamente.");
    }

    public function edit(
        Product $product,
        GetProductFormData $getProductFormData,
        GetProductFormOptions $getProductFormOptions
    ): Response {
        return Inertia::render('Products/Edit', [
            'product' => $getProductFormData->execute($product),
            'options' => $getProductFormOptions->execute(),
        ]);
    }

    public function update(
        Product $product,
        UpdateProductRequest $request,
        UpdateProduct $updateProduct
    ): RedirectResponse {
        $product = $updateProduct->execute($product, $request->validated());

        return to_route('products.index')
            ->with('success', "Producto {$product->commercial_name} actualizado correctamente.");
    }

    public function importForm(): Response
    {
        return Inertia::render('Products/Import');
    }

    public function import(ImportProductsRequest $request, ImportProducts $importProducts): Response|RedirectResponse
    {
        $result = $importProducts->execute($request->file('file')->getPathname());

        if (! empty($result['errors'])) {
            return Inertia::render('Products/Import', [
                'importErrors' => $result['errors'],
            ]);
        }

        $count = $result['created'];
        $label = $count === 1 ? 'producto importado' : 'productos importados';

        return to_route('products.index')
            ->with('success', "{$count} {$label} correctamente.");
    }

    public function downloadTemplate(GetProductImportTemplate $getTemplate): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($getTemplate): void {
                echo $getTemplate->execute();
            },
            'plantilla-productos.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }
}
