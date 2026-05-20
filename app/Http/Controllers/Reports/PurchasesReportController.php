<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PurchaseReceiptStatus;
use App\Models\PurchaseReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchasesReportController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $request->date('from', 'Y-m-d') ?? today()->startOfMonth();
        $to = $request->date('to', 'Y-m-d') ?? today();

        $receipts = PurchaseReceipt::with('supplier:id,name')
            ->where('status', PurchaseReceiptStatus::Received)
            ->whereBetween(DB::raw('DATE(received_at)'), [$from, $to])
            ->withCount('items')
            ->latest('received_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PurchaseReceipt $r) => [
                'document_number' => $r->document_number,
                'supplier' => $r->supplier?->name ?? '—',
                'received_at' => $r->received_at?->format('d/m/Y H:i') ?? '—',
                'items_count' => $r->items_count,
                'subtotal' => $r->subtotal,
                'tax_total' => $r->tax_total,
                'total' => $r->total,
            ]);

        $summary = PurchaseReceipt::where('status', PurchaseReceiptStatus::Received)
            ->whereBetween(DB::raw('DATE(received_at)'), [$from, $to]);

        return Inertia::render('Reports/Purchases', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'receipts' => $receipts,
            'summary' => [
                'count' => (int) (clone $summary)->count(),
                'total' => (int) (clone $summary)->sum('total'),
                'tax_total' => (int) (clone $summary)->sum('tax_total'),
            ],
        ]);
    }
}
