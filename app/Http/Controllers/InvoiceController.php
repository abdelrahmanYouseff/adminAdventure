<?php

namespace App\Http\Controllers;

use App\Exports\InvoicesExport;
use App\Models\Brand;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use App\Support\InvoicePdfData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $invoices = $this->finalInvoicesQuery($request)
            ->with([
                'user:id,customer_name,email,phone',
                'brand:id,name,slug',
                'order:id,invoice_id,status,payment_status,customer_name,customer_email,customer_phone',
            ])
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
        $invoice->load([
            'user',
            'rental.product',
            'order:id,invoice_id,customer_name,customer_email,customer_phone',
        ]);
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
     * Export all matching final invoices (every page) as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $invoices = $this->exportInvoices($request);
        $filename = 'invoices-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($invoices) {
            $file = fopen('php://output', 'w');

            // Excel-friendly UTF-8 BOM so Arabic columns display correctly.
            fwrite($file, "\xEF\xBB\xBF");

            $export = new InvoicesExport($invoices);
            fputcsv($file, $export->headings());

            foreach ($invoices as $invoice) {
                fputcsv($file, $export->map($invoice));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export all matching final invoices (every page) as XLSX.
     */
    public function exportXlsx(Request $request): BinaryFileResponse
    {
        $invoices = $this->exportInvoices($request);
        $filename = 'invoices-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new InvoicesExport($invoices), $filename);
    }

    private function exportInvoices(Request $request)
    {
        return $this->finalInvoicesQuery($request)
            ->with([
                'user:id,customer_name,email',
                'brand:id,name',
                'order:id,invoice_id,customer_name',
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Same final-invoice set as the index listing (unpaginated for export).
     */
    private function finalInvoicesQuery(Request $request)
    {
        $brandId = $request->query('brand');
        if ($brandId === 'all' || $brandId === '' || $brandId === null) {
            $brandId = null;
        }

        $search = trim((string) $request->query('search', ''));

        return Invoice::query()
            ->where('status', 'paid')
            ->where(function ($query) {
                $query->whereDoesntHave('order')
                    ->orWhereHas('order', fn ($order) => $order
                        ->where('status', 'paid')
                        ->where('payment_status', 'paid'));
            })
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date_to))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($user) use ($search) {
                            $user->where('customer_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('order', function ($order) use ($search) {
                            $order->where('customer_name', 'like', "%{$search}%")
                                ->orWhere('customer_email', 'like', "%{$search}%")
                                ->orWhere('customer_phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', "%{$search}%"));
                });
            });
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
