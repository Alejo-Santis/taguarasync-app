<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\AdjustInventory;
use App\Actions\Inventory\ListInventoryLots;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustInventoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request, ListInventoryLots $listInventoryLots): Response
    {
        return Inertia::render('Inventory/Index', $listInventoryLots->execute($request));
    }

    public function adjust(AdjustInventoryRequest $request, AdjustInventory $adjustInventory): RedirectResponse
    {
        try {
            $adjustInventory->execute($request->validated(), $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['adjust' => $e->getMessage()]);
        }

        $type = $request->input('type') === 'in' ? 'entrada' : 'salida';

        return back()->with('success', "Ajuste de {$type} registrado correctamente.");
    }
}
