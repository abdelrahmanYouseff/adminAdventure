<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Services\NoonReceiptService;
use App\Services\OrderPaymentReceiptService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class NoonReceiptController extends Controller
{
    public function index(Request $request, NoonReceiptService $receipts): InertiaResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;
        $page = max(1, (int) $request->query('page', 1));

        $rows = $receipts->confirmedRows();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(function (array $row) use ($needle) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $row['customer_name'] ?? '',
                    $row['customer_phone'] ?? '',
                    $row['customer_email'] ?? '',
                    $row['order_number'] ?? '',
                    $row['receipt_number'] ?? '',
                    $row['noon_order_id'] ?? '',
                ])));

                return str_contains($haystack, $needle);
            })->values();
        }

        $totalAmount = round((float) $rows->sum('amount'), 2);
        $total = $rows->count();
        $pageItems = $rows->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return Inertia::render('NoonReceipts/Index', [
            'receipts' => $paginator,
            'stats' => [
                'count' => $total,
                'amount' => $totalAmount,
            ],
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function pdf(Request $request, OrderPaymentReceipt $receipt, NoonReceiptService $receipts): Response
    {
        abort_unless(
            $receipt->order && $receipts->isConfirmed($receipt->order, $receipt),
            404,
            'هذا الإيصال غير موجود على بوابة نون.',
        );

        return $this->receiptPdfResponse($receipt, $request->boolean('download'));
    }

    public function orderPdf(Request $request, Order $order, NoonReceiptService $receipts): Response
    {
        abort_unless(
            $receipts->isConfirmed($order),
            404,
            'هذا الإيصال غير موجود على بوابة نون.',
        );

        $receipt = $order->paymentReceipts()
            ->successfulNoon()
            ->latest('id')
            ->first();

        if (! $receipt) {
            $paid = round((float) ($order->amount_paid ?: $order->total_amount ?: 0), 2);
            abort_unless($paid > 0, 404, 'لا يوجد إيصال نون ناجح لهذا الطلب.');

            $total = round((float) $order->total_amount, 2);
            $receipt = OrderPaymentReceipt::create([
                'order_id' => $order->id,
                'recorded_by' => $request->user()?->id,
                'receipt_number' => OrderPaymentReceipt::generateReceiptNumber(),
                'amount' => $paid,
                'total_amount' => $total,
                'amount_paid_before' => 0,
                'amount_paid_after' => $paid,
                'remaining_after' => round(max(0, $total - $paid), 2),
                'payment_method' => 'noon',
                'type' => 'payment',
                'approval_status' => OrderPaymentReceipt::STATUS_APPROVED,
                'approved_at' => now(),
                'notes' => 'دفع إلكتروني عبر Noon'.($order->payment_id ? ' ('.$order->payment_id.')' : ''),
            ]);
        }

        return $this->receiptPdfResponse($receipt, $request->boolean('download'));
    }

    private function receiptPdfResponse(OrderPaymentReceipt $receipt, bool $download): Response
    {
        $pdf = app(OrderPaymentReceiptService::class)->renderPdf($receipt);
        $filename = ($receipt->receipt_number ?: 'noon-receipt').'.pdf';
        $disposition = $download ? 'attachment' : 'inline';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
