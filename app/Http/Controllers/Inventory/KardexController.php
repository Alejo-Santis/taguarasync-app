<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KardexController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'type' => $request->string('type')->toString(),
            'from' => $request->string('from')->toString() ?: today()->startOfMonth()->toDateString(),
            'to' => $request->string('to')->toString() ?: today()->toDateString(),
        ];

        $baseQuery = InventoryMovement::query()
            ->with([
                'lot:id,lot_number,expires_on',
                'product:id,commercial_name,internal_code',
                'presentation:id,name',
                'user:id,name',
            ])
            ->whereBetween('occurred_at', [
                "{$filters['from']} 00:00:00",
                "{$filters['to']} 23:59:59",
            ])
            ->when(InventoryMovementType::tryFrom($filters['type']) !== null, fn (Builder $query) => $query->where('type', $filters['type']))
            ->when($filters['q'] !== '', fn (Builder $query) => $this->applySearch($query, $filters['q']));

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
        ]);
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
