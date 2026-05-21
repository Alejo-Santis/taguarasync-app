<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\DianIdentificationType;
use App\Models\DianMunicipality;
use App\Models\DianOrganizationType;
use App\Models\DianRegimeType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $items = Customer::withTrashed()
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $query->where(function ($q) use ($filters) {
                    $q->where('identification_number', 'like', "%{$filters['q']}%")
                        ->orWhere('first_name', 'ilike', "%{$filters['q']}%")
                        ->orWhere('last_name', 'ilike', "%{$filters['q']}%")
                        ->orWhere('business_name', 'ilike', "%{$filters['q']}%");
                });
            })
            ->when($filters['status'] === 'active', fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->whereNull('deleted_at')->where('is_active', false))
            ->orderBy('business_name')
            ->orderBy('last_name')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Customer $c) => [
                'id' => $c->id,
                'uuid' => $c->uuid,
                'identification_type_code' => $c->identification_type_code,
                'identification_number' => $c->identification_number,
                'verification_digit' => $c->verification_digit,
                'full_name' => $c->full_name,
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'business_name' => $c->business_name,
                'organization_type_code' => $c->organization_type_code,
                'regime_type_code' => $c->regime_type_code,
                'email' => $c->email,
                'phone' => $c->phone,
                'address' => $c->address,
                'municipality_code' => $c->municipality_code,
                'is_active' => $c->is_active,
                'deleted_at' => $c->deleted_at,
            ]);

        return Inertia::render('Customers/Index', [
            'items' => $items,
            'filters' => $filters,
            'stats' => [
                'total' => Customer::withoutTrashed()->count(),
                'active' => Customer::withoutTrashed()->where('is_active', true)->count(),
            ],
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        Customer::create($request->validated());

        return back()->with('success', 'Cliente creado correctamente.');
    }

    public function update(Customer $customer, UpdateCustomerRequest $request): RedirectResponse
    {
        $customer->update($request->validated());

        return back()->with('success', 'Cliente actualizado correctamente.');
    }

    public function toggle(Customer $customer): RedirectResponse
    {
        $customer->update(['is_active' => ! $customer->is_active]);

        return back();
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'identification_types' => DianIdentificationType::where('is_active', true)
                ->orderBy('name')->get(['code', 'name']),
            'organization_types' => DianOrganizationType::orderBy('name')->get(['code', 'name']),
            'regime_types' => DianRegimeType::orderBy('name')->get(['code', 'name']),
            'municipalities' => DianMunicipality::orderBy('department_name')->orderBy('name')
                ->get(['code', 'name', 'department_name']),
        ];
    }
}
