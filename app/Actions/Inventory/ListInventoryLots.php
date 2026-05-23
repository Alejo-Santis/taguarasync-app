<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryLotStatus;
use App\Models\InventoryLot;
use App\Models\Laboratory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ListInventoryLots
{
    /**
     * @return array{
     *     lots: LengthAwarePaginator<int, array<string, mixed>>,
     *     filters: array{q: string, status: string, expiry: string},
     *     stats: array{lots: int, units: int, expiring: int, depleted: int},
     *     statuses: array<int, array{value: string, label: string}>,
     *     laboratories: array<int, array{id: int, name: string}>
     * }
     */
    public function execute(Request $request): array
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'status' => $request->string('status')->toString(),
            'expiry' => $request->string('expiry')->toString(),
        ];

        $lots = InventoryLot::query()
            ->select([
                'id',
                'uuid',
                'product_id',
                'product_presentation_id',
                'lot_number',
                'expires_on',
                'initial_quantity',
                'current_quantity',
                'unit_cost',
                'status',
                'received_at',
            ])
            ->with([
                'product:id,commercial_name,generic_name,pharmaceutical_form,concentration,internal_code',
                'presentation:id,name,minimum_unit_quantity',
            ])
            ->when($filters['q'] !== '', fn (Builder $query) => $this->applySearch($query, $filters['q']))
            ->when($this->isValidStatus($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['expiry'] === 'expired', fn (Builder $query) => $query->whereDate('expires_on', '<', today()))
            ->when($filters['expiry'] === 'soon', fn (Builder $query) => $query->whereBetween('expires_on', [today(), today()->addDays(90)]))
            ->when($filters['expiry'] === 'none', fn (Builder $query) => $query->whereNull('expires_on'))
            ->orderByRaw('expires_on is null')
            ->orderBy('expires_on')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (InventoryLot $lot) => $this->transformLot($lot));

        return [
            'lots' => $lots,
            'filters' => $filters,
            'stats' => $this->stats(),
            'statuses' => $this->statuses(),
            'laboratories' => Laboratory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Laboratory $laboratory) => [
                    'id' => $laboratory->id,
                    'name' => $laboratory->name,
                ])
                ->all(),
        ];
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql'
            ? 'ilike'
            : 'like';

        return $query->where(function (Builder $query) use ($operator, $search): void {
            $query
                ->where('lot_number', $operator, "%{$search}%")
                ->orWhereHas('product', function (Builder $query) use ($operator, $search): void {
                    $query
                        ->where('commercial_name', $operator, "%{$search}%")
                        ->orWhere('generic_name', $operator, "%{$search}%")
                        ->orWhere('internal_code', $operator, "%{$search}%");
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function transformLot(InventoryLot $lot): array
    {
        return [
            'id' => $lot->id,
            'uuid' => $lot->uuid,
            'lot_number' => $lot->lot_number,
            'expires_on' => $lot->expires_on?->toDateString(),
            'expires_label' => $lot->expires_on?->format('d/m/Y') ?? 'Sin vencimiento',
            'days_to_expiry' => $lot->expires_on ? today()->diffInDays($lot->expires_on, false) : null,
            'initial_quantity' => $lot->initial_quantity,
            'current_quantity' => $lot->current_quantity,
            'unit_cost' => $lot->unit_cost,
            'inventory_value' => $lot->current_quantity * $lot->unit_cost,
            'status' => [
                'value' => $lot->status->value,
                'label' => $lot->status->label(),
            ],
            'received_at' => $lot->received_at?->format('d/m/Y H:i'),
            'product' => [
                'name' => $lot->product?->commercial_name,
                'generic_name' => $lot->product?->generic_name,
                'form' => $lot->product?->pharmaceutical_form,
                'concentration' => $lot->product?->concentration,
                'internal_code' => $lot->product?->internal_code,
            ],
            'presentation' => $lot->presentation ? [
                'name' => $lot->presentation->name,
                'minimum_unit_quantity' => $lot->presentation->minimum_unit_quantity,
            ] : null,
        ];
    }

    /**
     * @return array{lots: int, units: int, expiring: int, depleted: int}
     */
    private function stats(): array
    {
        return [
            'lots' => InventoryLot::count(),
            'units' => InventoryLot::sum('current_quantity'),
            'expiring' => InventoryLot::whereBetween('expires_on', [today(), today()->addDays(90)])->count(),
            'depleted' => InventoryLot::where('status', InventoryLotStatus::Depleted)->count(),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return collect(InventoryLotStatus::cases())
            ->map(fn (InventoryLotStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->all();
    }

    private function isValidStatus(string $status): bool
    {
        return InventoryLotStatus::tryFrom($status) !== null;
    }
}
