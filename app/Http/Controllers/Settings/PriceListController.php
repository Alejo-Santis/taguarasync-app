<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PriceListController extends Controller
{
    public function index(Request $request): Response
    {
        $lists = PriceList::query()
            ->withCount('items')
            ->withCount('customers')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (PriceList $list) => [
                'id' => $list->id,
                'name' => $list->name,
                'description' => $list->description,
                'is_default' => $list->is_default,
                'is_active' => $list->is_active,
                'items_count' => $list->items_count,
                'customers_count' => $list->customers_count,
            ]);

        return Inertia::render('Settings/PriceLists/Index', [
            'lists' => $lists,
            'filters' => $request->only(['q']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('price_lists')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ]);

        if ($validated['is_default'] ?? false) {
            PriceList::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        PriceList::create([...$validated, 'tenant_id' => $tenantId]);

        return back()->with('success', "Lista de precio '{$validated['name']}' creada correctamente.");
    }

    public function update(PriceList $priceList, Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('price_lists')->where('tenant_id', $tenantId)->ignore($priceList->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ]);

        if ($validated['is_default'] ?? false) {
            PriceList::where('tenant_id', $tenantId)->where('id', '!=', $priceList->id)->update(['is_default' => false]);
        }

        $priceList->update($validated);

        return back()->with('success', "Lista de precio '{$priceList->name}' actualizada.");
    }

    public function toggle(PriceList $priceList): RedirectResponse
    {
        $priceList->update(['is_active' => ! $priceList->is_active]);

        return back();
    }

    public function show(PriceList $priceList): Response
    {
        $priceList->load('items.product');

        $products = Product::query()
            ->select(['id', 'commercial_name', 'internal_code', 'sale_price'])
            ->where('status', ProductStatus::Active)
            ->orderBy('commercial_name')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->commercial_name,
                'code' => $p->internal_code,
                'base_price' => $p->sale_price,
            ])
            ->all();

        return Inertia::render('Settings/PriceLists/Show', [
            'priceList' => [
                'id' => $priceList->id,
                'name' => $priceList->name,
                'description' => $priceList->description,
                'is_default' => $priceList->is_default,
                'is_active' => $priceList->is_active,
                'items' => $priceList->items->map(fn (PriceListItem $item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->commercial_name,
                    'base_price' => $item->product->sale_price,
                    'sale_price' => $item->sale_price,
                ])->values()->all(),
            ],
            'products' => $products,
        ]);
    }

    public function upsertItem(PriceList $priceList, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'sale_price' => ['required', 'integer', 'min:0'],
        ]);

        PriceListItem::updateOrCreate(
            ['price_list_id' => $priceList->id, 'product_id' => $validated['product_id']],
            ['tenant_id' => $request->user()->tenant_id, 'sale_price' => $validated['sale_price']]
        );

        return back()->with('success', 'Precio actualizado correctamente.');
    }

    public function removeItem(PriceList $priceList, PriceListItem $item): RedirectResponse
    {
        abort_unless($item->price_list_id === $priceList->id, 403);
        $item->delete();

        return back()->with('success', 'Precio eliminado. Se usará el precio base del producto.');
    }
}
