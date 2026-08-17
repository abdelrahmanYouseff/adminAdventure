<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\User;

class SidebarNavBadges
{
    /**
     * @return array{work_orders: int, returns: int, payment_receipts: int}
     */
    public static function forUser(?User $user): array
    {
        if (! $user?->canAccessDashboard()) {
            return [
                'work_orders' => 0,
                'returns' => 0,
                'payment_receipts' => 0,
            ];
        }

        return [
            'work_orders' => static::openWorkOrdersCount(),
            'returns' => static::openReturnsCount(),
            'payment_receipts' => static::pendingPaymentReceiptsCount(),
        ];
    }

    /**
     * Work orders that still need installation / workers-manager approval.
     */
    public static function openWorkOrdersCount(): int
    {
        return Order::query()
            ->whereHas('workerOrders')
            ->whereNull('work_order_approved_at')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->count();
    }

    /**
     * Returns waiting for warehouse confirmation after the work order is approved.
     */
    public static function openReturnsCount(): int
    {
        return Order::query()
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereNotNull('work_order_approved_at')
            ->whereNull('warehouse_returned_at')
            ->count();
    }

    /**
     * Payment receipts waiting for accountant approval.
     */
    public static function pendingPaymentReceiptsCount(): int
    {
        return OrderPaymentReceipt::query()
            ->where('approval_status', OrderPaymentReceipt::STATUS_PENDING)
            ->count();
    }
}
