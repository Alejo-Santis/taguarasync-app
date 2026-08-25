<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerCollection;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerReceivableController extends Controller
{
    public function index(Request $request): Response
    {
        $customers = Customer::query()
            ->where('is_active', true)
            ->withSum(
                ['sales as total_invoiced' => fn ($q) => $q->where('status', '!=', 'voided')->where('payment_form', '2')],
                'total'
            )
            ->withSum('collections as total_collected', 'amount')
            ->orderBy('business_name')
            ->orderBy('last_name')
            ->get(['id', 'uuid', 'first_name', 'last_name', 'business_name', 'identification_number', 'identification_type_code'])
            ->filter(fn ($c) => ($c->total_invoiced ?? 0) > 0 || ($c->total_collected ?? 0) > 0)
            ->map(fn (Customer $c) => [
                'uuid' => $c->uuid,
                'full_name' => $c->full_name,
                'identification' => $c->identification_type_code.' '.$c->identification_number,
                'total_invoiced' => (int) ($c->total_invoiced ?? 0),
                'total_collected' => (int) ($c->total_collected ?? 0),
                'balance' => (int) ($c->total_invoiced ?? 0) - (int) ($c->total_collected ?? 0),
            ])
            ->values();

        $totals = [
            'total_invoiced' => $customers->sum('total_invoiced'),
            'total_collected' => $customers->sum('total_collected'),
            'balance' => $customers->sum('balance'),
        ];

        $perPage = 25;
        $page = $request->integer('page', 1);
        $paginatedCustomers = new LengthAwarePaginator(
            $customers->forPage($page, $perPage)->values(),
            $customers->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Sales/Receivables/Index', [
            'customers' => $paginatedCustomers,
            'totals' => $totals,
        ]);
    }

    public function show(Customer $customer, Request $request): Response
    {
        $sales = Sale::query()
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'voided')
            ->where('payment_form', '2')
            ->orderByDesc('created_at')
            ->get(['id', 'uuid', 'document_number', 'created_at', 'total', 'payment_due_date'])
            ->map(fn (Sale $s) => [
                'uuid' => $s->uuid,
                'date' => $s->created_at->toDateString(),
                'type' => 'invoice',
                'label' => 'Factura',
                'document_number' => $s->document_number,
                'amount' => $s->total,
                'due_date' => $s->payment_due_date?->toDateString(),
                'is_overdue' => $s->payment_due_date && $s->payment_due_date->isPast(),
            ]);

        $collections = CustomerCollection::query()
            ->where('customer_id', $customer->id)
            ->with('bankAccount:id,bank_name,account_name')
            ->orderByDesc('collection_date')
            ->get()
            ->map(fn (CustomerCollection $c) => [
                'uuid' => $c->uuid,
                'date' => $c->collection_date->toDateString(),
                'type' => 'payment',
                'label' => 'Cobro',
                'document_number' => null,
                'amount' => -$c->amount,
                'reference' => $c->reference,
                'bank' => $c->bankAccount ? "{$c->bankAccount->bank_name} · {$c->bankAccount->account_name}" : null,
                'notes' => $c->notes,
            ]);

        $movements = $sales->concat($collections)->sortByDesc('date')->values();

        $totalInvoiced = $sales->sum('amount');
        $totalCollected = -$collections->sum('amount');
        $balance = $totalInvoiced - $totalCollected;

        $perPage = 20;
        $page = $request->integer('page', 1);
        $paginatedMovements = new LengthAwarePaginator(
            $movements->forPage($page, $perPage)->values(),
            $movements->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $bankAccounts = BankAccount::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('bank_name')
            ->get(['id', 'bank_name', 'account_name', 'is_default']);

        return Inertia::render('Sales/Receivables/Show', [
            'customer' => [
                'id' => $customer->id,
                'uuid' => $customer->uuid,
                'full_name' => $customer->full_name,
                'identification' => $customer->identification_type_code.' '.$customer->identification_number,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ],
            'movements' => $paginatedMovements,
            'summary' => [
                'total_invoiced' => (int) $totalInvoiced,
                'total_collected' => (int) $totalCollected,
                'balance' => (int) $balance,
            ],
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function storeCollection(Customer $customer, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'collection_date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        CustomerCollection::create([
            ...$validated,
            'tenant_id' => $request->user()->tenant_id,
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Cobro de '.number_format($validated['amount'])." registrado para {$customer->full_name}.");
    }
}
