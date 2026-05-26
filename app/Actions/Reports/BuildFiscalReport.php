<?php

namespace App\Actions\Reports;

use App\Enums\PurchaseReceiptStatus;
use App\Enums\SaleStatus;
use App\Models\CreditNote;
use App\Models\PurchaseReceipt;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BuildFiscalReport
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $tenantId, Carbon $from, Carbon $to): array
    {
        $salesSummary = $this->salesSummary($tenantId, $from, $to);
        $creditNoteSummary = $this->creditNoteSummary($tenantId, $from, $to);
        $purchaseSummary = $this->purchaseSummary($tenantId, $from, $to);

        return [
            'summary' => [
                'sales' => $salesSummary,
                'credit_notes' => $creditNoteSummary,
                'purchases' => $purchaseSummary,
                'net_sales' => [
                    'subtotal' => $salesSummary['subtotal'] - $creditNoteSummary['subtotal'],
                    'tax_total' => $salesSummary['tax_total'] - $creditNoteSummary['tax_total'],
                    'total' => $salesSummary['total'] - $creditNoteSummary['total'],
                ],
                'vat' => $this->vatSummary($salesSummary, $creditNoteSummary, $purchaseSummary),
            ],
            'tax_breakdown' => $this->taxBreakdown($tenantId, $from, $to),
            'documents' => $this->documents($tenantId, $from, $to),
        ];
    }

    /**
     * @return array{count: int, subtotal: int, tax_total: int, total: int}
     */
    private function salesSummary(int $tenantId, Carbon $from, Carbon $to): array
    {
        $query = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', SaleStatus::Completed)
            ->whereDate('created_at', '>=', $from->toDateString())
            ->whereDate('created_at', '<=', $to->toDateString());

        return $this->summaryFrom($query);
    }

    /**
     * @return array{count: int, subtotal: int, tax_total: int, total: int}
     */
    private function creditNoteSummary(int $tenantId, Carbon $from, Carbon $to): array
    {
        $query = CreditNote::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', '>=', $from->toDateString())
            ->whereDate('created_at', '<=', $to->toDateString());

        return $this->summaryFrom($query);
    }

    /**
     * @return array{count: int, subtotal: int, tax_total: int, total: int}
     */
    private function purchaseSummary(int $tenantId, Carbon $from, Carbon $to): array
    {
        $query = PurchaseReceipt::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', PurchaseReceiptStatus::Received)
            ->whereDate('received_at', '>=', $from->toDateString())
            ->whereDate('received_at', '<=', $to->toDateString());

        return $this->summaryFrom($query);
    }

    /**
     * @param  Builder<Model>  $query
     * @return array{count: int, subtotal: int, tax_total: int, total: int}
     */
    private function summaryFrom($query): array
    {
        return [
            'count' => (int) (clone $query)->count(),
            'subtotal' => (int) (clone $query)->sum('subtotal'),
            'tax_total' => (int) (clone $query)->sum('tax_total'),
            'total' => (int) (clone $query)->sum('total'),
        ];
    }

    /**
     * @param  array{tax_total: int}  $sales
     * @param  array{tax_total: int}  $creditNotes
     * @param  array{tax_total: int}  $purchases
     * @return array{generated: int, discounted: int, payable: int, favorable_balance: int}
     */
    private function vatSummary(array $sales, array $creditNotes, array $purchases): array
    {
        $generated = $sales['tax_total'] - $creditNotes['tax_total'];
        $discounted = $purchases['tax_total'];

        return [
            'generated' => $generated,
            'discounted' => $discounted,
            'payable' => max($generated - $discounted, 0),
            'favorable_balance' => max($discounted - $generated, 0),
        ];
    }

    /**
     * @return array<int, array{rate: string, sales_subtotal: int, sales_tax: int, credit_note_subtotal: int, credit_note_tax: int, purchase_subtotal: int, purchase_tax: int, net_generated_tax: int}>
     */
    private function taxBreakdown(int $tenantId, Carbon $from, Carbon $to): array
    {
        $sales = $this->taxRows('sale_items', 'sales', 'sale_id', 'sales.created_at', [
            ['sales.tenant_id', '=', $tenantId],
            ['sales.status', '=', SaleStatus::Completed->value],
        ], $from, $to);

        $creditNotes = $this->taxRows('credit_note_items', 'credit_notes', 'credit_note_id', 'credit_notes.created_at', [
            ['credit_notes.tenant_id', '=', $tenantId],
        ], $from, $to);

        $purchases = $this->taxRows('purchase_receipt_items', 'purchase_receipts', 'purchase_receipt_id', 'purchase_receipts.received_at', [
            ['purchase_receipts.tenant_id', '=', $tenantId],
            ['purchase_receipts.status', '=', PurchaseReceiptStatus::Received->value],
        ], $from, $to);

        return $sales
            ->merge($creditNotes)
            ->merge($purchases)
            ->pluck('rate')
            ->unique()
            ->sortByDesc(fn (string $rate): float => (float) $rate)
            ->map(function (string $rate) use ($sales, $creditNotes, $purchases): array {
                $saleRow = $sales->firstWhere('rate', $rate) ?? ['subtotal' => 0, 'tax' => 0];
                $creditNoteRow = $creditNotes->firstWhere('rate', $rate) ?? ['subtotal' => 0, 'tax' => 0];
                $purchaseRow = $purchases->firstWhere('rate', $rate) ?? ['subtotal' => 0, 'tax' => 0];

                return [
                    'rate' => $rate,
                    'sales_subtotal' => (int) $saleRow['subtotal'],
                    'sales_tax' => (int) $saleRow['tax'],
                    'credit_note_subtotal' => (int) $creditNoteRow['subtotal'],
                    'credit_note_tax' => (int) $creditNoteRow['tax'],
                    'purchase_subtotal' => (int) $purchaseRow['subtotal'],
                    'purchase_tax' => (int) $purchaseRow['tax'],
                    'net_generated_tax' => (int) $saleRow['tax'] - (int) $creditNoteRow['tax'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: mixed}>  $conditions
     * @return Collection<int, array{rate: string, subtotal: int, tax: int}>
     */
    private function taxRows(string $itemTable, string $parentTable, string $foreignKey, string $dateColumn, array $conditions, Carbon $from, Carbon $to): Collection
    {
        return DB::table($itemTable)
            ->join($parentTable, "{$parentTable}.id", '=', "{$itemTable}.{$foreignKey}")
            ->where($conditions)
            ->whereDate($dateColumn, '>=', $from->toDateString())
            ->whereDate($dateColumn, '<=', $to->toDateString())
            ->selectRaw("{$itemTable}.tax_rate as rate")
            ->selectRaw("COALESCE(SUM({$itemTable}.line_subtotal), 0) as subtotal")
            ->selectRaw("COALESCE(SUM({$itemTable}.line_tax), 0) as tax")
            ->groupBy("{$itemTable}.tax_rate")
            ->get()
            ->map(fn ($row): array => [
                'rate' => number_format((float) $row->rate, 2, '.', ''),
                'subtotal' => (int) $row->subtotal,
                'tax' => (int) $row->tax,
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documents(int $tenantId, Carbon $from, Carbon $to): array
    {
        $sales = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', SaleStatus::Completed)
            ->whereDate('created_at', '>=', $from->toDateString())
            ->whereDate('created_at', '<=', $to->toDateString())
            ->latest()
            ->limit(60)
            ->get(['document_number', 'subtotal', 'tax_total', 'total', 'fe_status', 'created_at'])
            ->map(fn (Sale $sale): array => [
                'type' => 'Factura venta',
                'document_number' => $sale->document_number,
                'date' => $sale->created_at?->format('d/m/Y H:i'),
                'subtotal' => $sale->subtotal,
                'tax_total' => $sale->tax_total,
                'total' => $sale->total,
                'fe_status' => $sale->fe_status?->label() ?? 'Pendiente',
                'impact' => 'positive',
                'sort_date' => $sale->created_at,
            ]);

        $creditNotes = CreditNote::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', '>=', $from->toDateString())
            ->whereDate('created_at', '<=', $to->toDateString())
            ->latest()
            ->limit(60)
            ->get(['prefix', 'number', 'subtotal', 'tax_total', 'total', 'fe_status', 'created_at'])
            ->map(fn (CreditNote $creditNote): array => [
                'type' => 'Nota credito',
                'document_number' => "{$creditNote->prefix}{$creditNote->number}",
                'date' => $creditNote->created_at?->format('d/m/Y H:i'),
                'subtotal' => -$creditNote->subtotal,
                'tax_total' => -$creditNote->tax_total,
                'total' => -$creditNote->total,
                'fe_status' => $creditNote->fe_status?->label() ?? 'Pendiente',
                'impact' => 'negative',
                'sort_date' => $creditNote->created_at,
            ]);

        $purchases = PurchaseReceipt::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', PurchaseReceiptStatus::Received)
            ->whereDate('received_at', '>=', $from->toDateString())
            ->whereDate('received_at', '<=', $to->toDateString())
            ->latest('received_at')
            ->limit(60)
            ->get(['document_number', 'subtotal', 'tax_total', 'total', 'received_at'])
            ->map(fn (PurchaseReceipt $purchase): array => [
                'type' => 'Compra',
                'document_number' => $purchase->document_number,
                'date' => $purchase->received_at?->format('d/m/Y H:i'),
                'subtotal' => $purchase->subtotal,
                'tax_total' => $purchase->tax_total,
                'total' => $purchase->total,
                'fe_status' => 'Soporte compra',
                'impact' => 'purchase',
                'sort_date' => $purchase->received_at,
            ]);

        return $sales
            ->merge($creditNotes)
            ->merge($purchases)
            ->sortByDesc('sort_date')
            ->take(80)
            ->map(function (array $document): array {
                unset($document['sort_date']);

                return $document;
            })
            ->values()
            ->all();
    }
}
