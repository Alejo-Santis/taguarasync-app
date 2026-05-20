<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\VoidSale;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'method' => $request->string('method')->toString(),
            'status' => $request->string('status')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $sales = Sale::with(['cashSession.register', 'user'])
            ->withCount('items')
            ->when($filters['q'] !== '', fn ($q) => $q->where('document_number', 'like', "%{$filters['q']}%"))
            ->when($filters['method'] !== '', fn ($q) => $q->where('payment_method', $filters['method']))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['from'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Sale $s) => [
                'uuid' => $s->uuid,
                'document_number' => $s->document_number,
                'payment_method' => ['value' => $s->payment_method->value, 'label' => $s->payment_method->label()],
                'status' => ['value' => $s->status->value, 'label' => $s->status->label()],
                'total' => $s->total,
                'items_count' => $s->items_count,
                'cashier' => $s->user?->name ?? '—',
                'register' => $s->cashSession?->register?->name ?? '—',
                'created_at' => $s->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'filters' => $filters,
            'stats' => [
                'total_today' => (int) Sale::whereDate('created_at', today())->where('status', SaleStatus::Completed)->sum('total'),
                'count_today' => Sale::whereDate('created_at', today())->where('status', SaleStatus::Completed)->count(),
                'voided_today' => Sale::whereDate('created_at', today())->where('status', SaleStatus::Voided)->count(),
            ],
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()]),
            'statuses' => collect(SaleStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function show(Sale $sale): Response
    {
        $sale->load(['items.presentation', 'items.lot', 'user', 'cashSession.register']);

        return Inertia::render('Sales/Show', [
            'sale' => [
                'uuid' => $sale->uuid,
                'document_number' => $sale->document_number,
                'status' => ['value' => $sale->status->value, 'label' => $sale->status->label()],
                'payment_method' => $sale->payment_method->label(),
                'amount_tendered' => $sale->amount_tendered,
                'change_amount' => $sale->change_amount,
                'subtotal' => $sale->subtotal,
                'tax_total' => $sale->tax_total,
                'total' => $sale->total,
                'cashier' => $sale->user?->name ?? '—',
                'register' => $sale->cashSession?->register?->name ?? '—',
                'created_at' => $sale->created_at->format('d/m/Y H:i:s'),
                'items' => $sale->items->map(fn ($i) => [
                    'description' => $i->description,
                    'quantity' => $i->quantity,
                    'unit_price' => $i->unit_price,
                    'tax_rate' => $i->tax_rate,
                    'line_subtotal' => $i->line_subtotal,
                    'line_tax' => $i->line_tax,
                    'line_total' => $i->line_total,
                ])->values(),
            ],
        ]);
    }

    public function receipt(Sale $sale, Request $request): View
    {
        $sale->load(['items.presentation', 'user', 'cashSession.register']);

        $tenantName = $request->user()?->tenant?->name ?? 'Farmacia';

        return view('receipts.sale', compact('sale', 'tenantName'));
    }

    public function void(Sale $sale, Request $request, VoidSale $voidSale): RedirectResponse
    {
        try {
            $voidSale->execute($sale, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['void' => $e->getMessage()]);
        }

        return back()->with('success', "Venta {$sale->document_number} anulada. El inventario fue restaurado.");
    }
}
