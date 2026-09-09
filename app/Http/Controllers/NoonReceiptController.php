<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\User;
use App\Services\OrderPaymentReceiptService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class NoonReceiptController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;
        $page = max(1, (int) $request->query('page', 1));

        $rows = $this->successfulNoonRows($search);
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

    public function pdf(Request $request, OrderPaymentReceipt $receipt): Response
    {
        return $this->receiptPdfResponse($receipt, $request->boolean('download'));
    }

    public function orderPdf(Request $request, Order $order): Response
    {
        $receipt = $this->resolveNoonReceiptForOrder($order, $request->user());

        abort_unless($receipt !== null, 404, 'لا يوجد إيصال نون ناجح لهذا الطلب.');

        return $this->receiptPdfResponse($receipt, $request->boolean('download'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function successfulNoonRows(string $search): Collection
    {
        $receipts = OrderPaymentReceipt::query()
            ->successfulNoon()
            ->with([
                'order:id,order_number,customer_name,customer_phone,customer_email,currency,payment_id,payment_status,payment_method',
            ])
            ->latest('id')
            ->get();

        $rows = $receipts->map(fn (OrderPaymentReceipt $receipt) => $this->serializeReceipt($receipt));

        $orderIdsWithReceipts = $receipts->pluck('order_id')->filter()->unique()->all();

        $orphanOrders = Order::query()
            ->where('payment_method', 'noon')
            ->where('payment_status', 'paid')
            ->when($orderIdsWithReceipts !== [], fn ($query) => $query->whereNotIn('id', $orderIdsWithReceipts))
            ->latest('id')
            ->get([
                'id',
                'order_number',
                'customer_name',
                'customer_phone',
                'customer_email',
                'currency',
                'payment_id',
                'amount_paid',
                'total_amount',
                'created_at',
                'updated_at',
            ]);

        $rows = $rows->concat($orphanOrders->map(fn (Order $order) => $this->serializeOrder($order)));

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

        return $rows
            ->sortByDesc(fn (array $row) => $row['sort_at'] ?? '')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReceipt(OrderPaymentReceipt $receipt): array
    {
        $order = $receipt->order;
        $paidAt = $receipt->approved_at ?? $receipt->created_at;

        return [
            'key' => 'receipt-'.$receipt->id,
            'receipt_id' => $receipt->id,
            'order_id' => $order?->id,
            'customer_name' => $order?->customer_name ?: '—',
            'customer_phone' => $order?->customer_phone,
            'customer_email' => $order?->customer_email,
            'order_number' => $order?->order_number,
            'receipt_number' => $receipt->receipt_number,
            'amount' => (float) $receipt->amount,
            'currency' => $order?->currency ?: 'SAR',
            'noon_order_id' => $order?->payment_id ?: $this->noonIdFromNotes($receipt->notes),
            'paid_at' => $paidAt?->toIso8601String(),
            'sort_at' => $paidAt?->toIso8601String(),
            'pdf_url' => route('noon-receipts.pdf', $receipt),
            'download_url' => route('noon-receipts.pdf', ['receipt' => $receipt, 'download' => 1]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order): array
    {
        $paidAt = $order->updated_at ?? $order->created_at;
        $amount = round((float) ($order->amount_paid ?: $order->total_amount), 2);

        return [
            'key' => 'order-'.$order->id,
            'receipt_id' => null,
            'order_id' => $order->id,
            'customer_name' => $order->customer_name ?: '—',
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'order_number' => $order->order_number,
            'receipt_number' => null,
            'amount' => $amount,
            'currency' => $order->currency ?: 'SAR',
            'noon_order_id' => $order->payment_id,
            'paid_at' => $paidAt?->toIso8601String(),
            'sort_at' => $paidAt?->toIso8601String(),
            'pdf_url' => route('noon-receipts.order-pdf', $order),
            'download_url' => route('noon-receipts.order-pdf', ['order' => $order, 'download' => 1]),
        ];
    }

    private function receiptPdfResponse(OrderPaymentReceipt $receipt, bool $download): Response
    {
        abort_unless($this->isSuccessfulNoonReceipt($receipt), 404, 'هذا الإيصال ليس دفعة نون ناجحة.');

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

    private function isSuccessfulNoonReceipt(OrderPaymentReceipt $receipt): bool
    {
        return strtolower((string) $receipt->payment_method) === 'noon'
            && $receipt->isApproved();
    }

    private function resolveNoonReceiptForOrder(Order $order, ?User $user): ?OrderPaymentReceipt
    {
        $existing = $order->paymentReceipts()
            ->successfulNoon()
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        if (strtolower((string) $order->payment_method) !== 'noon' || $order->payment_status !== 'paid') {
            return null;
        }

        $paid = round((float) ($order->amount_paid ?: $order->total_amount ?: 0), 2);
        if ($paid <= 0) {
            return null;
        }

        $total = round((float) $order->total_amount, 2);

        return OrderPaymentReceipt::create([
            'order_id' => $order->id,
            'recorded_by' => $user?->id,
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

    private function noonIdFromNotes(?string $notes): ?string
    {
        if (! is_string($notes) || $notes === '') {
            return null;
        }

        if (preg_match('/\(([^)]+)\)\s*$/', $notes, $matches) === 1) {
            return trim($matches[1]) !== '' ? trim($matches[1]) : null;
        }

        return null;
    }
}
