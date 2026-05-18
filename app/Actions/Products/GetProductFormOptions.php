<?php

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Models\ActiveIngredient;
use App\Models\Laboratory;
use App\Models\ProductCategory;
use App\Models\ProductUnit;

class GetProductFormOptions
{
    /**
     * @return array{
     *     laboratories: array<int, array{id: int, name: string}>,
     *     categories: array<int, array{id: int, name: string}>,
     *     activeIngredients: array<int, array{id: int, name: string}>,
     *     units: array<int, array{id: int, name: string, code: string}>,
     *     statuses: array<int, array{value: string, label: string}>
     * }
     */
    public function execute(): array
    {
        return [
            'laboratories' => Laboratory::query()
                ->select(['id', 'name'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Laboratory $laboratory) => [
                    'id' => $laboratory->id,
                    'name' => $laboratory->name,
                ])
                ->all(),
            'categories' => ProductCategory::query()
                ->select(['id', 'name'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (ProductCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->all(),
            'activeIngredients' => ActiveIngredient::query()
                ->select(['id', 'dci_name'])
                ->orderBy('dci_name')
                ->get()
                ->map(fn (ActiveIngredient $ingredient) => [
                    'id' => $ingredient->id,
                    'name' => $ingredient->dci_name,
                ])
                ->all(),
            'units' => ProductUnit::query()
                ->select(['id', 'name', 'code'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (ProductUnit $unit) => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'code' => $unit->code,
                ])
                ->all(),
            'statuses' => collect(ProductStatus::cases())
                ->map(fn (ProductStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->all(),
        ];
    }
}
