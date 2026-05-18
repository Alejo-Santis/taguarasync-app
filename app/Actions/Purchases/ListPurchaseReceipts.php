<?php

namespace App\Actions\Purchases;

use App\Enums\PurchaseReceiptStatus;
use App\Models\PurchaseReceipt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ListPurchaseReceipts
{
    /**
     * @return array{
     *     receipts: LengthAwarePaginator<int, array<string, mixed>>,
     *     filters: array{q: string, status: string},
     *     stats: array{receipts: int, total: int, items: int},
     *     statuses: array<int, array{value: string, label: string}>
     * }
     */
    public function execute(Request $request): array
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $receipts = PurchaseReceipt::query()
            ->select([
                'id',
                'uuid',
                'supplier_id',
                'user_id',
                'document_number',
                'document_date',
                'received_at',
                'subtotal',
                'tax_total',
                'total',
                'status',
                'notes',
            ])
            ->with([
                'supplier:id,name,nit',
                'user:id,name',
                'items:id,purchase_receipt_id,description,lot_number,expires_on,quantity,unit_cost,line_total',
            ])
            ->withCount('items')
            ->when($filters['q'] !== '', fn (Builder $query) => $this->applySearch($query, $filters['q']))
            ->when($this->isValidStatus($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->latest('received_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PurchaseReceipt $receipt) => $this->transformReceipt($receipt));

        return [
            'receipts' => $receipts,
            'filters' => $filters,
            'stats' => $this->stats(),
            'statuses' => $this->statuses(),
        ];
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql'
            ? 'ilike'
            : 'like';

        return $query->where(function (Builder $query) use ($operator, $search): void {
            $query
                ->where('document_number', $operator, "%{$search}%")
                ->orWhereHas('supplier', fn (Builder $query) => $query->where('name', $operator, "%{$search}%"));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function transformReceipt(PurchaseReceipt $receipt): array
    {
        return [
            'uuid' => $receipt->uuid,
            'document_number' => $receipt->document_number,
            'document_date' => $receipt->document_date?->format('d/m/Y'),
            'received_at' => $receipt->received_at?->format('d/m/Y H:i'),
            'subtotal' => $receipt->subtotal,
            'tax_total' => $receipt->tax_total,
            'total' => $receipt->total,
            'status' => [
                'value' => $receipt->status->value,
                'label' => $receipt->status->label(),
            ],
            'supplier' => [
                'name' => $receipt->supplier?->name,
                'nit' => $receipt->supplier?->nit,
            ],
            'user' => $receipt->user?->name,
            'items_count' => $receipt->items_count,
            'items' => $receipt->items->map(fn ($item) => [
                'description' => $item->description,
                'lot_number' => $item->lot_number,
                'expires_on' => $item->expires_on?->format('d/m/Y'),
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
                'line_total' => $item->line_total,
            ])->values(),
            'notes' => $receipt->notes,
        ];
    }

    /**
     * @return array{receipts: int, total: int, items: int}
     */
    private function stats(): array
    {
        $received = PurchaseReceiptStatus::Received;

        return [
            'receipts' => PurchaseReceipt::count(),
            'total' => PurchaseReceipt::where('status', $received)->sum('total'),
            'items' => (int) PurchaseReceipt::where('status', $received)
                ->join('purchase_receipt_items', 'purchase_receipts.id', '=', 'purchase_receipt_items.purchase_receipt_id')
                ->count('purchase_receipt_items.id'),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return collect(PurchaseReceiptStatus::cases())
            ->map(fn (PurchaseReceiptStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->all();
    }

    private function isValidStatus(string $status): bool
    {
        return PurchaseReceiptStatus::tryFrom($status) !== null;
    }
}
