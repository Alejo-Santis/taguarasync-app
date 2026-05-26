<?php

namespace App\Http\Controllers\Purchases;

use App\Actions\Purchases\RecordSupplierPayment;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierPayableController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->withSum(['purchaseReceipts as total_purchased' => fn ($q) => $q->where('status', '!=', 'voided')], 'total')
            ->withSum('payments as total_paid', 'amount')
            ->withSum(['returns as total_returned' => fn ($q) => $q->where('status', 'confirmed')], 'total')
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'nit', 'contact_name', 'contact_email', 'contact_phone']);

        $suppliers = $suppliers->map(fn (Supplier $s) => [
            'id' => $s->id,
            'uuid' => $s->uuid,
            'name' => $s->name,
            'nit' => $s->nit,
            'contact_name' => $s->contact_name,
            'contact_email' => $s->contact_email,
            'total_purchased' => (int) ($s->total_purchased ?? 0),
            'total_paid' => (int) ($s->total_paid ?? 0),
            'total_returned' => (int) ($s->total_returned ?? 0),
            'balance' => (int) ($s->total_purchased ?? 0) - (int) ($s->total_paid ?? 0) - (int) ($s->total_returned ?? 0),
        ]);

        $totals = [
            'total_purchased' => $suppliers->sum('total_purchased'),
            'total_paid' => $suppliers->sum('total_paid'),
            'total_returned' => $suppliers->sum('total_returned'),
            'balance' => $suppliers->sum('balance'),
        ];

        return Inertia::render('Purchases/Payables/Index', [
            'suppliers' => $suppliers->values()->all(),
            'totals' => $totals,
        ]);
    }

    public function show(Supplier $supplier): Response
    {
        $receipts = PurchaseReceipt::query()
            ->where('supplier_id', $supplier->id)
            ->where('status', '!=', 'voided')
            ->orderByDesc('document_date')
            ->get(['id', 'uuid', 'document_number', 'document_date', 'total', 'status'])
            ->map(fn (PurchaseReceipt $r) => [
                'uuid' => $r->uuid,
                'document_number' => $r->document_number,
                'date' => $r->document_date->toDateString(),
                'type' => 'receipt',
                'label' => 'Compra',
                'amount' => $r->total,
                'reference' => $r->document_number,
                'status' => $r->status,
            ]);

        $payments = SupplierPayment::query()
            ->where('supplier_id', $supplier->id)
            ->with('bankAccount:id,bank_name,account_name')
            ->orderByDesc('payment_date')
            ->get()
            ->map(fn (SupplierPayment $p) => [
                'uuid' => $p->uuid,
                'document_number' => null,
                'date' => $p->payment_date->toDateString(),
                'type' => 'payment',
                'label' => 'Pago',
                'amount' => -$p->amount,
                'reference' => $p->reference,
                'bank' => $p->bankAccount ? "{$p->bankAccount->bank_name} · {$p->bankAccount->account_name}" : null,
                'notes' => $p->notes,
            ]);

        $returns = SupplierReturn::query()
            ->where('supplier_id', $supplier->id)
            ->where('status', 'confirmed')
            ->orderByDesc('return_date')
            ->get(['id', 'uuid', 'document_number', 'return_date', 'total'])
            ->map(fn (SupplierReturn $r) => [
                'uuid' => $r->uuid,
                'document_number' => $r->document_number,
                'date' => $r->return_date->toDateString(),
                'type' => 'return',
                'label' => 'Devolución',
                'amount' => -$r->total,
                'reference' => $r->document_number,
            ]);

        $movements = $receipts
            ->concat($payments)
            ->concat($returns)
            ->sortByDesc('date')
            ->values()
            ->all();

        $totalPurchased = $receipts->sum('amount');
        $totalPaid = -$payments->sum('amount');
        $totalReturned = -$returns->sum('amount');
        $balance = $totalPurchased - $totalPaid - $totalReturned;

        $bankAccounts = BankAccount::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('bank_name')
            ->get(['id', 'bank_name', 'account_name', 'is_default']);

        return Inertia::render('Purchases/Payables/Show', [
            'supplier' => [
                'id' => $supplier->id,
                'uuid' => $supplier->uuid,
                'name' => $supplier->name,
                'nit' => $supplier->nit,
                'contact_name' => $supplier->contact_name,
                'contact_email' => $supplier->contact_email,
                'contact_phone' => $supplier->contact_phone,
            ],
            'movements' => $movements,
            'summary' => [
                'total_purchased' => (int) $totalPurchased,
                'total_paid' => (int) $totalPaid,
                'total_returned' => (int) $totalReturned,
                'balance' => (int) $balance,
            ],
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function storePayment(Supplier $supplier, Request $request, RecordSupplierPayment $recordSupplierPayment): RedirectResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $recordSupplierPayment->execute($supplier, $validated, $request->user());

        return back()->with('success', 'Pago de '.number_format($validated['amount'])." registrado para {$supplier->name}.");
    }
}
