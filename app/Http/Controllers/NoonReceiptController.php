<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Services\NoonPaymentGateway;
use App\Services\NoonReceiptService;
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

    public function transactionPdf(Request $request, string $noonOrder, NoonReceiptService $receipts): Response
    {
        $result = $receipts->renderPdf($noonOrder);
        abort_unless($result !== null, 404, 'هذه المعاملة غير موجودة على بوابة نون.');

        return $this->pdfResponse($result['content'], $result['filename'], $request->boolean('download'));
    }

    public function pdf(Request $request, OrderPaymentReceipt $receipt, NoonPaymentGateway $gateway, NoonReceiptService $receipts): Response
    {
        $order = $receipt->order;
        abort_unless($order !== null, 404);

        $noonOrderId = $gateway->resolveNoonOrderId($order, $receipt)
            ?: $order->payment_id
            ?: $gateway->noonIdFromNotes($receipt->notes);

        abort_unless(is_string($noonOrderId) && $noonOrderId !== '', 404, 'هذه المعاملة غير موجودة على بوابة نون.');

        return $this->transactionPdf($request, $noonOrderId, $receipts);
    }

    public function orderPdf(Request $request, Order $order, NoonPaymentGateway $gateway, NoonReceiptService $receipts): Response
    {
        $noonOrderId = $gateway->resolveNoonOrderId($order)
            ?: $order->payment_id;

        abort_unless(is_string($noonOrderId) && $noonOrderId !== '', 404, 'هذه المعاملة غير موجودة على بوابة نون.');

        return $this->transactionPdf($request, $noonOrderId, $receipts);
    }

    private function pdfResponse(string $content, string $filename, bool $download): Response
    {
        $disposition = $download ? 'attachment' : 'inline';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
