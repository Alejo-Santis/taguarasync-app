<?php

namespace App\Http\Controllers;

use App\Models\FeSubmission;
use App\Models\InventoryMovement;
use App\Models\PurchaseReceipt;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): Response
    {
        $tenantId = $currentTenant->id();
        $tab = $request->string('tab', 'fe')->toString();

        return Inertia::render('Audit/Index', [
            'active_tab' => $tab,
            'is_super_admin' => $tenantId === null,
            'fe' => Inertia::defer(fn () => $this->feSubmissions($tenantId)),
            'radian' => Inertia::defer(fn () => $this->radianHistory($tenantId)),
            'movements' => Inertia::defer(fn () => $this->inventoryMovements($tenantId)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function feSubmissions(?int $tenantId): array
    {
        $query = FeSubmission::withoutGlobalScopes();

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->join('tenants', 'fe_submissions.tenant_id', '=', 'tenants.id')
                ->select('fe_submissions.*', 'tenants.name as tenant_name');
        }

        $rows = $query->latest('fe_submissions.created_at')->limit(100)->get()
            ->map(fn (FeSubmission $s) => [
                'id' => $s->id,
                'document_type' => $s->document_type,
                'document_id' => $s->document_id,
                'xml_document_key' => $s->xml_document_key,
                'response_status' => $s->response_status,
                'attempts' => $s->attempts,
                'is_non_recoverable' => $s->is_non_recoverable,
                'error' => $s->response_payload['error'] ?? null,
                'submitted_at' => $s->submitted_at?->format('d/m/Y H:i:s'),
                'responded_at' => $s->responded_at?->format('d/m/Y H:i:s'),
                'tenant' => $s->tenant_name ?? null,
            ]);

        $statsBase = FeSubmission::withoutGlobalScopes();
        if ($tenantId !== null) {
            $statsBase->where('tenant_id', $tenantId);
        }

        return [
            'rows' => $rows,
            'stats' => [
                'total' => $statsBase->count(),
                'errors' => FeSubmission::withoutGlobalScopes()
                    ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->where('is_non_recoverable', true)
                    ->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function radianHistory(?int $tenantId): array
    {
        $query = PurchaseReceipt::withoutGlobalScopes()
            ->with('supplier:id,name')
            ->whereNotNull('radian_checked_at');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->join('tenants', 'purchase_receipts.tenant_id', '=', 'tenants.id')
                ->select('purchase_receipts.*', 'tenants.name as tenant_name');
        }

        $rows = $query->latest('purchase_receipts.radian_checked_at')->limit(100)->get()
            ->map(fn (PurchaseReceipt $r) => [
                'uuid' => $r->uuid,
                'document_number' => $r->document_number,
                'supplier' => $r->supplier?->name,
                'supplier_cufe' => $r->supplier_cufe,
                'radian_status' => $r->radian_status?->value,
                'radian_status_label' => $r->radian_status?->label(),
                'radian_checked_at' => $r->radian_checked_at?->format('d/m/Y H:i:s'),
                'radian_error_message' => $r->radian_error_message,
                'tenant' => $r->tenant_name ?? null,
            ]);

        return ['rows' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryMovements(?int $tenantId): array
    {
        $query = InventoryMovement::withoutGlobalScopes()
            ->with(['product:id,commercial_name', 'user:id,name']);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->join('tenants', 'inventory_movements.tenant_id', '=', 'tenants.id')
                ->select('inventory_movements.*', 'tenants.name as tenant_name');
        }

        $rows = $query->latest('inventory_movements.occurred_at')->limit(200)->get()
            ->map(fn (InventoryMovement $m) => [
                'uuid' => $m->uuid,
                'type' => $m->type,
                'product' => $m->product?->commercial_name,
                'quantity_delta' => $m->quantity_delta,
                'quantity_before' => $m->quantity_before,
                'quantity_after' => $m->quantity_after,
                'unit_cost' => $m->unit_cost,
                'reference_code' => $m->reference_code,
                'notes' => $m->notes,
                'user' => $m->user?->name,
                'occurred_at' => $m->occurred_at?->format('d/m/Y H:i:s'),
                'tenant' => $m->tenant_name ?? null,
            ]);

        return ['rows' => $rows];
    }
}
