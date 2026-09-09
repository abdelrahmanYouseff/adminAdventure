<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\PaymentSession;
use App\Models\Quotation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class NoonReceiptService
{
    public function __construct(
        private NoonPaymentGateway $gateway,
        private OrderPaymentReceiptService $receipts,
    ) {}

    /**
     * Successful Noon transactions, matched to local customers.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function confirmedRows(): Collection
    {
        $sessions = PaymentSession::query()->latest('id')->get();
        $noonIds = $sessions->pluck('noon_order_id')->all();
        $references = $sessions->pluck('merchant_reference')->all();

        $ordersWithPaymentId = Order::query()
            ->whereNotNull('payment_id')
            ->where('payment_id', '!=', '')
            ->get(['id', 'order_number', 'payment_id', 'payment_order_reference', 'customer_name', 'customer_phone', 'customer_email', 'currency', 'amount_paid', 'total_amount', 'activity_date', 'address']);

        $noonIds = array_merge($noonIds, $ordersWithPaymentId->pluck('payment_id')->all());
        $references = array_merge(
            $references,
            $ordersWithPaymentId->pluck('order_number')->all(),
            $ordersWithPaymentId->pluck('payment_order_reference')->all(),
        );

        $noonOrders = $this->gateway->fetchSuccessfulOrders($noonIds, $references);

        if ($noonOrders === []) {
            return collect();
        }

        $referencesFromNoon = array_values(array_filter(array_column($noonOrders, 'reference')));
        $noonIdsFromNoon = array_keys($noonOrders);

        $ordersByNumber = Order::query()
            ->where(function ($query) use ($referencesFromNoon, $noonIdsFromNoon) {
                if ($noonIdsFromNoon !== []) {
                    $query->orWhereIn('payment_id', $noonIdsFromNoon);
                }
                if ($referencesFromNoon !== []) {
                    $query->orWhereIn('order_number', $referencesFromNoon)
                        ->orWhereIn('payment_order_reference', $referencesFromNoon);
                }
            })
            ->get();

        $ordersByPaymentId = $ordersByNumber->keyBy(fn (Order $order) => (string) $order->payment_id);
        $ordersByNumberKeyed = $ordersByNumber->keyBy(fn (Order $order) => (string) $order->order_number);
        $ordersByReference = $ordersByNumber->keyBy(fn (Order $order) => (string) $order->payment_order_reference);

        $sessionsByNoonId = $sessions
            ->filter(fn (PaymentSession $session) => filled($session->noon_order_id))
            ->keyBy(fn (PaymentSession $session) => (string) $session->noon_order_id);
        $sessionsByReference = $sessions->keyBy(fn (PaymentSession $session) => (string) $session->merchant_reference);

        $orderIds = $ordersByNumber->pluck('id')->filter()->all();
        $receipts = $orderIds === []
            ? collect()
            : OrderPaymentReceipt::query()
                ->successfulNoon()
                ->whereIn('order_id', $orderIds)
                ->get()
                ->groupBy('order_id');

        return collect($noonOrders)
            ->map(function (array $noonOrder) use ($ordersByPaymentId, $ordersByNumberKeyed, $ordersByReference, $sessionsByNoonId, $sessionsByReference, $receipts) {
                $noonId = (string) $noonOrder['id'];
                $reference = (string) ($noonOrder['reference'] ?? '');
                $session = $sessionsByNoonId->get($noonId) ?? $sessionsByReference->get($reference);
                $order = $ordersByPaymentId->get($noonId)
                    ?? ($reference !== '' ? $ordersByNumberKeyed->get($reference) : null)
                    ?? ($reference !== '' ? $ordersByReference->get($reference) : null)
                    ?? $this->orderFromSession($session);

                $receipt = $order
                    ? $receipts->get($order->id)?->first()
                    : null;

                $customer = $this->customerFrom($order, $session, $noonOrder);

                $paidAt = $noonOrder['created_at'] ?? $session?->used_at?->toIso8601String() ?? $session?->created_at?->toIso8601String();

                return [
                    'key' => 'noon-'.$noonId,
                    'receipt_id' => $receipt?->id,
                    'order_id' => $order?->id,
                    'customer_name' => $customer['name'],
                    'customer_phone' => $customer['phone'],
                    'customer_email' => $customer['email'],
                    'order_number' => $order?->order_number ?: ($reference !== '' ? $reference : null),
                    'receipt_number' => $receipt?->receipt_number,
                    'amount' => (float) ($noonOrder['amount'] ?? 0),
                    'currency' => $noonOrder['currency'] ?? 'SAR',
                    'noon_order_id' => $noonId,
                    'paid_at' => is_string($paidAt) ? $paidAt : $paidAt?->toIso8601String(),
                    'sort_at' => is_string($paidAt) ? $paidAt : ($paidAt?->toIso8601String() ?? ''),
                    'pdf_url' => route('noon-receipts.transaction-pdf', $noonId),
                    'download_url' => route('noon-receipts.transaction-pdf', ['noonOrder' => $noonId, 'download' => 1]),
                ];
            })
            ->sortByDesc(fn (array $row) => $row['sort_at'] ?? '')
            ->values();
    }

    public function renderPdf(string $noonOrderId): ?array
    {
        $noonOrder = $this->gateway->fetchSuccessfulOrder($noonOrderId);
        if (! $noonOrder) {
            return null;
        }

        $match = $this->confirmedRows()->firstWhere('noon_order_id', $noonOrderId);
        $order = isset($match['order_id'])
            ? Order::query()->with(['products', 'paymentReceipts'])->find($match['order_id'])
            : null;

        if ($order) {
            $receipt = $order->paymentReceipts
                ->where('payment_method', 'noon')
                ->where('approval_status', OrderPaymentReceipt::STATUS_APPROVED)
                ->sortByDesc('id')
                ->first();

            if ($receipt) {
                $pdf = $this->receipts->renderPdf($receipt);
                $filename = ($receipt->receipt_number ?: 'noon-'.$noonOrderId).'.pdf';

                return ['content' => $pdf, 'filename' => $filename];
            }
        }

        $pdf = $this->renderTransactionPdf($noonOrder, $match ?? [
            'customer_name' => $noonOrder['name'] ?: '—',
            'customer_phone' => null,
            'order_number' => $noonOrder['reference'] ?: null,
        ]);

        return [
            'content' => $pdf,
            'filename' => 'noon-'.$noonOrderId.'.pdf',
        ];
    }

    /**
     * @param  array<string, mixed>  $noonOrder
     * @param  array<string, mixed>  $row
     */
    private function renderTransactionPdf(array $noonOrder, array $row): string
    {
        $tempDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'default_font' => 'dejavusans',
            'directionality' => 'rtl',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true,
        ]);

        $mpdf->SetTitle('إيصال نون '.$noonOrder['id']);

        $html = View::make('noon-transaction-pdf', [
            'noon' => $noonOrder,
            'customer_name' => $row['customer_name'] ?? '—',
            'customer_phone' => $row['customer_phone'] ?? null,
            'order_number' => $row['order_number'] ?? $noonOrder['reference'] ?? null,
        ])->render();

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function orderFromSession(?PaymentSession $session): ?Order
    {
        if (! $session) {
            return null;
        }

        $reference = (string) $session->merchant_reference;
        $order = Order::query()->where('order_number', $reference)->first();
        if ($order) {
            return $order;
        }

        $quotationId = $session->payload['quotation_id'] ?? null;
        if ($quotationId) {
            return Order::query()->where('quotation_id', $quotationId)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $noonOrder
     * @return array{name: string, phone: ?string, email: ?string}
     */
    private function customerFrom(?Order $order, ?PaymentSession $session, array $noonOrder): array
    {
        $payload = is_array($session?->payload) ? $session->payload : [];

        $name = $order?->customer_name
            ?: ($payload['customer_name'] ?? null)
            ?: $this->quotationName($payload['quotation_id'] ?? null)
            ?: null;

        if (! is_string($name) || trim($name) === '' || strcasecmp(trim($name), 'Customer') === 0) {
            $name = '—';
        }

        return [
            'name' => $name,
            'phone' => $order?->customer_phone ?: ($payload['customer_phone'] ?? null),
            'email' => $order?->customer_email ?: ($payload['customer_email'] ?? null),
        ];
    }

    private function quotationName(mixed $quotationId): ?string
    {
        if (! is_numeric($quotationId)) {
            return null;
        }

        return Quotation::query()->whereKey((int) $quotationId)->value('customer_name');
    }
}
