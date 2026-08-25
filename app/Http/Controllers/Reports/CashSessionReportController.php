<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Pos\TransformCashSessionSummary;
use App\Enums\CashSessionStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashSessionReportController extends Controller
{
    public function __construct(private readonly TransformCashSessionSummary $transformSummary) {}

    public function index(Request $request): Response
    {
        $filters = [
            'from' => $request->string('from')->toString() ?: today()->startOfMonth()->toDateString(),
            'to' => $request->string('to')->toString() ?: today()->toDateString(),
            'status' => $request->string('status')->toString(),
            'cash_register_id' => $request->integer('cash_register_id') ?: null,
            'user_id' => $request->integer('user_id') ?: null,
            'difference' => $request->string('difference')->toString(),
        ];

        $baseQuery = CashSession::query()
            ->with(['register:id,name,code', 'user:id,name', 'closedBy:id,name'])
            ->withCount('sales')
            ->withSum('sales as sales_total', 'total')
            ->withSum(['sales as cash_sales_total' => fn (Builder $query) => $query->where('payment_method', PaymentMethod::Cash->value)], 'total')
            ->withSum(['sales as card_sales_total' => fn (Builder $query) => $query->where('payment_method', PaymentMethod::Card->value)], 'total')
            ->withSum(['sales as transfer_sales_total' => fn (Builder $query) => $query->where('payment_method', PaymentMethod::Transfer->value)], 'total')
            ->whereBetween('opened_at', [
                "{$filters['from']} 00:00:00",
                "{$filters['to']} 23:59:59",
            ])
            ->when(CashSessionStatus::tryFrom($filters['status']) !== null, fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['cash_register_id'], fn (Builder $query, int $registerId) => $query->where('cash_register_id', $registerId))
            ->when($filters['user_id'], fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['difference'] === 'balanced', fn (Builder $query) => $query->where('difference', 0))
            ->when($filters['difference'] === 'short', fn (Builder $query) => $query->where('difference', '<', 0))
            ->when($filters['difference'] === 'over', fn (Builder $query) => $query->where('difference', '>', 0));

        $sessionIds = (clone $baseQuery)->select('cash_sessions.id');

        $sessions = $baseQuery
            ->latest('opened_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CashSession $session) => $this->transformSummary->execute($session));

        return Inertia::render('Reports/CashSessions', [
            'filters' => $filters,
            'sessions' => $sessions,
            'summary' => [
                'sessions_count' => (clone $sessionIds)->count(),
                'open_count' => CashSession::query()->whereIn('id', (clone $sessionIds))->where('status', CashSessionStatus::Open->value)->count(),
                'closed_count' => CashSession::query()->whereIn('id', (clone $sessionIds))->where('status', CashSessionStatus::Closed->value)->count(),
                'difference_total' => (int) CashSession::query()->whereIn('id', (clone $sessionIds))->sum('difference'),
                'sales_total' => (int) Sale::query()->whereIn('cash_session_id', (clone $sessionIds))->sum('total'),
            ],
            'registers' => CashRegister::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (CashRegister $register) => [
                    'id' => $register->id,
                    'name' => "{$register->name} ({$register->code})",
                ]),
            'cashiers' => User::query()
                ->whereIn('id', CashSession::query()->select('user_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function show(CashSession $session): Response
    {
        $session->load(['register:id,name,code', 'user:id,name', 'closedBy:id,name']);

        $sales = $session->sales()
            ->with('customer:id,first_name,last_name,business_name,identification_number')
            ->latest()
            ->paginate(20)
            ->through(fn (Sale $sale) => [
                'uuid' => $sale->uuid,
                'document_number' => $sale->document_number,
                'customer' => $sale->customer?->full_name ?? 'Consumidor final',
                'payment_method' => $sale->payment_method->label(),
                'subtotal' => $sale->subtotal,
                'tax_total' => $sale->tax_total,
                'total' => $sale->total,
                'status' => $sale->status->label(),
                'created_at' => $sale->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Reports/CashSessionShow', [
            'session' => $this->transformSummary->execute($session),
            'sales' => $sales,
        ]);
    }
}
