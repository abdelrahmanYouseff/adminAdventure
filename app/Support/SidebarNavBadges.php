<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderPaymentReceipt;
use App\Models\User;

class SidebarNavBadges
{
    /**
     * @return array{work_orders: int, warehouse: int, returns: int, payment_receipts: int}
     */
    public static function forUser(?User $user): array
    {
        if (! $user?->canAccessDashboard()) {
            return [
                'work_orders' => 0,
                'warehouse' => 0,
                'returns' => 0,
                'payment_receipts' => 0,
            ];
        }

        return [
            'work_orders' => static::openWorkOrdersCount(),
            'warehouse' => static::pendingWarehouseCount(),
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
     * Work orders waiting for warehouse-keeper approval after return confirmation.
     */
    public static function pendingWarehouseCount(): int
    {
        return Order::query()
            ->whereNotNull('warehouse_returned_at')
            ->whereNotNull('warehouse_returned_by')
            ->whereNull('warehouse_keeper_approved_at')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->count();
    }

    /**
     * Returns waiting for warehouse confirmation (same as «بانتظار الاسترجاع»).
     */
    public static function openReturnsCount(): int
    {
        return Order::query()
            ->whereNotIn('status', ['cancelled', 'refunded'])
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
