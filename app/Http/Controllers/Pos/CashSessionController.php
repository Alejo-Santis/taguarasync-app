<?php

namespace App\Http\Controllers\Pos;

use App\Actions\Pos\CloseCashSession;
use App\Actions\Pos\OpenCashSession;
use App\Actions\Pos\TransformCashSessionSummary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\CloseCashSessionRequest;
use App\Http\Requests\Pos\OpenCashSessionRequest;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\SalePayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CashSessionController extends Controller
{
    public function open(): Response
    {
        $registers = CashRegister::where('is_active', true)
            ->with('branch:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (CashRegister $register) => [
                'id' => $register->id,
                'name' => $register->name,
                'code' => $register->code,
                'branch_name' => $register->branch?->name ?? 'Sin sucursal',
                'has_open_session' => $register->hasOpenSession(),
            ])
            ->values();

        return Inertia::render('Pos/OpenSession', [
            'registers' => $registers,
        ]);
    }

    public function store(OpenCashSessionRequest $request, OpenCashSession $openSession): RedirectResponse
    {
        try {
            $openSession->execute($request->validated(), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return to_route('pos.index')->with('success', 'Turno abierto. ¡Buenas ventas!');
    }

    public function close(CashSession $session): Response|RedirectResponse
    {
        if (! $session->isOpen()) {
            return to_route('pos.index');
        }

        $money = fn (int $v) => number_format($v, 0, ',', '.');

        $paymentTotals = SalePayment::query()
            ->where('cash_session_id', $session->id)
            ->join('payment_methods', 'sale_payments.payment_method_id', '=', 'payment_methods.id')
            ->selectRaw('payment_methods.type, payment_methods.affects_cash, SUM(sale_payments.amount) as total')
            ->groupBy('payment_methods.type', 'payment_methods.affects_cash')
            ->get();

        $cashSales = (int) $paymentTotals->where('affects_cash', true)->sum('total');
        $cardSales = (int) $paymentTotals->where('type', 'card')->sum('total');
        $transferSales = (int) $paymentTotals->whereIn('type', ['transfer', 'wallet'])->sum('total');

        if ($paymentTotals->isEmpty()) {
            $cashSales = $session->sales()->where('payment_method', 'cash')->sum('total');
            $cardSales = $session->sales()->where('payment_method', 'card')->sum('total');
            $transferSales = $session->sales()->where('payment_method', 'transfer')->sum('total');
        }
        $totalSales = $session->sales()->count();

        return Inertia::render('Pos/CloseSession', [
            'session' => [
                'uuid' => $session->uuid,
                'register_name' => $session->register->name,
                'cashier_name' => $session->user->name,
                'opened_at' => $session->opened_at->format('d/m/Y H:i'),
                'opening_amount' => $session->opening_amount,
                'total_sales' => $totalSales,
                'cash_sales' => (int) $cashSales,
                'card_sales' => (int) $cardSales,
                'transfer_sales' => (int) $transferSales,
                'expected_closing' => $session->expectedClosingAmount(),
            ],
        ]);
    }

    public function update(
        CashSession $session,
        CloseCashSessionRequest $request,
        CloseCashSession $closeSession
    ): RedirectResponse {
        if (! $session->isOpen()) {
            return to_route('pos.index');
        }

        $closed = $closeSession->execute($session, $request->validated(), $request->user());

        $diff = $closed->difference ?? 0;
        $diffMsg = $diff === 0
            ? 'Caja cuadrada sin diferencia.'
            : ($diff > 0 ? 'Sobrante: $'.number_format($diff, 0, ',', '.') : 'Faltante: $'.number_format(abs($diff), 0, ',', '.'));

        return to_route('pos.session.closed', $closed)->with('success', "Turno cerrado. {$diffMsg}");
    }

    public function closed(CashSession $session, TransformCashSessionSummary $transformSummary): Response|RedirectResponse
    {
        if ($session->isOpen()) {
            return to_route('pos.index');
        }

        return Inertia::render('Pos/SessionClosed', [
            'session' => $transformSummary->execute($session),
        ]);
    }
}
