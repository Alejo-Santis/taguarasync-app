<?php

namespace App\Http\Controllers\Purchases;

use App\Actions\Purchases\CreatePurchaseOrder;
use App\Actions\Purchases\GetPurchaseReceiptFormOptions;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'user'])
            ->when($request->q, fn ($query, $q) => $query->where('order_number', 'like', "%{$q}%"))
            ->when($request->status, fn ($query, $s) => $query->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Purchases/Orders/Index', [
            'orders' => $orders->through(fn (PurchaseOrder $o) => [
                'id' => $o->id,
                'uuid' => $o->uuid,
                'order_number' => $o->order_number,
                'order_date' => $o->order_date->toDateString(),
                'expected_date' => $o->expected_date?->toDateString(),
                'supplier' => ['id' => $o->supplier->id, 'name' => $o->supplier->name],
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'total' => $o->total,
            ]),
            'filters' => $request->only(['q', 'status']),
            'statuses' => collect(PurchaseOrderStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(GetPurchaseReceiptFormOptions $getOptions): Response
    {
        return Inertia::render('Purchases/Orders/Create', [
            'options' => $getOptions->execute(),
        ]);
    }

    public function store(
        StorePurchaseOrderRequest $request,
        CreatePurchaseOrder $createPurchaseOrder
    ): RedirectResponse {
        $order = $createPurchaseOrder->execute([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ], $request->user());

        return to_route('purchases.orders.index')
            ->with('success', "Orden {$order->order_number} creada correctamente.");
    }

    public function show(PurchaseOrder $order): Response
    {
        $order->load(['supplier', 'items.product', 'user', 'receipts']);

        return Inertia::render('Purchases/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'uuid' => $order->uuid,
                'order_number' => $order->order_number,
                'order_date' => $order->order_date->toDateString(),
                'expected_date' => $order->expected_date?->toDateString(),
                'notes' => $order->notes,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'subtotal' => $order->subtotal,
                'tax_total' => $order->tax_total,
                'total' => $order->total,
                'supplier' => ['id' => $order->supplier->id, 'name' => $order->supplier->name, 'nit' => $order->supplier->nit],
                'user' => $order->user ? ['name' => $order->user->name] : null,
                'created_at' => $order->created_at->toDateTimeString(),
                'receipts_count' => $order->receipts->count(),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'description' => $item->description,
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

    public function send(PurchaseOrder $order): RedirectResponse
    {
        abort_unless($order->status === PurchaseOrderStatus::Draft, 422, 'Solo se pueden enviar órdenes en borrador.');

        $order->update(['status' => PurchaseOrderStatus::Sent]);

        return back()->with('success', "Orden {$order->order_number} marcada como enviada al proveedor.");
    }

    public function cancel(PurchaseOrder $order): RedirectResponse
    {
        abort_unless(
            in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Sent]),
            422,
            'Solo se pueden cancelar órdenes en borrador o enviadas.'
        );

        $order->update(['status' => PurchaseOrderStatus::Cancelled]);

        return back()->with('success', "Orden {$order->order_number} cancelada.");
    }
}
