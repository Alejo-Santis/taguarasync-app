<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreLaboratoryRequest;
use App\Http\Requests\Settings\UpdateLaboratoryRequest;
use App\Models\Laboratory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LaboratoryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $items = Laboratory::withCount('products')
            ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'like', "%{$filters['q']}%"))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Laboratory $lab) => [
                'id' => $lab->id,
                'name' => $lab->name,
                'nit' => $lab->nit,
                'country' => $lab->country,
                'contact_name' => $lab->contact_name,
                'contact_email' => $lab->contact_email,
                'contact_phone' => $lab->contact_phone,
                'is_active' => $lab->is_active,
                'products_count' => $lab->products_count,
            ]);

        return Inertia::render('Settings/Laboratories/Index', [
            'items' => $items,
            'filters' => $filters,
            'stats' => [
                'total' => Laboratory::count(),
                'active' => Laboratory::where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(StoreLaboratoryRequest $request): RedirectResponse
    {
        Laboratory::create($request->validated());

        return back()->with('success', 'Laboratorio creado correctamente.');
    }

    public function update(Laboratory $laboratory, UpdateLaboratoryRequest $request): RedirectResponse
    {
        $laboratory->update($request->validated());

        return back()->with('success', 'Laboratorio actualizado correctamente.');
    }

    public function toggle(Laboratory $laboratory): RedirectResponse
    {
        $laboratory->update(['is_active' => ! $laboratory->is_active]);

        return back();
    }
}
