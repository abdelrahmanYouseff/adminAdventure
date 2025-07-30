<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices
     */
    public function index(Request $request)
    {
        $query = Invoice::with(['user']);

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by payment method
        if ($request->has('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        // Search by invoice number or user name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('full_name', 'like', "%{$search}%")
                               ->orWhere('name', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(15);

        // Get statistics
        $stats = [
            'total' => Invoice::count(),
            'paid' => Invoice::where('status', 'paid')->count(),
            'pending' => Invoice::where('status', 'pending')->count(),
            'cancelled' => Invoice::where('status', 'cancelled')->count(),
            'overdue' => Invoice::where('status', 'pending')->where('due_date', '<', now())->count(),
            'total_amount' => Invoice::where('status', 'paid')->sum('amount'),
            'pending_amount' => Invoice::where('status', 'pending')->sum('amount'),
        ];

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'stats' => $stats,
            'filters' => [
                'status' => $request->status ?? 'all',
                'payment_method' => $request->payment_method ?? 'all',
                'search' => $request->search ?? '',
            ],
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
