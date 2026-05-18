<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreActiveIngredientRequest;
use App\Http\Requests\Settings\UpdateActiveIngredientRequest;
use App\Models\ActiveIngredient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActiveIngredientController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
        ];

        $items = ActiveIngredient::query()
            ->when($filters['q'] !== '', fn ($q) => $q->where('dci_name', 'like', "%{$filters['q']}%")->orWhere('pharmacological_group', 'like', "%{$filters['q']}%"))
            ->orderBy('dci_name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ActiveIngredient $ingredient) => [
                'id' => $ingredient->id,
                'dci_name' => $ingredient->dci_name,
                'pharmacological_group' => $ingredient->pharmacological_group,
                'atc_classification' => $ingredient->atc_classification,
            ]);

        return Inertia::render('Settings/ActiveIngredients/Index', [
            'items' => $items,
            'filters' => $filters,
            'stats' => [
                'total' => ActiveIngredient::count(),
            ],
        ]);
    }

    public function store(StoreActiveIngredientRequest $request): RedirectResponse
    {
        ActiveIngredient::create($request->validated());

        return back()->with('success', 'Principio activo creado correctamente.');
    }

    public function update(ActiveIngredient $ingredient, UpdateActiveIngredientRequest $request): RedirectResponse
    {
        $ingredient->update($request->validated());

        return back()->with('success', 'Principio activo actualizado correctamente.');
    }

    public function destroy(ActiveIngredient $ingredient): RedirectResponse
    {
        $ingredient->delete();

        return back()->with('success', 'Principio activo eliminado correctamente.');
    }
}
