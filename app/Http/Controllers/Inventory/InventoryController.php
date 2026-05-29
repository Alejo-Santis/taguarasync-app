<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\AdjustInventory;
use App\Actions\Inventory\GenerateOpeningTemplate;
use App\Actions\Inventory\ListInventoryLots;
use App\Actions\Inventory\LoadOpeningStock;
use App\Actions\Inventory\ParseOpeningImport;
use App\Actions\Purchases\GetPurchaseReceiptFormOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustInventoryRequest;
use App\Http\Requests\Inventory\StoreOpeningStockRequest;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function index(Request $request, ListInventoryLots $listInventoryLots): Response
    {
        $data = $listInventoryLots->execute($request);
        $data['branches'] = Branch::where('is_active', true)
            ->orderByDesc('is_main')->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])
            ->all();

        return Inertia::render('Inventory/Index', $data);
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

    public function openingForm(GetPurchaseReceiptFormOptions $getFormOptions): Response
    {
        return Inertia::render('Inventory/Opening', [
            'options' => array_merge($getFormOptions->execute(), [
                'branches' => Branch::where('is_active', true)
                    ->orderByDesc('is_main')->orderBy('name')
                    ->get(['id', 'name', 'is_main'])
                    ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'is_main' => $b->is_main])
                    ->all(),
            ]),
        ]);
    }

    public function openingTemplate(GenerateOpeningTemplate $generateTemplate): StreamedResponse
    {
        $csv = $generateTemplate->execute();
        $filename = 'plantilla-carga-inicial-'.date('Y-m-d').'.csv';

        return response()->streamDownload(
            fn () => print ($csv),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function parseOpeningImport(Request $request, ParseOpeningImport $parseImport): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $result = $parseImport->execute($request->file('file'));

        return response()->json($result);
    }

    public function storeOpening(StoreOpeningStockRequest $request, LoadOpeningStock $loadOpeningStock): RedirectResponse
    {
        $count = count($request->validated()['items']);

        $loadOpeningStock->execute([
            'tenant_id' => $request->user()->tenant_id,
            'branch_id' => $request->validated()['branch_id'],
            'items' => $request->validated()['items'],
        ], $request->user());

        return to_route('inventory.index')
            ->with('success', "Carga inicial registrada: {$count} ".($count === 1 ? 'línea' : 'líneas').' de inventario.');
    }
}
