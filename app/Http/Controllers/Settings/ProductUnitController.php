<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreProductUnitRequest;
use App\Http\Requests\Settings\UpdateProductUnitRequest;
use App\Models\ProductUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductUnitController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $items = ProductUnit::query()
            ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'like', "%{$filters['q']}%")->orWhere('code', 'like', "%{$filters['q']}%"))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ProductUnit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code,
                'kind' => $unit->kind,
                'allows_decimals' => $unit->allows_decimals,
                'is_active' => $unit->is_active,
            ]);

        return Inertia::render('Settings/Units/Index', [
            'items' => $items,
            'filters' => $filters,
            'stats' => [
                'total' => ProductUnit::count(),
                'active' => ProductUnit::where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(StoreProductUnitRequest $request): RedirectResponse
    {
        ProductUnit::create($request->validated());

        return back()->with('success', 'Unidad creada correctamente.');
    }

    public function update(ProductUnit $unit, UpdateProductUnitRequest $request): RedirectResponse
    {
        $unit->update($request->validated());

        return back()->with('success', 'Unidad actualizada correctamente.');
    }

    public function toggle(ProductUnit $unit): RedirectResponse
    {
        $unit->update(['is_active' => ! $unit->is_active]);

        return back();
    }
}
