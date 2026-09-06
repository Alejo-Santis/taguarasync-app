<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\ProcessCreditNote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreCreditNoteRequest;
use App\Models\DianDiscrepancyReason;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CreditNoteController extends Controller
{
    public function create(Sale $sale): Response
    {
        $sale->load(['items.product', 'customer', 'payments.paymentMethod']);

        return Inertia::render('Sales/CreditNote/Create', [
            'sale' => [
                'uuid' => $sale->uuid,
                'document_number' => $sale->document_number,
                'invoice_prefix' => $sale->invoice_prefix,
                'invoice_number' => $sale->invoice_number,
                'fe_cufe' => $sale->fe_cufe,
                'subtotal' => $sale->subtotal,
                'tax_total' => $sale->tax_total,
                'total' => $sale->total,
                'created_at' => $sale->created_at->format('d/m/Y H:i'),
                'customer_name' => $sale->customer?->full_name ?? 'Consumidor final',
                'items' => $sale->items->map(fn ($i) => [
                    'id' => $i->id,
                    'description' => $i->description,
                    'quantity' => $i->quantity,
                    'unit_price' => $i->unit_price,
                    'tax_rate' => $i->tax_rate,
                    'line_total' => $i->line_total,
                ])->values(),
                'payments' => $sale->payments->map(function ($p): array {
                    $method = $p->paymentMethod;

                    return [
                        'amount' => $p->amount,
                        'method_name' => $method?->name ?? 'Otro',
                        'affects_cash' => $method !== null && (bool) $method->affects_cash,
                        'has_bank_account' => $p->bank_account_id !== null,
                    ];
                })->values(),
            ],
            'discrepancy_reasons' => DianDiscrepancyReason::where('applies_to', 'credit_note')
                ->orderBy('code')
                ->get(['code', 'name']),
        ]);
    }

    public function store(Sale $sale, StoreCreditNoteRequest $request, ProcessCreditNote $action): RedirectResponse
    {
        $creditNote = $action->execute($sale, $request->validated(), $request->user());

        return to_route('sales.index')->with('success', "Nota crédito {$creditNote->number} creada. Inventario revertido.");
    }
}
