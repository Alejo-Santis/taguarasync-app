<?php

namespace App\Http\Controllers\Purchases;

use App\Actions\Purchases\GetSupplierReturnFormOptions;
use App\Actions\Purchases\ProcessSupplierReturn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StoreSupplierReturnRequest;
use App\Models\SupplierReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $returns = SupplierReturn::query()
            ->with(['supplier', 'user'])
            ->when($request->q, fn ($query, $q) => $query->where('document_number', 'like', "%{$q}%"))
            ->when($request->supplier_id, fn ($query, $id) => $query->where('supplier_id', $id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Purchases/Returns/Index', [
            'returns' => $returns->through(fn (SupplierReturn $r) => [
                'id' => $r->id,
                'uuid' => $r->uuid,
                'document_number' => $r->document_number,
                'return_date' => $r->return_date->toDateString(),
                'supplier' => ['id' => $r->supplier->id, 'name' => $r->supplier->name],
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'total' => $r->total,
                'reason' => $r->reason,
            ]),
            'filters' => $request->only(['q', 'supplier_id']),
        ]);
    }

    public function create(GetSupplierReturnFormOptions $getOptions): Response
    {
        return Inertia::render('Purchases/Returns/Create', [
            'options' => $getOptions->execute(),
        ]);
    }

    public function store(
        StoreSupplierReturnRequest $request,
        ProcessSupplierReturn $processSupplierReturn
    ): RedirectResponse {
        $return = $processSupplierReturn->execute([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ], $request->user());

        return to_route('purchases.returns.index')
            ->with('success', "Devolución {$return->document_number} registrada correctamente.");
    }

    public function show(SupplierReturn $return): Response
    {
        $return->load(['supplier', 'items.product', 'items.lot', 'user', 'purchaseReceipt']);

        return Inertia::render('Purchases/Returns/Show', [
            'return' => [
                'id' => $return->id,
                'uuid' => $return->uuid,
                'document_number' => $return->document_number,
                'return_date' => $return->return_date->toDateString(),
                'reason' => $return->reason,
                'notes' => $return->notes,
                'status' => $return->status->value,
                'status_label' => $return->status->label(),
                'subtotal' => $return->subtotal,
                'tax_total' => $return->tax_total,
                'total' => $return->total,
                'supplier' => ['id' => $return->supplier->id, 'name' => $return->supplier->name, 'nit' => $return->supplier->nit],
                'purchase_receipt' => $return->purchaseReceipt
                    ? ['document_number' => $return->purchaseReceipt->document_number]
                    : null,
                'user' => $return->user ? ['name' => $return->user->name] : null,
                'created_at' => $return->created_at->toDateTimeString(),
                'items' => $return->items->map(fn ($item) => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'lot_number' => $item->lot_number,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'tax_rate' => $item->tax_rate,
                    'line_subtotal' => $item->line_subtotal,
                    'line_tax' => $item->line_tax,
                    'line_total' => $item->line_total,
                ])->all(),
            ],
        ]);
    }
}
