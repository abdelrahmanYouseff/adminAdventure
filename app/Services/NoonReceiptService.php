<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\PaymentSession;
use Illuminate\Support\Collection;

class NoonReceiptService
{
    public function __construct(private NoonPaymentGateway $gateway) {}

    /**
     * Receipts that Noon itself confirms as successful gateway payments.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function confirmedRows(): Collection
    {
        [$sessionsByReference, $sessionsByNoonId] = $this->indexedSessions();

        $receipts = OrderPaymentReceipt::query()
            ->successfulNoon()
            ->with([
                'order:id,order_number,customer_name,customer_phone,customer_email,currency,payment_id,payment_order_reference,payment_status,payment_method,amount_paid,total_amount',
            ])
            ->latest('id')
            ->get();

        $candidates = [];

        foreach ($receipts as $receipt) {
            $order = $receipt->order;
            if (! $order) {
                continue;
            }

            $session = $this->sessionFor($order, $sessionsByReference, $sessionsByNoonId);
            $noonOrderId = $this->gateway->resolveNoonOrderId($order, $receipt, $session);

            if (! $this->isGatewayCandidate($order, $receipt, $session, $noonOrderId)) {
                continue;
            }

            $candidates[] = [
                'type' => 'receipt',
                'receipt' => $receipt,
                'order' => $order,
                'noon_order_id' => $noonOrderId,
            ];
        }

        $orderIdsWithReceipts = $receipts->pluck('order_id')->filter()->unique()->all();

        $orphanOrders = Order::query()
            ->where('payment_method', 'noon')
            ->where('payment_status', 'paid')
            ->when($orderIdsWithReceipts !== [], fn ($query) => $query->whereNotIn('id', $orderIdsWithReceipts))
            ->latest('id')
            ->get();

        foreach ($orphanOrders as $order) {
            $session = $this->sessionFor($order, $sessionsByReference, $sessionsByNoonId);
            $noonOrderId = $this->gateway->resolveNoonOrderId($order, null, $session);

            if (! $this->isGatewayCandidate($order, null, $session, $noonOrderId)) {
                continue;
            }

            $candidates[] = [
                'type' => 'order',
                'receipt' => null,
                'order' => $order,
                'noon_order_id' => $noonOrderId,
            ];
        }

        $captured = $this->gateway->capturedMap(array_column($candidates, 'noon_order_id'));

        return collect($candidates)
            ->filter(fn (array $candidate) => ($captured[$candidate['noon_order_id']] ?? false) === true)
            ->map(function (array $candidate) {
                /** @var Order $order */
                $order = $candidate['order'];
                /** @var OrderPaymentReceipt|null $receipt */
                $receipt = $candidate['receipt'];
                $noonOrderId = (string) $candidate['noon_order_id'];

                return $receipt
                    ? $this->serializeReceipt($receipt, $order, $noonOrderId)
                    : $this->serializeOrder($order, $noonOrderId);
            })
            ->sortByDesc(fn (array $row) => $row['sort_at'] ?? '')
            ->values();
    }

    public function isConfirmed(Order $order, ?OrderPaymentReceipt $receipt = null): bool
    {
        [$sessionsByReference, $sessionsByNoonId] = $this->indexedSessions();
        $session = $this->sessionFor($order, $sessionsByReference, $sessionsByNoonId);
        $noonOrderId = $this->gateway->resolveNoonOrderId($order, $receipt, $session);

        if (! $this->isGatewayCandidate($order, $receipt, $session, $noonOrderId)) {
            return false;
        }

        return ($this->gateway->capturedMap([$noonOrderId])[$noonOrderId] ?? false) === true;
    }

    /**
     * @return array{0: Collection<string, PaymentSession>, 1: Collection<string, PaymentSession>}
     */
    private function indexedSessions(): array
    {
        $sessions = $this->gatewaySessions();

        return [
            $sessions->keyBy(fn (PaymentSession $session) => (string) $session->merchant_reference),
            $sessions
                ->filter(fn (PaymentSession $session) => filled($session->noon_order_id))
                ->keyBy(fn (PaymentSession $session) => (string) $session->noon_order_id),
        ];
    }

    /**
     * @param  Collection<string, PaymentSession>  $sessionsByReference
     * @param  Collection<string, PaymentSession>  $sessionsByNoonId
     */
    private function sessionFor(
        Order $order,
        Collection $sessionsByReference,
        Collection $sessionsByNoonId,
    ): ?PaymentSession {
        return $sessionsByReference->get((string) $order->order_number)
            ?? $sessionsByReference->get((string) $order->payment_order_reference)
            ?? $sessionsByNoonId->get((string) $order->payment_id);
    }

    private function isGatewayCandidate(
        Order $order,
        ?OrderPaymentReceipt $receipt,
        ?PaymentSession $session,
        ?string $noonOrderId,
    ): bool {
        if (! $this->gateway->isRealNoonOrderId($noonOrderId, $order->order_number)) {
            return false;
        }

        if ($session?->noon_order_id) {
            return true;
        }

        if ($receipt !== null && $this->isGatewayReceiptNotes($receipt->notes)) {
            return true;
        }

        return $this->gateway->isConfigured();
    }

    private function isGatewayReceiptNotes(?string $notes): bool
    {
        $notes = (string) $notes;

        return str_contains($notes, 'دفع إلكتروني عبر Noon')
            || str_contains($notes, 'دفع إلكتروني عبر رابط الطلب');
    }

    /**
     * @return Collection<int, PaymentSession>
     */
    private function gatewaySessions(): Collection
    {
        return PaymentSession::query()
            ->whereNotNull('noon_order_id')
            ->where('noon_order_id', '!=', '')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReceipt(OrderPaymentReceipt $receipt, Order $order, string $noonOrderId): array
    {
        $paidAt = $receipt->approved_at ?? $receipt->created_at;

        return [
            'key' => 'receipt-'.$receipt->id,
            'receipt_id' => $receipt->id,
            'order_id' => $order->id,
            'customer_name' => $order->customer_name ?: '—',
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'order_number' => $order->order_number,
            'receipt_number' => $receipt->receipt_number,
            'amount' => (float) $receipt->amount,
            'currency' => $order->currency ?: 'SAR',
            'noon_order_id' => $noonOrderId,
            'paid_at' => $paidAt?->toIso8601String(),
            'sort_at' => $paidAt?->toIso8601String(),
            'pdf_url' => route('noon-receipts.pdf', $receipt),
            'download_url' => route('noon-receipts.pdf', ['receipt' => $receipt, 'download' => 1]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order, string $noonOrderId): array
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
            'noon_order_id' => $noonOrderId,
            'paid_at' => $paidAt?->toIso8601String(),
            'sort_at' => $paidAt?->toIso8601String(),
            'pdf_url' => route('noon-receipts.order-pdf', $order),
            'download_url' => route('noon-receipts.order-pdf', ['order' => $order, 'download' => 1]),
        ];
    }
}
