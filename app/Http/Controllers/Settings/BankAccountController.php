<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankAccountMovement;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): Response
    {
        $filters = [
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'movement_status' => $request->string('movement_status')->toString(),
            'account_id' => $request->integer('account_id'),
        ];

        $accounts = BankAccount::query()
            ->withSum(['movements as inflows_total' => fn (Builder $query) => $query->where('type', 'inflow')], 'amount')
            ->withSum(['movements as outflows_total' => fn (Builder $query) => $query->where('type', 'outflow')], 'amount')
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('bank_name', 'ilike', "%{$filters['q']}%")
                        ->orWhere('account_name', 'ilike', "%{$filters['q']}%")
                        ->orWhere('account_number', 'ilike', "%{$filters['q']}%");
                });
            })
            ->when($filters['status'] === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderByDesc('is_default')
            ->orderBy('bank_name')
            ->paginate(10, ['*'], 'page')
            ->withQueryString()
            ->through(fn (BankAccount $account) => [
                'id' => $account->id,
                'bank_name' => $account->bank_name,
                'account_name' => $account->account_name,
                'account_number' => $account->account_number,
                'type' => $account->type,
                'is_default' => $account->is_default,
                'is_active' => $account->is_active,
                'notes' => $account->notes,
                'balance' => (int) (($account->inflows_total ?? 0) - ($account->outflows_total ?? 0)),
                'inflows_total' => (int) ($account->inflows_total ?? 0),
                'outflows_total' => (int) ($account->outflows_total ?? 0),
            ]);

        $movements = BankAccountMovement::query()
            ->with(['bankAccount:id,bank_name,account_name', 'reconciledBy:id,name'])
            ->when($filters['account_id'] > 0, fn (Builder $query) => $query->where('bank_account_id', $filters['account_id']))
            ->when($this->isValidMovementStatus($filters['movement_status']), fn (Builder $query) => $query->where('status', $filters['movement_status']))
            ->latest('occurred_at')
            ->paginate(20, ['*'], 'movements_page')
            ->withQueryString()
            ->through(fn (BankAccountMovement $movement) => [
                'id' => $movement->id,
                'bank' => $movement->bankAccount?->bank_name,
                'account' => $movement->bankAccount?->account_name,
                'type' => $movement->type,
                'amount' => $movement->amount,
                'reference' => $movement->reference,
                'status' => $movement->status,
                'status_label' => $this->movementStatusLabel($movement->status),
                'occurred_at' => $movement->occurred_at->format('d/m/Y H:i'),
                'description' => $movement->description,
                'reconciled_at' => $movement->reconciled_at?->format('d/m/Y H:i'),
                'reconciled_by' => $movement->reconciledBy?->name,
                'reconciliation_notes' => $movement->reconciliation_notes,
            ]);

        return Inertia::render('Settings/Banks/Index', [
            'items' => $accounts,
            'movements' => $movements,
            'filters' => $filters,
            'stats' => [
                'accounts' => BankAccount::query()->count(),
                'active' => BankAccount::query()->where('is_active', true)->count(),
                'balance' => (int) BankAccountMovement::query()
                    ->selectRaw("COALESCE(SUM(CASE WHEN type = 'inflow' THEN amount ELSE -amount END), 0) as total")
                    ->value('total'),
                'pending' => (int) BankAccountMovement::query()->where('status', 'pending')->sum('amount'),
                'difference' => (int) BankAccountMovement::query()->where('status', 'difference')->sum('amount'),
            ],
            'movementStatuses' => $this->movementStatuses(),
            'tenant' => [
                'id' => $currentTenant->id(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->clearDefaultWhenNeeded($data);

        BankAccount::create($data);

        return back()->with('success', 'Cuenta bancaria creada.');
    }

    public function update(BankAccount $bankAccount, Request $request): RedirectResponse
    {
        $data = $this->validated($request, $bankAccount);
        $this->clearDefaultWhenNeeded($data, $bankAccount);

        $bankAccount->update($data);

        return back()->with('success', 'Cuenta bancaria actualizada.');
    }

    public function toggle(BankAccount $bankAccount): RedirectResponse
    {
        $bankAccount->update(['is_active' => ! $bankAccount->is_active]);

        return back()->with('success', 'Estado de la cuenta actualizado.');
    }

    public function reconcileMovement(BankAccountMovement $movement, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'confirmed', 'difference'])],
            'reconciliation_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $movement->update([
            'status' => $data['status'],
            'reconciliation_notes' => $data['reconciliation_notes'] ?? null,
            'reconciled_at' => $data['status'] === 'pending' ? null : now(),
            'reconciled_by_user_id' => $data['status'] === 'pending' ? null : $request->user()->id,
        ]);

        return back()->with('success', 'Movimiento bancario actualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?BankAccount $account = null): array
    {
        return $request->validate([
            'bank_name' => ['required', 'string', 'max:120'],
            'account_name' => ['required', 'string', 'max:160'],
            'account_number' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('bank_accounts', 'account_number')->where('tenant_id', $request->user()->tenant_id)->ignore($account?->id),
            ],
            'type' => ['required', 'string', Rule::in(['savings', 'checking', 'wallet'])],
            'is_default' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function clearDefaultWhenNeeded(array $data, ?BankAccount $except = null): void
    {
        if (($data['is_default'] ?? false) !== true) {
            return;
        }

        BankAccount::query()
            ->when($except, fn (Builder $query) => $query->whereKeyNot($except->id))
            ->update(['is_default' => false]);
    }

    private function isValidMovementStatus(string $status): bool
    {
        return in_array($status, ['pending', 'confirmed', 'difference'], true);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function movementStatuses(): array
    {
        return collect(['pending', 'confirmed', 'difference'])
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => $this->movementStatusLabel($status),
            ])
            ->all();
    }

    private function movementStatusLabel(string $status): string
    {
        return match ($status) {
            'confirmed' => 'Conciliado',
            'difference' => 'Con diferencia',
            default => 'Pendiente',
        };
    }
}
