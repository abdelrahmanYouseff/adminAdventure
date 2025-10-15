<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices
     */
    public function index(Request $request)
    {
        $invoices = Invoice::with(['user'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
        ]);
    }

    /**
     * Show the specified invoice
     */
    public function show(Invoice $invoice)
    {
        $invoice->load(['user', 'rental.product']);

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Generate PDF for the specified invoice
     */
    public function generatePdf(Invoice $invoice)
    {
        $invoice->load(['user', 'rental.product']);

        $pdf = Pdf::loadView('invoice-pdf', compact('invoice'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'dpi' => 150,
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        return $pdf->stream("invoice-{$invoice->invoice_number}.pdf");
    }

    /**
     * Update invoice status
     */
    public function updateStatus(Request $request, Invoice $invoice)
    {
        $request->validate([
            'status' => 'required|in:pending,paid,cancelled,overdue',
        ]);

        $invoice->update([
            'status' => $request->status,
        ]);

        return back()->with('success', 'Invoice status updated successfully.');
    }

    /**
     * Update overdue invoices
     */
    public function updateOverdueInvoices()
    {
        $overdueInvoices = Invoice::where('status', 'pending')
            ->where('due_date', '<', now())
            ->update(['status' => 'overdue']);

        return response()->json([
            'success' => true,
            'message' => "Updated {$overdueInvoices} overdue invoices",
        ]);
    }

    /**
     * Export invoices
     */
    public function export(Request $request)
    {
        $query = Invoice::with(['user']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $invoices = $query->orderBy('created_at', 'desc')->get();

        // Return CSV data
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="invoices.csv"',
        ];

        $callback = function() use ($invoices) {
            $file = fopen('php://output', 'w');

            // Add headers
            fputcsv($file, [
                'Invoice Number',
                'Customer Name',
                'Amount',
                'Currency',
                'Status',
                'Payment Method',
                'Created Date',
                'Due Date'
            ]);

            // Add data
            foreach ($invoices as $invoice) {
                fputcsv($file, [
                    $invoice->invoice_number,
                    $invoice->user->full_name ?? $invoice->user->name,
                    $invoice->amount,
                    'SAR',
                    ucfirst($invoice->status),
                    ucfirst($invoice->payment_method ?? 'N/A'),
                    $invoice->created_at->format('Y-m-d H:i:s'),
                    $invoice->due_date ? $invoice->due_date->format('Y-m-d') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
