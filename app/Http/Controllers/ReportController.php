<?php

namespace App\Http\Controllers;

use App\Exports\CommissionsExport;
use App\Models\Invoice;
use App\Services\CommissionReportService;
use App\Services\SalesReportService;
use Illuminate\Http\RedirectResponse;
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

        $payload = $reports->build($month);
        $monthKey = (string) ($payload['filters']['month'] ?? now()->format('Y-m'));

        return Excel::download(
            new CommissionsExport(collect($payload['rows']), (string) ($payload['period']['label'] ?? $monthKey)),
            'commissions-'.$monthKey.'.xlsx',
        );
    }

    public function destroyCommissionInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status === 'paid', 404);

        $invoice->forceFill([
            'excluded_from_commissions' => true,
        ])->save();

        $month = $request->input('month', $request->query('month'));
        $month = is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) ? $month : null;

        return redirect()
            ->route('reports.commissions', array_filter(['month' => $month]))
            ->with('success', 'تم حذف الفاتورة من تقرير العمولات.');
    }
}
