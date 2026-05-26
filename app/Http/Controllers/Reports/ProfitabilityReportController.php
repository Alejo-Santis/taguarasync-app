<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\BuildProfitabilityReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfitabilityReportController extends Controller
{
    public function index(Request $request, BuildProfitabilityReport $report): Response
    {
        $from = $request->date('from', 'Y-m-d') ?? today()->startOfMonth();
        $to = $request->date('to', 'Y-m-d') ?? today();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $data = $report->execute((int) $request->user()->tenant_id, $from, $to);

        return Inertia::render('Reports/Profitability', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => $data['summary'],
            'rows' => $data['rows'],
        ]);
    }
}
