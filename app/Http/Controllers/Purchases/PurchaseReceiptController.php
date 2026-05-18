<?php

namespace App\Http\Controllers\Purchases;

use App\Actions\Purchases\GetPurchaseReceiptFormOptions;
use App\Actions\Purchases\ListPurchaseReceipts;
use App\Actions\Purchases\ReceivePurchaseReceipt;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseReceiptRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
            'tenant_id' => $request->user()->tenant_id,
        ], $request->user());

        return to_route('purchases.index')
            ->with('success', "Compra {$receipt->document_number} recibida correctamente.");
    }
}
