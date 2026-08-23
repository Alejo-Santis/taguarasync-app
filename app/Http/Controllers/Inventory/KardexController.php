<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InventoryMovement;
use App\Models\Laboratory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KardexController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $baseQuery = $this->filteredQuery($filters);

        $movementIds = (clone $baseQuery)->select('inventory_movements.id');

        $movements = $baseQuery
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (InventoryMovement $movement) => $this->transformMovement($movement));

        return Inertia::render('Inventory/Kardex', [
            'filters' => $filters,
            'movements' => $movements,
            'summary' => [
                'movements_count' => (clone $movementIds)->count(),
                'entries' => (int) InventoryMovement::query()
                    ->whereIn('id', (clone $movementIds))
                    ->where('quantity_delta', '>', 0)
                    ->sum('quantity_delta'),
                'exits' => abs((int) InventoryMovement::query()
                    ->whereIn('id', (clone $movementIds))
                    ->where('quantity_delta', '<', 0)
                    ->sum('quantity_delta')),
                'net' => (int) InventoryMovement::query()
                    ->whereIn('id', (clone $movementIds))
                    ->sum('quantity_delta'),
            ],
            'types' => collect(InventoryMovementType::cases())
                ->map(fn (InventoryMovementType $type) => [
                    'value' => $type->value,
                    'label' => $this->typeLabel($type),
                ])
                ->all(),
            'laboratories' => Laboratory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Laboratory $laboratory) => ['id' => $laboratory->id, 'name' => $laboratory->name])
                ->all(),
            'branches' => Branch::where('is_active', true)
                ->orderByDesc('is_main')->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch) => ['id' => $branch->id, 'name' => $branch->name])
                ->all(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);

        // chunkById requires ordering strictly by its own id column, so no
        // additional orderBy is applied here — row order isn't significant
        // for a CSV export the way it is for the paginated UI.
        $movements = $this->filteredQuery($filters);

        $filename = 'kardex-'.$filters['from'].'-'.$filters['to'].'.csv';

        return response()->streamDownload(function () use ($movements): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Fecha', 'Producto', 'Codigo interno', 'Laboratorio', 'Presentacion', 'Lote', 'Vencimiento',
                'Movimiento', 'Cantidad antes', 'Cantidad movimiento', 'Cantidad despues',
                'Costo unitario', 'Valor movimiento', 'Referencia', 'Notas', 'Usuario', 'Sucursal',
            ]);

            $movements->chunkById(500, function ($chunk) use ($handle): void {
                foreach ($chunk as $movement) {
                    fputcsv($handle, [
                        $movement->occurred_at->format('Y-m-d H:i'),
                        $movement->product?->commercial_name,
                        $movement->product?->internal_code,
                        $movement->product?->laboratory?->name,
                        $movement->presentation?->name,
                        $movement->lot?->lot_number,
                        $movement->lot?->expires_on?->format('Y-m-d') ?? '',
                        $this->typeLabel($movement->type),
                        $movement->quantity_before,
                        $movement->quantity_delta,
                        $movement->quantity_after,
                        $movement->unit_cost,
                        abs($movement->quantity_delta) * $movement->unit_cost,
                        $movement->reference_code,
                        $movement->notes,
                        $movement->user?->name,
                        $movement->branch?->name,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{q: string, type: string, from: string, to: string, laboratory_id: string, branch_id: string}
     */
    private function filters(Request $request): array
    {
        return [
            'q' => $request->string('q')->trim()->toString(),
            'type' => $request->string('type')->toString(),
            'from' => $request->string('from')->toString() ?: today()->startOfMonth()->toDateString(),
            'to' => $request->string('to')->toString() ?: today()->toDateString(),
            'laboratory_id' => $request->string('laboratory_id')->toString(),
            'branch_id' => $request->string('branch_id')->toString(),
        ];
    }

    /**
     * @param  array{q: string, type: string, from: string, to: string, laboratory_id: string, branch_id: string}  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return InventoryMovement::query()
            ->with([
                'lot:id,lot_number,expires_on',
                'product:id,laboratory_id,commercial_name,internal_code',
                'product.laboratory:id,name',
                'presentation:id,name',
                'user:id,name',
                'branch:id,name',
            ])
            ->whereBetween('occurred_at', [
                "{$filters['from']} 00:00:00",
                "{$filters['to']} 23:59:59",
            ])
            ->when(InventoryMovementType::tryFrom($filters['type']) !== null, fn (Builder $query) => $query->where('type', $filters['type']))
            ->when($filters['q'] !== '', fn (Builder $query) => $this->applySearch($query, $filters['q']))
            ->when($filters['laboratory_id'] !== '', fn (Builder $query) => $query->whereHas(
                'product',
                fn (Builder $query) => $query->where('laboratory_id', $filters['laboratory_id'])
            ))
            ->when($filters['branch_id'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch_id']));
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql'
            ? 'ilike'
            : 'like';

        return $query->where(function (Builder $query) use ($operator, $search): void {
            $query
                ->where('reference_code', $operator, "%{$search}%")
                ->orWhere('notes', $operator, "%{$search}%")
                ->orWhereHas('lot', fn (Builder $query) => $query->where('lot_number', $operator, "%{$search}%"))
                ->orWhereHas('product', function (Builder $query) use ($operator, $search): void {
                    $query
                        ->where('commercial_name', $operator, "%{$search}%")
                        ->orWhere('internal_code', $operator, "%{$search}%");
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function transformMovement(InventoryMovement $movement): array
    {
        return [
            'uuid' => $movement->uuid,
            'occurred_at' => $movement->occurred_at->format('d/m/Y H:i'),
            'type' => [
                'value' => $movement->type->value,
                'label' => $this->typeLabel($movement->type),
            ],
            'product' => [
                'name' => $movement->product?->commercial_name,
                'internal_code' => $movement->product?->internal_code,
            ],
            'presentation' => $movement->presentation?->name,
            'lot' => [
                'number' => $movement->lot?->lot_number,
                'expires_on' => $movement->lot?->expires_on?->format('d/m/Y') ?? 'Sin vencimiento',
            ],
            'user' => $movement->user?->name,
            'quantity_delta' => $movement->quantity_delta,
            'quantity_before' => $movement->quantity_before,
            'quantity_after' => $movement->quantity_after,
            'unit_cost' => $movement->unit_cost,
            'movement_value' => abs($movement->quantity_delta) * $movement->unit_cost,
            'reference_code' => $movement->reference_code,
            'notes' => $movement->notes,
        ];
    }

    private function typeLabel(InventoryMovementType $type): string
    {
        return match ($type) {
            InventoryMovementType::Opening => 'Apertura',
            InventoryMovementType::Purchase => 'Compra',
            InventoryMovementType::Sale => 'Venta',
            InventoryMovementType::SaleReturn => 'Devolucion venta',
            InventoryMovementType::PurchaseReturn => 'Devolucion compra',
            InventoryMovementType::AdjustmentIn => 'Ajuste entrada',
            InventoryMovementType::AdjustmentOut => 'Ajuste salida',
            InventoryMovementType::TransferIn => 'Traslado entrada',
            InventoryMovementType::TransferOut => 'Traslado salida',
        };
    }
}
