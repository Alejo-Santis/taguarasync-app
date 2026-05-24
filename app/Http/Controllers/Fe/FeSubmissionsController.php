<?php

namespace App\Http\Controllers\Fe;

use App\Enums\FeStatus;
use App\Http\Controllers\Controller;
use App\Jobs\EmitElectronicInvoiceJob;
use App\Models\FeSubmission;
use App\Models\Sale;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeSubmissionsController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): Response
    {
        $tenantId = $currentTenant->id();

        $filters = [
            'status' => $request->string('status')->toString(),
            'doc_type' => $request->string('doc_type')->toString(),
        ];

        // Estadísticas del panel
        $stats = [
            'total' => FeSubmission::withoutGlobalScopes()->where('tenant_id', $tenantId)->count(),
            'sent' => Sale::where('fe_status', FeStatus::Sent)->count(),
            'accepted' => Sale::where('fe_status', FeStatus::Accepted)->count(),
            'rejected' => Sale::where('fe_status', FeStatus::Rejected)->count(),
            'pending' => Sale::where('fe_status', FeStatus::Pending)->count(),
            'contingency' => Sale::where('fe_status', FeStatus::Contingency)->count(),
            'non_recoverable' => FeSubmission::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_non_recoverable', true)
                ->count(),
        ];

        $submissions = FeSubmission::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->when($filters['status'], fn ($q) => $q->where('response_status', $filters['status']))
            ->when($filters['doc_type'], fn ($q) => $q->where('document_type', $filters['doc_type']))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(function (FeSubmission $s) {
                $sale = $s->document_type === 'invoice'
                    ? Sale::withoutGlobalScopes()->find($s->document_id)
                    : null;

                return [
                    'id' => $s->id,
                    'document_type' => $s->document_type,
                    'document_id' => $s->document_id,
                    'document_number' => $sale?->document_number,
                    'sale_uuid' => $sale?->uuid,
                    'xml_document_key' => $s->xml_document_key,
                    'attempts' => $s->attempts,
                    'response_status' => $s->response_status,
                    'is_non_recoverable' => $s->is_non_recoverable,
                    'error_message' => $s->response_payload['error'] ?? null,
                    'submitted_at' => $s->submitted_at?->format('d/m/Y H:i:s'),
                    'responded_at' => $s->responded_at?->format('d/m/Y H:i:s'),
                    'fe_status' => $sale?->fe_status?->value,
                    'fe_status_label' => $sale?->fe_status?->label(),
                ];
            });

        return Inertia::render('Fe/Submissions/Index', [
            'submissions' => $submissions,
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function retry(int $submissionId, CurrentTenant $currentTenant): RedirectResponse
    {
        $submission = FeSubmission::withoutGlobalScopes()
            ->where('tenant_id', $currentTenant->id())
            ->findOrFail($submissionId);

        if ($submission->is_non_recoverable) {
            return back()->withErrors(['retry' => 'Este envío tiene un error no recuperable. Verifica los datos antes de reintentar.']);
        }

        if ($submission->document_type === 'invoice') {
            $sale = Sale::withoutGlobalScopes()->find($submission->document_id);

            if ($sale) {
                $sale->update(['fe_status' => FeStatus::Pending, 'fe_error_message' => null]);
                EmitElectronicInvoiceJob::dispatch($sale->id, $currentTenant->id());

                return back()->with('success', 'Reintento despachado para la factura '.$sale->document_number.'.');
            }
        }

        return back()->withErrors(['retry' => 'No se pudo procesar el reintento.']);
    }
}
