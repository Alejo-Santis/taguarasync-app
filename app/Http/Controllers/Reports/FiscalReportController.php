<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\BuildFiscalReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FiscalReportController extends Controller
{
    public function index(Request $request, BuildFiscalReport $report): Response
    {
        $from = $request->date('from', 'Y-m-d') ?? today()->startOfMonth();
        $to = $request->date('to', 'Y-m-d') ?? today();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return Inertia::render('Reports/Fiscal', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            ...$report->execute((int) $request->user()->tenant_id, $from, $to),
        ]);
    }
}
