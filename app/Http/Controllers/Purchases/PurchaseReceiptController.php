<?php

namespace App\Http\Controllers\Purchases;

use App\Actions\Purchases\GetPurchaseReceiptFormOptions;
use App\Actions\Purchases\ListPurchaseReceipts;
use App\Actions\Purchases\ReceivePurchaseReceipt;
use App\Actions\Purchases\ValidatePurchaseReceiptRadian;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseReceiptRequest;
use App\Models\PurchaseReceipt;
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
