<?php

namespace App\Http\Controllers;

use App\Services\SalesReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request, SalesReportService $reports): Response
    {
        $preset = (string) $request->query('preset', 'month');
        $month = $request->query('month');
        $month = is_string($month) ? $month : null;

        return Inertia::render('Reports/Index', $reports->build($preset, $month));
    }
}
