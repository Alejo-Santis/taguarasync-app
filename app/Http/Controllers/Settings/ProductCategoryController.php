<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreProductCategoryRequest;
use App\Http\Requests\Settings\UpdateProductCategoryRequest;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $items = ProductCategory::withCount('products')
            ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'like', "%{$filters['q']}%"))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ProductCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'is_active' => $category->is_active,
                'products_count' => $category->products_count,
            ]);

        return Inertia::render('Settings/Categories/Index', [
            'items' => $items,
            'filters' => $filters,
            'stats' => [
                'total' => ProductCategory::count(),
                'active' => ProductCategory::where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(StoreProductCategoryRequest $request): RedirectResponse
    {
        ProductCategory::create($request->validated());

        return back()->with('success', 'Categoria creada correctamente.');
    }

    public function update(ProductCategory $category, UpdateProductCategoryRequest $request): RedirectResponse
    {
        $category->update($request->validated());

        return back()->with('success', 'Categoria actualizada correctamente.');
    }

    public function toggle(ProductCategory $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return back();
    }
}
