<?php

namespace App\Http\Controllers\Purchases;

use App\Actions\Purchases\GetPurchaseReceiptFormOptions;
use App\Actions\Purchases\ListPurchaseReceipts;
use App\Actions\Purchases\ReceivePurchaseReceipt;
use App\Actions\Purchases\ValidatePurchaseReceiptRadian;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseReceiptRequest;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseReceiptController extends Controller
{
    public function index(Request $request, ListPurchaseReceipts $listPurchaseReceipts): Response
    {
        return Inertia::render('Purchases/Index', $listPurchaseReceipts->execute($request));
    }

    public function create(GetPurchaseReceiptFormOptions $getPurchaseReceiptFormOptions): Response
    {
        return Inertia::render('Purchases/Create', [
            'options' => $getPurchaseReceiptFormOptions->execute(),
        ]);
    }

    public function store(
        StorePurchaseReceiptRequest $request,
        ReceivePurchaseReceipt $receivePurchaseReceipt
    ): RedirectResponse {
        $receipt = $receivePurchaseReceipt->execute([
            ...$request->validated(),
            'source_file_path' => $request->hasFile('source_file')
                ? $request->file('source_file')->store("tenants/{$request->user()->tenant_id}/purchase-receipts")
                : null,
            'tenant_id' => $request->user()->tenant_id,
        ], $request->user());

        return to_route('purchases.index')
            ->with('success', "Compra {$receipt->document_number} recibida correctamente.");
    }

    public function show(PurchaseReceipt $purchase): Response
    {
        $purchase->load(['supplier', 'user', 'purchaseOrder', 'items.product']);

        return Inertia::render('Purchases/Show', [
            'receipt' => [
                'id' => $purchase->id,
                'uuid' => $purchase->uuid,
                'document_number' => $purchase->document_number,
                'document_date' => $purchase->document_date->toDateString(),
                'received_at' => $purchase->received_at?->format('Y-m-d H:i'),
                'status' => $purchase->status,
                'subtotal' => $purchase->subtotal,
                'tax_total' => $purchase->tax_total,
                'total' => $purchase->total,
                'notes' => $purchase->notes,
                'radian_status' => $purchase->radian_status,
                'radian_checked_at' => $purchase->radian_checked_at?->toDateTimeString(),
                'has_attachment' => (bool) $purchase->source_file_path,
                'supplier' => [
                    'name' => $purchase->supplier->name,
                    'nit' => $purchase->supplier->nit,
                    'uuid' => $purchase->supplier->uuid,
                ],
                'user' => $purchase->user?->name,
                'purchase_order_number' => $purchase->purchaseOrder?->order_number,
                'purchase_order_uuid' => $purchase->purchaseOrder?->uuid,
                'items' => $purchase->items->map(fn (PurchaseReceiptItem $item) => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'lot_number' => $item->lot_number,
                    'expires_on' => $item->expires_on?->toDateString(),
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'tax_rate' => $item->tax_rate,
                    'line_subtotal' => $item->line_subtotal,
                    'line_tax' => $item->line_tax,
                    'line_total' => $item->line_total,
                    'product_name' => $item->product?->commercial_name,
                ])->values()->all(),
            ],
        ]);
    }

    public function validateRadian(PurchaseReceipt $purchase, ValidatePurchaseReceiptRadian $validatePurchaseReceiptRadian): RedirectResponse
    {
        $validatePurchaseReceiptRadian->execute($purchase);

        return back()->with('success', "Validación RADIAN ejecutada para {$purchase->document_number}.");
    }

    public function attachment(PurchaseReceipt $purchase): StreamedResponse
    {
        abort_unless($purchase->source_file_path && Storage::exists($purchase->source_file_path), 404);

        return Storage::download($purchase->source_file_path);
    }
}
