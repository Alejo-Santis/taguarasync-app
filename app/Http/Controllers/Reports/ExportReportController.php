<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\BuildFiscalReport;
use App\Http\Controllers\Controller;
use App\Models\BankAccountMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportReportController extends Controller
{
    public function fiscal(Request $request, BuildFiscalReport $report): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $data = $report->execute((int) $request->user()->tenant_id, $from, $to);

        return response()->streamDownload(function () use ($data): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Fecha', 'Tipo', 'Documento', 'Estado', 'Base', 'IVA', 'Total']);

            foreach ($data['documents'] as $document) {
                fputcsv($handle, [
                    $document['date'],
                    $document['type'],
                    $document['document_number'],
                    $document['fe_status'],
                    $document['subtotal'],
                    $document['tax_total'],
                    $document['total'],
                ]);
            }

            fclose($handle);
        }, "libro-fiscal-{$from->toDateString()}-{$to->toDateString()}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function bankMovements(Request $request): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $tenantId = (int) $request->user()->tenant_id;

        return response()->streamDownload(function () use ($tenantId, $from, $to): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Fecha', 'Banco', 'Cuenta', 'Tipo', 'Referencia', 'Monto', 'Estado', 'Conciliado en', 'Conciliado por']);

            BankAccountMovement::withoutGlobalScopes()
                ->with(['bankAccount:id,bank_name,account_name', 'reconciledBy:id,name'])
                ->where('tenant_id', $tenantId)
                ->whereDate('occurred_at', '>=', $from->toDateString())
                ->whereDate('occurred_at', '<=', $to->toDateString())
                ->orderBy('occurred_at')
                ->chunkById(500, function ($movements) use ($handle): void {
                    foreach ($movements as $movement) {
                        fputcsv($handle, [
                            $movement->occurred_at?->format('Y-m-d H:i'),
                            $movement->bankAccount?->bank_name,
                            $movement->bankAccount?->account_name,
                            $movement->type === 'outflow' ? 'Salida' : 'Entrada',
                            $movement->reference,
                            $movement->amount,
                            $this->movementStatusLabel($movement->status),
                            $movement->reconciled_at?->format('Y-m-d H:i'),
                            $movement->reconciledBy?->name,
                        ]);
                    }
                });

            fclose($handle);
        }, "movimientos-bancarios-{$from->toDateString()}-{$to->toDateString()}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        $from = $request->date('from', 'Y-m-d') ?? today()->startOfMonth();
        $to = $request->date('to', 'Y-m-d') ?? today();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
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
