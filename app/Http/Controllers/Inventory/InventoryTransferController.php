<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ConfirmInventoryTransfer;
use App\Enums\InventoryLotStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InventoryLot;
use App\Models\InventoryTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryTransferController extends Controller
{
    public function index(): Response
    {
        $transfers = InventoryTransfer::query()
            ->with(['fromBranch', 'toBranch'])
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (InventoryTransfer $transfer) => [
                'id' => $transfer->id,
                'uuid' => $transfer->uuid,
                'from_branch' => $transfer->fromBranch?->name,
                'to_branch' => $transfer->toBranch?->name,
                'items_count' => $transfer->items_count,
                'status' => [
                    'value' => $transfer->status->value,
                    'label' => $transfer->status->label(),
                ],
                'confirmed_at' => $transfer->confirmed_at?->format('d/m/Y H:i'),
                'created_at' => $transfer->created_at->format('d/m/Y H:i'),
                'notes' => $transfer->notes,
            ]);

        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])
            ->all();

        return Inertia::render('Inventory/Transfers/Index', [
            'transfers' => $transfers,
            'branches' => $branches,
        ]);
    }

    public function create(): Response
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])
            ->all();

        return Inertia::render('Inventory/Transfers/Create', [
            'branches' => $branches,
        ]);
    }

    /**
     * Available lots for a single source branch, fetched on demand from the
     * transfer form — a tenant's full lot list has no natural upper bound,
     * but a transfer always sources from one branch at a time.
     */
    public function lots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
        ]);

        $lots = InventoryLot::query()
            ->with(['product:id,commercial_name'])
            ->where('branch_id', $validated['branch_id'])
            ->where('status', InventoryLotStatus::Available)
            ->where('current_quantity', '>', 0)
            ->orderBy('lot_number')
            ->get(['id', 'branch_id', 'product_id', 'lot_number', 'current_quantity', 'expires_on'])
            ->map(fn (InventoryLot $lot) => [
                'id' => $lot->id,
                'lot_number' => $lot->lot_number,
                'product_name' => $lot->product?->commercial_name,
                'branch_id' => $lot->branch_id,
                'current_quantity' => $lot->current_quantity,
                'expires_on' => $lot->expires_on?->toDateString(),
            ]);

        return response()->json(['lots' => $lots]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'from_branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'to_branch_id' => ['required', 'integer', 'different:from_branch_id', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.lot_id' => ['required', 'integer', Rule::exists('inventory_lots', 'id')->where('tenant_id', $tenantId)->where('branch_id', $request->integer('from_branch_id'))],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        // Validate stock for each item
        foreach ($validated['items'] as $item) {
            $lot = InventoryLot::findOrFail($item['lot_id']);

            if ($lot->current_quantity < $item['quantity']) {
                return back()->withErrors([
                    'items' => "Lote {$lot->lot_number}: stock insuficiente ({$lot->current_quantity} disponibles, {$item['quantity']} solicitados).",
                ]);
            }
        }

        $transfer = InventoryTransfer::create([
            'tenant_id' => $tenantId,
            'from_branch_id' => $validated['from_branch_id'],
            'to_branch_id' => $validated['to_branch_id'],
            'user_id' => $request->user()->id,
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
        ]);

        foreach ($validated['items'] as $item) {
            $transfer->items()->create([
                'inventory_lot_id' => $item['lot_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        return redirect()
            ->route('inventory.transfers.show', $transfer)
            ->with('success', 'Traslado creado como borrador.');
    }

    public function show(InventoryTransfer $transfer): Response
    {
        $transfer->load(['fromBranch', 'toBranch', 'user', 'items.lot.product']);

        return Inertia::render('Inventory/Transfers/Show', [
            'transfer' => [
                'id' => $transfer->id,
                'uuid' => $transfer->uuid,
                'from_branch' => ['id' => $transfer->fromBranch?->id, 'name' => $transfer->fromBranch?->name],
                'to_branch' => ['id' => $transfer->toBranch?->id, 'name' => $transfer->toBranch?->name],
                'user_name' => $transfer->user?->name,
                'status' => [
                    'value' => $transfer->status->value,
                    'label' => $transfer->status->label(),
                ],
                'notes' => $transfer->notes,
                'confirmed_at' => $transfer->confirmed_at?->format('d/m/Y H:i'),
                'created_at' => $transfer->created_at->format('d/m/Y H:i'),
                'items' => $transfer->items->map(fn ($item) => [
                    'id' => $item->id,
                    'lot_id' => $item->inventory_lot_id,
                    'lot_number' => $item->lot?->lot_number,
                    'product_name' => $item->lot?->product?->commercial_name,
                    'current_quantity' => $item->lot?->current_quantity,
                    'quantity' => $item->quantity,
                    'expires_on' => $item->lot?->expires_on?->toDateString(),
                ])->values()->all(),
            ],
        ]);
    }

    public function confirm(InventoryTransfer $transfer, ConfirmInventoryTransfer $action, Request $request): RedirectResponse
    {
        abort_unless($transfer->tenant_id === $request->user()->tenant_id, 403);

        try {
            $action->execute($transfer, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return redirect()
            ->route('inventory.transfers.show', $transfer)
            ->with('success', 'Traslado confirmado. Los movimientos de inventario han sido registrados.');
    }

    public function cancel(InventoryTransfer $transfer, Request $request): RedirectResponse
    {
        abort_unless($transfer->tenant_id === $request->user()->tenant_id, 403);

        if ($transfer->status->value !== 'draft') {
            return back()->withErrors(['transfer' => 'Solo se pueden cancelar traslados en estado borrador.']);
        }

        $transfer->update(['status' => 'cancelled']);

        return redirect()
            ->route('inventory.transfers.show', $transfer)
            ->with('success', 'Traslado cancelado.');
    }
}
