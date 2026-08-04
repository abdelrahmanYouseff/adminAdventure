<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;

class InsuranceApprovalChain
{
    public const STEP_WORKERS_MANAGER = 'workers_manager';

    public const STEP_MANAGER = 'manager';

    public const STEP_GENERAL_MANAGER = 'general_manager';

    public const STEP_ACCOUNTS = 'accounts';

    /**
     * @return array<string, array{label: string, at: string, by: string, roles: list<string>}>
     */
    public static function steps(): array
    {
        return [
            self::STEP_WORKERS_MANAGER => [
                'label' => 'مدير العمال',
                'at' => 'work_order_approved_at',
                'by' => 'work_order_approved_by',
                'roles' => [User::ROLE_WORKERS_MANAGER],
            ],
            self::STEP_MANAGER => [
                'label' => 'المسئول',
                'at' => 'insurance_manager_approved_at',
                'by' => 'insurance_manager_approved_by',
                'roles' => [User::ROLE_MANAGER],
            ],
            self::STEP_GENERAL_MANAGER => [
                'label' => 'المدير العام',
                'at' => 'insurance_gm_approved_at',
                'by' => 'insurance_gm_approved_by',
                'roles' => [User::ROLE_GENERAL_MANAGER],
            ],
            self::STEP_ACCOUNTS => [
                'label' => 'المحاسب',
                'at' => 'insurance_accounts_approved_at',
                'by' => 'insurance_accounts_approved_by',
                'roles' => [User::ROLE_ACCOUNTS],
            ],
        ];
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

    public static function canUserApproveStep(?User $user, string $stepKey): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $step = self::steps()[$stepKey] ?? null;
        if (! $step) {
            return false;
        }

        return in_array($user->role, $step['roles'], true);
    }

    /**
     * رسالة عندما يحاول مستخدم التعميد قبل اكتمال الخطوة السابقة.
     */
    public static function blockedMessage(Order $order, ?User $user = null): string
    {
        $next = self::nextPendingStep($order);

        if ($next === null) {
            return 'اكتملت سلسلة التعميدات مسبقاً.';
        }

        $steps = self::steps();
        $nextLabel = $steps[$next]['label'];

        if ($user && ! self::canUserApproveStep($user, $next)) {
            return "التعميد الحالي مطلوب من {$nextLabel} أولاً. لا يمكنك التعميد قبل اكتمال التسلسل.";
        }

        $previousKeys = array_keys($steps);
        $index = array_search($next, $previousKeys, true);

        if ($index === 0) {
            return "بانتظار تعميد {$nextLabel}.";
        }

        $previousLabel = $steps[$previousKeys[$index - 1]]['label'];

        return "لا يمكن التعميد الآن. يجب أن يعتمد {$previousLabel} أولاً، ثم {$nextLabel}.";
    }

    /**
     * @return list<array{key: string, label: string, completed: bool, approved_at: string|null, approved_by_name: string|null, is_next: bool}>
     */
    public static function progress(Order $order): array
    {
        $next = self::nextPendingStep($order);
        $steps = self::steps();

        $byRelations = [
            self::STEP_WORKERS_MANAGER => 'workOrderApprovedBy',
            self::STEP_MANAGER => 'insuranceManagerApprovedBy',
            self::STEP_GENERAL_MANAGER => 'insuranceGmApprovedBy',
            self::STEP_ACCOUNTS => 'insuranceAccountsApprovedBy',
        ];

        $progress = [];

        foreach ($steps as $key => $step) {
            $at = $order->{$step['at']};
            $relation = $byRelations[$key];
            $approver = $order->relationLoaded($relation) ? $order->{$relation} : null;

            $progress[] = [
                'key' => $key,
                'label' => $step['label'],
                'completed' => (bool) $at,
                'approved_at' => $at?->toIso8601String(),
                'approved_by_name' => $approver?->name,
                'is_next' => $next === $key,
            ];
        }

        return $progress;
    }
}
