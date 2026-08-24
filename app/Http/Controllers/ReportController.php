<?php

namespace App\Http\Controllers;

use App\Exports\CommissionsExport;
use App\Services\CommissionReportService;
use App\Services\SalesReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(Request $request, SalesReportService $reports): Response
    {
        $preset = (string) $request->query('preset', 'month');
        $month = $request->query('month');
        $month = is_string($month) ? $month : null;

        return Inertia::render('Reports/Index', $reports->build($preset, $month));
    }

    public function commissions(Request $request, CommissionReportService $reports): Response
    {
        $month = $request->query('month');
        $month = is_string($month) ? $month : null;

        return Inertia::render('Reports/Commissions', $reports->build($month));
    }

    public function exportCommissions(Request $request, CommissionReportService $reports): BinaryFileResponse
    {
        $month = $request->query('month');
        $month = is_string($month) ? $month : null;

        [$start, $end, $monthKey] = $reports->resolveMonth($month);
        $rows = $reports->rowsForMonth($start, $end);

        return Excel::download(
            new CommissionsExport($rows),
            'commissions-'.$monthKey.'.xlsx',
        );
    }
}
