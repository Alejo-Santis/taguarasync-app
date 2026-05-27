<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\VoidSale;
use App\Enums\FeStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Jobs\EmitElectronicInvoiceJob;
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

        $sales = Sale::with(['cashSession.register', 'user', 'customer', 'payments.paymentMethod'])
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
                'invoice_prefix' => $s->invoice_prefix,
                'payment_method' => ['value' => $s->payment_method->value, 'label' => $s->payment_method->label()],
                'payment_form' => ['value' => $s->payment_form->value, 'label' => $s->payment_form->label()],
                'payment_due_date' => $s->payment_due_date?->format('d/m/Y'),
                'status' => ['value' => $s->status->value, 'label' => $s->status->label()],
                'total' => $s->total,
                'subtotal' => $s->subtotal,
                'tax_total' => $s->tax_total,
                'amount_tendered' => $s->amount_tendered,
                'change_amount' => $s->change_amount,
                'items_count' => $s->items_count,
                'cashier' => $s->user?->name ?? '—',
                'register' => $s->cashSession?->register?->name ?? '—',
                'created_at' => $s->created_at->format('d/m/Y H:i'),
                'customer_name' => $s->customer?->full_name,
                'fe' => [
                    'status' => $s->fe_status?->value,
                    'status_label' => $s->fe_status?->label(),
                    'cufe' => $s->fe_cufe,
                    'error_message' => $s->fe_error_message,
                ],
                'payments' => $s->payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'method' => $payment->paymentMethod?->name ?? $s->payment_method->label(),
                    'amount' => $payment->amount,
                    'reference' => $payment->reference,
                    'has_attachment' => $payment->attachment_path !== null,
                    'attachment_url' => $payment->attachment_path
                        ? route('sales.payments.attachment', $payment, false)
                        : null,
                ])->values(),
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
        $sale->load(['items.presentation', 'items.lot', 'user', 'cashSession.register', 'customer', 'payments.paymentMethod']);

        return Inertia::render('Sales/Show', [
            'sale' => [
                'uuid' => $sale->uuid,
                'document_number' => $sale->document_number,
                'invoice_prefix' => $sale->invoice_prefix,
                'status' => ['value' => $sale->status->value, 'label' => $sale->status->label()],
                'payment_method' => $sale->payment_method->label(),
                'payment_form' => $sale->payment_form?->label(),
                'payment_form_value' => $sale->payment_form?->value,
                'payment_due_date' => $sale->payment_due_date?->format('d/m/Y'),
                'amount_tendered' => $sale->amount_tendered,
                'change_amount' => $sale->change_amount,
                'subtotal' => $sale->subtotal,
                'tax_total' => $sale->tax_total,
                'total' => $sale->total,
                'cashier' => $sale->user?->name ?? '—',
                'register' => $sale->cashSession?->register?->name ?? '—',
                'created_at' => $sale->created_at->format('d/m/Y H:i:s'),
                'customer' => $sale->customer ? [
                    'full_name' => $sale->customer->full_name,
                    'identification' => $sale->customer->identification_type_code.' '.$sale->customer->identification_number.($sale->customer->verification_digit ? '-'.$sale->customer->verification_digit : ''),
                ] : null,
                'fe' => [
                    'status' => $sale->fe_status?->value,
                    'status_label' => $sale->fe_status?->label(),
                    'cufe' => $sale->fe_cufe,
                    'qr_code' => $sale->fe_qr_code,
                    'sent_at' => $sale->fe_sent_at?->format('d/m/Y H:i:s'),
                    'accepted_at' => $sale->fe_accepted_at?->format('d/m/Y H:i:s'),
                    'error_message' => $sale->fe_error_message,
                ],
                'items' => $sale->items->map(fn ($i) => [
                    'description' => $i->description,
                    'quantity' => $i->quantity,
                    'unit_price' => $i->unit_price,
                    'tax_rate' => $i->tax_rate,
                    'line_subtotal' => $i->line_subtotal,
                    'line_tax' => $i->line_tax,
                    'line_total' => $i->line_total,
                ])->values(),
                'payments' => $sale->payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'method' => $payment->paymentMethod?->name ?? $sale->payment_method->label(),
                    'amount' => $payment->amount,
                    'reference' => $payment->reference,
                    'has_attachment' => $payment->attachment_path !== null,
                    'attachment_url' => $payment->attachment_path
                        ? route('sales.payments.attachment', $payment, false)
                        : null,
                ])->values(),
            ],
        ]);
    }

    public function receipt(Sale $sale, Request $request): View
    {
        $sale->load(['items.presentation', 'user', 'cashSession.register', 'customer', 'payments.paymentMethod']);

        $tenantName = $request->user()?->tenant?->name ?? 'Farmacia';

        return view('receipts.sale', compact('sale', 'tenantName'));
    }

    public function retryFe(Sale $sale, Request $request): RedirectResponse
    {
        if (! in_array($sale->fe_status?->value, [FeStatus::Pending->value, FeStatus::Rejected->value, FeStatus::Contingency->value])) {
            return back()->withErrors(['fe' => 'Solo se pueden reintentar facturas pendientes, en contingencia o rechazadas.']);
        }

        $sale->update(['fe_status' => FeStatus::Pending, 'fe_error_message' => null]);

        EmitElectronicInvoiceJob::dispatch($sale->id, $request->user()->tenant_id);

        return back()->with('success', 'Reintento de emisión FE enviado.');
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
