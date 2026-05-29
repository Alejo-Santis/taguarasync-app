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

        abort_unless($tenantId !== null, 403);

        $tab = $request->string('tab', 'fe')->toString();
        $page = max(1, (int) $request->integer('page', 1));

        return Inertia::render('Audit/Index', [
            'active_tab' => $tab,
            'fe' => Inertia::defer(fn () => $this->feSubmissions($tenantId)),
            'radian' => Inertia::defer(fn () => $this->radianHistory($tenantId)),
            'movements' => Inertia::defer(fn () => $this->inventoryMovements($tenantId)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function feSubmissions(int $tenantId): array
    {
        $rows = FeSubmission::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest()
            ->limit(100)
            ->get()
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
            ]);

        return [
            'rows' => $rows,
            'stats' => [
                'total' => FeSubmission::withoutGlobalScopes()->where('tenant_id', $tenantId)->count(),
                'errors' => FeSubmission::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_non_recoverable', true)->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function radianHistory(int $tenantId): array
    {
        $rows = PurchaseReceipt::withoutGlobalScopes()
            ->with('supplier:id,name')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('radian_checked_at')
            ->latest('radian_checked_at')
            ->limit(100)
            ->get()
            ->map(fn (PurchaseReceipt $r) => [
                'uuid' => $r->uuid,
                'document_number' => $r->document_number,
                'supplier' => $r->supplier?->name,
                'supplier_cufe' => $r->supplier_cufe,
                'radian_status' => $r->radian_status?->value,
                'radian_status_label' => $r->radian_status?->label(),
                'radian_checked_at' => $r->radian_checked_at?->format('d/m/Y H:i:s'),
                'radian_error_message' => $r->radian_error_message,
            ]);

        return ['rows' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryMovements(int $tenantId): array
    {
        $rows = InventoryMovement::withoutGlobalScopes()
            ->with(['product:id,commercial_name', 'user:id,name'])
            ->where('tenant_id', $tenantId)
            ->latest('occurred_at')
            ->limit(200)
            ->get()
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
            ]);

        return ['rows' => $rows];
    }
}
