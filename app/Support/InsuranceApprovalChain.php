<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;

class InsuranceApprovalChain
{
    public const STEP_WORKERS_MANAGER = 'workers_manager';

    public const STEP_ACCOUNTS_RECEIVED = 'accounts_received';

    public const STEP_ADMIN = 'admin';

    public const STEP_ACCOUNTS_TRANSFER = 'accounts_transfer';

    /**
     * @return array<string, array{label: string, description: string, at: string, by: string, relation: string, roles: list<string>}>
     */
    public static function steps(): array
    {
        return [
            self::STEP_WORKERS_MANAGER => [
                'label' => 'مدير العمال',
                'description' => 'أول اعتماد بعد رفع طلب استرداد التأمين',
                'at' => 'insurance_workers_manager_approved_at',
                'by' => 'insurance_workers_manager_approved_by',
                'relation' => 'insuranceWorkersManagerApprovedBy',
                'roles' => [User::ROLE_WORKERS_MANAGER],
            ],
            self::STEP_ACCOUNTS_RECEIVED => [
                'label' => 'المحاسب — استلام المبلغ',
                'description' => 'يتأكد أن مبلغ التأمين دخل',
                'at' => 'insurance_accounts_received_at',
                'by' => 'insurance_accounts_received_by',
                'relation' => 'insuranceAccountsReceivedBy',
                'roles' => [User::ROLE_ACCOUNTS],
            ],
            self::STEP_ADMIN => [
                'label' => 'الادمن',
                'description' => 'اعتماد الادمن فقط',
                'at' => 'insurance_admin_approved_at',
                'by' => 'insurance_admin_approved_by',
                'relation' => 'insuranceAdminApprovedBy',
                'roles' => [User::ROLE_ADMIN],
            ],
            self::STEP_ACCOUNTS_TRANSFER => [
                'label' => 'المحاسب — اعتماد التحويل',
                'description' => 'اعتماد تحويل مبلغ الاسترداد',
                'at' => 'insurance_accounts_approved_at',
                'by' => 'insurance_accounts_approved_by',
                'relation' => 'insuranceAccountsApprovedBy',
                'roles' => [User::ROLE_ACCOUNTS],
            ],
        ];
    }

    public static function summary(): string
    {
        return 'مدير العمال ← المحاسب (استلام المبلغ) ← الادمن ← المحاسب (اعتماد التحويل)';
    }

    public static function nextPendingStep(Order $order): ?string
    {
        foreach (self::steps() as $key => $step) {
            if (! $order->{$step['at']}) {
                return $key;
            }
        }

        return null;
    }

    public static function isFullyApproved(Order $order): bool
    {
        return self::nextPendingStep($order) === null;
    }

    public static function canApproveWorkOrder(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAdminAccess() || $user->isWorkersManager();
    }

    public static function canUserApproveStep(?User $user, string $stepKey): bool
    {
        if (! $user) {
            return false;
        }

        $step = self::steps()[$stepKey] ?? null;
        if (! $step) {
            return false;
        }

        return in_array($user->role, $step['roles'], true);
    }

    public static function blockedMessage(Order $order, ?User $user = null): string
    {
        $next = self::nextPendingStep($order);

        if ($next === null) {
            return 'اكتملت سلسلة التعميدات مسبقاً.';
        }

        $nextLabel = self::steps()[$next]['label'];

        if ($user && ! self::canUserApproveStep($user, $next)) {
            return "الاعتماد واقف عند {$nextLabel}. لا يمكنك التعميد قبل دورك.";
        }

        return "الاعتماد واقف عند {$nextLabel}.";
    }

    /**
     * @return list<array{key: string, label: string, description: string, completed: bool, approved_at: string|null, approved_by_name: string|null, is_next: bool}>
     */
    public static function progress(Order $order): array
    {
        $next = self::nextPendingStep($order);
        $progress = [];

        foreach (self::steps() as $key => $step) {
            $at = $order->{$step['at']};
            $relation = $step['relation'];
            $approver = $order->relationLoaded($relation) ? $order->{$relation} : null;

            $progress[] = [
                'key' => $key,
                'label' => $step['label'],
                'description' => $step['description'],
                'completed' => (bool) $at,
                'approved_at' => $at?->toIso8601String(),
                'approved_by_name' => $approver?->name,
                'is_next' => $next === $key,
            ];
        }

        return $progress;
    }
}
