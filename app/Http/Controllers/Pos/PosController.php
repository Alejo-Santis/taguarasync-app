<?php

namespace App\Http\Controllers\Pos;

use App\Actions\Payments\EnsureDefaultPaymentMethods;
use App\Actions\Pos\GetPosProducts;
use App\Actions\Pos\ProcessSale;
use App\Enums\CashSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\ProcessSaleRequest;
use App\Models\BankAccount;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\DianIdentificationType;
use App\Models\DianRegimeType;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(Request $request, EnsureDefaultPaymentMethods $ensureDefaultPaymentMethods): Response|RedirectResponse
    {
        $session = CashSession::where('user_id', $request->user()->id)
            ->where('status', CashSessionStatus::Open)
            ->with('register')
            ->first();

        if (! $session) {
            return to_route('pos.session.open');
        }

        $paymentMethods = $ensureDefaultPaymentMethods->execute($request->user()->tenant_id);

        return Inertia::render('Pos/Index', [
            'activeSession' => [
                'uuid' => $session->uuid,
                'register_name' => $session->register->name,
                'register_code' => $session->register->code,
                'opened_at' => $session->opened_at->format('H:i'),
                'sales_count' => $session->sales()->count(),
                'sales_total' => $session->salesTotal(),
            ],
            'customerFormOptions' => [
                'identification_types' => DianIdentificationType::where('is_active', true)
                    ->orderBy('name')->get(['code', 'name']),
                'regime_types' => DianRegimeType::orderBy('name')->get(['code', 'name']),
            ],
            'paymentOptions' => [
                'methods' => $paymentMethods->map(fn ($method) => [
                    'id' => $method->id,
                    'name' => $method->name,
                    'code' => $method->code,
                    'type' => $method->type,
                    'requires_reference' => $method->requires_reference,
                    'requires_bank_account' => $method->requires_bank_account,
                    'allows_attachment' => $method->allows_attachment,
                    'affects_cash' => $method->affects_cash,
                ])->values(),
                'bank_accounts' => BankAccount::query()
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('bank_name')
                    ->get(['id', 'bank_name', 'account_name', 'account_number', 'type', 'is_default']),
            ],
        ]);
    }

    public function search(Request $request, GetPosProducts $getPosProducts): JsonResponse
    {
        $query = $request->string('q')->trim()->toString();
        $priceListId = $request->integer('price_list_id') ?: null;

        return response()->json($getPosProducts->execute($query, $priceListId));
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $query = $request->string('q')->trim()->toString();

        // Con query vacío devolvemos el Consumidor Final como opción por defecto
        if (mb_strlen($query) < 2) {
            $consumidorFinal = Customer::consumidorFinal()
                ->where('is_active', true)
                ->first(['id', 'identification_type_code', 'identification_number', 'verification_digit', 'first_name', 'last_name', 'business_name', 'regime_type_code', 'price_list_id']);

            if ($consumidorFinal) {
                return response()->json([$this->formatCustomer($consumidorFinal)]);
            }

            return response()->json([]);
        }

        $customers = Customer::where(function ($q) use ($query): void {
            $q->where('identification_number', 'like', "{$query}%")
                ->orWhere('first_name', 'ilike', "%{$query}%")
                ->orWhere('last_name', 'ilike', "%{$query}%")
                ->orWhere('business_name', 'ilike', "%{$query}%");
        })
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN identification_number = '222222222222' THEN 0 ELSE 1 END")
            ->limit(8)
            ->withSum(
                ['sales as credit_invoiced' => fn ($q) => $q->where('payment_form', '2')->where('status', '!=', 'voided')],
                'total'
            )
            ->withSum('collections as credit_collected', 'amount')
            ->get(['id', 'identification_type_code', 'identification_number', 'verification_digit', 'first_name', 'last_name', 'business_name', 'regime_type_code', 'price_list_id', 'credit_limit']);

        return response()->json($customers->map(fn (Customer $c) => $this->formatCustomer($c)));
    }

    /** @return array<string, mixed> */
    private function formatCustomer(Customer $c): array
    {
        $creditInvoiced = (int) ($c->credit_invoiced ?? 0);
        $creditCollected = (int) ($c->credit_collected ?? 0);
        $creditBalance = $creditInvoiced - $creditCollected;

        return [
            'id' => $c->id,
            'full_name' => $c->full_name,
            'identification' => $c->identification_type_code.' '.$c->identification_number.($c->verification_digit ? '-'.$c->verification_digit : ''),
            'regime' => $c->regime_type_code === '48' ? 'Resp. IVA' : 'No resp. IVA',
            'regime_code' => $c->regime_type_code,
            'is_consumidor_final' => $c->isConsumidorFinal(),
            'price_list_id' => $c->price_list_id,
            'credit_limit' => $c->credit_limit,
            'credit_balance' => $creditBalance,
        ];
    }

    public function quickStoreCustomer(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'identification_type_code' => ['required', 'string', Rule::exists('dian_identification_types', 'code')],
            'identification_number' => [
                'required', 'string', 'max:30',
                Rule::unique('customers', 'identification_number')
                    ->where('tenant_id', $tenantId)
                    ->where('identification_type_code', $request->string('identification_type_code')->toString())
                    ->whereNull('deleted_at'),
            ],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'business_name' => ['nullable', 'string', 'max:220'],
            'regime_type_code' => ['nullable', 'string', Rule::exists('dian_regime_types', 'code')],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
        ]);

        $customer = Customer::create($validated);

        return response()->json([
            'id' => $customer->id,
            'full_name' => $customer->full_name,
            'identification' => $customer->identification_type_code.' '.$customer->identification_number,
            'regime' => $customer->regime_type_code === '48' ? 'Resp. IVA' : 'No resp. IVA',
            'regime_code' => $customer->regime_type_code,
        ], 201);
    }

    public function recentSales(Request $request): JsonResponse
    {
        $session = CashSession::where('user_id', $request->user()->id)
            ->where('status', CashSessionStatus::Open)
            ->first();

        if (! $session) {
            return response()->json([]);
        }

        $sales = $session->sales()
            ->latest()
            ->limit(10)
            ->get(['id', 'uuid', 'document_number', 'total', 'payment_method', 'status', 'created_at'])
            ->map(fn (Sale $sale) => [
                'uuid' => $sale->uuid,
                'document_number' => $sale->document_number,
                'total' => $sale->total,
                'payment_method' => $sale->payment_method->label(),
                'status' => $sale->status->value,
                'created_at' => $sale->created_at->format('H:i'),
            ]);

        return response()->json($sales);
    }

    public function store(ProcessSaleRequest $request, ProcessSale $processSale): RedirectResponse
    {
        $session = CashSession::where('user_id', $request->user()->id)
            ->where('status', CashSessionStatus::Open)
            ->first();

        if (! $session) {
            return to_route('pos.session.open');
        }

        try {
            $sale = $processSale->execute($request->validated(), $request->user(), $session->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        $isCredit = $sale->payment_form === '2';

        return to_route('pos.index')->with('completedSale', [
            'uuid' => $sale->uuid,
            'document_number' => $sale->document_number,
            'total' => $sale->total,
            'payment_form' => $sale->payment_form,
            'payment_due_date' => $isCredit ? $sale->payment_due_date?->toDateString() : null,
            'payment_method' => $isCredit ? 'Crédito' : $sale->payment_method->label(),
            'change_amount' => $sale->change_amount,
            'payments' => $sale->payments()
                ->with('paymentMethod:id,name')
                ->get()
                ->map(fn ($payment) => [
                    'method' => $payment->paymentMethod?->name ?? $sale->payment_method->label(),
                    'amount' => $payment->amount,
                    'reference' => $payment->reference,
                    'has_attachment' => $payment->attachment_path !== null,
                ]),
            'items_count' => $sale->items()->count(),
        ]);
    }
}
