<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreSupplierRequest;
use App\Http\Requests\Settings\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $items = Supplier::withCount('purchaseReceipts')
            ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'like', "%{$filters['q']}%")->orWhere('nit', 'like', "%{$filters['q']}%"))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Supplier $supplier) => [
                'uuid' => $supplier->uuid,
                'name' => $supplier->name,
                'nit' => $supplier->nit,
                'contact_name' => $supplier->contact_name,
                'contact_email' => $supplier->contact_email,
                'contact_phone' => $supplier->contact_phone,
                'is_active' => $supplier->is_active,
                'purchase_receipts_count' => $supplier->purchase_receipts_count,
            ]);

        return Inertia::render('Settings/Suppliers/Index', [
            'items' => $items,
            'filters' => $filters,
            'stats' => [
                'total' => Supplier::count(),
                'active' => Supplier::where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        return back()->with('success', 'Proveedor creado correctamente.');
    }

    public function update(Supplier $supplier, UpdateSupplierRequest $request): RedirectResponse
    {
        $supplier->update($request->validated());

        return back()->with('success', 'Proveedor actualizado correctamente.');
    }

    public function toggle(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);

        return back();
    }
}
