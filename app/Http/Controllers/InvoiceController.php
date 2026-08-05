<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use App\Support\InvoicePdfData;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices
     */
    public function index(Request $request)
    {
        $brandId = $request->query('brand');
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;

        $invoices = Invoice::with([
            'user:id,customer_name,email,phone',
            'brand:id,name,slug',
            'order:id,invoice_id,status,payment_status',
        ])
            ->where('status', 'paid')
            ->where(function ($query) {
                $query->whereDoesntHave('order')
                    ->orWhereHas('order', fn ($order) => $order
                        ->where('status', 'paid')
                        ->where('payment_status', 'paid'));
            })
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($user) use ($search) {
                            $user->where('customer_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $brands = Brand::query()
            ->withCount([
                'invoices' => fn ($query) => $query->where('status', 'paid'),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'brands' => $brands,
            'selectedBrandId' => $brandId ? (int) $brandId : null,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the specified invoice
     */
    public function show(Invoice $invoice)
    {
        $invoice->load(['user', 'rental.product', 'order']);
        abort_unless($this->isFinalInvoice($invoice), 404);

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Generate PDF for the specified invoice
     */
    public function generatePdf(Invoice $invoice, InvoicePdfService $pdfService)
    {
        $invoice->loadMissing('order');
        abort_unless($this->isFinalInvoice($invoice), 404);

        $data = InvoicePdfData::fromInvoice($invoice);
        $content = $pdfService->render($data);
        $filename = 'invoice-'.$invoice->invoice_number.'.pdf';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /**
     * Update invoice status
     */
    public function updateStatus(Request $request, Invoice $invoice)
    {
        abort(403, 'حالة الفاتورة النهائية تُحدّث تلقائياً بعد اكتمال السداد.');
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
        $query = Invoice::with(['user'])->where('status', 'paid');

        if ($request->has('brand') && $request->brand !== 'all') {
            $query->where('brand_id', $request->brand);
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
                'Created Date'
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
                    $invoice->created_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function isFinalInvoice(Invoice $invoice): bool
    {
        if ($invoice->status !== 'paid') {
            return false;
        }

        return ! $invoice->order
            || (
                $invoice->order->status === 'paid'
                && $invoice->order->payment_status === 'paid'
            );
    }
}
