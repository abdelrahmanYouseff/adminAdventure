<?php

namespace App\Support;

use App\Models\User;

class MainAppModules
{
    /**
     * @return list<array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     icon: string,
     *     tone: string,
     *     desktop_path: string,
     *     roles: list<string>
     * }>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'dashboard',
                'title' => 'لوحة التحكم',
                'description' => 'نظرة عامة على الأداء والأرقام',
                'icon' => 'layout-grid',
                'tone' => 'teal',
                'desktop_path' => '/dashboard',
                'roles' => [User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER],
            ],
            [
                'key' => 'products',
                'title' => 'المنتجات',
                'description' => 'إدارة المنتجات والأسعار والمخزون',
                'icon' => 'shopping-bag',
                'tone' => 'sky',
                'desktop_path' => '/products',
                'roles' => [User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER],
            ],
            [
                'key' => 'returns',
                'title' => 'الاسترجاع',
                'description' => 'متابعة عمليات الاسترجاع من المستودع',
                'icon' => 'undo-2',
                'tone' => 'amber',
                'desktop_path' => '/returns',
                'roles' => [User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER, User::ROLE_WAREHOUSE_KEEPER],
            ],
            [
                'key' => 'categories',
                'title' => 'الأصناف',
                'description' => 'تصنيفات المنتجات حسب البراند',
                'icon' => 'tags',
                'tone' => 'violet',
                'desktop_path' => '/categories',
                'roles' => [User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER],
            ],
            [
                'key' => 'brands',
                'title' => 'البراندات',
                'description' => 'إدارة البراندات والواجهات',
                'icon' => 'building-2',
                'tone' => 'indigo',
                'desktop_path' => '/brands',
                'roles' => [User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER],
            ],
            [
                'key' => 'packages',
                'title' => 'الباقات',
                'description' => 'باقات المنتجات والعروض المجمّعة',
                'icon' => 'package',
                'tone' => 'fuchsia',
                'desktop_path' => '/packages',
                'roles' => [User::ROLE_ADMIN, User::ROLE_MANAGER],
            ],
            [
                'key' => 'orders',
                'title' => 'الطلبات',
                'description' => 'متابعة الطلبات والمدفوعات',
                'icon' => 'shopping-cart',
                'tone' => 'blue',
                'desktop_path' => '/orders',
                'roles' => [User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER],
            ],
            [
                'key' => 'payment-receipts',
                'title' => 'سندات القبض',
                'description' => 'اعتماد ورفض المبالغ المحصّلة',
                'icon' => 'receipt',
                'tone' => 'emerald',
                'desktop_path' => '/payment-receipts',
                'roles' => [User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER, User::ROLE_ACCOUNTS],
            ],
            [
                'key' => 'worker-orders',
                'title' => 'أوامر العمل',
                'description' => 'توزيع ومتابعة أوامر التركيب',
                'icon' => 'hard-hat',
                'tone' => 'orange',
                'desktop_path' => '/worker-orders',
                'roles' => [User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER, User::ROLE_WORKERS_MANAGER],
            ],
            [
                'key' => 'customers',
                'title' => 'العملاء',
                'description' => 'ملفات العملاء والأفراد والشركات',
                'icon' => 'users',
                'tone' => 'cyan',
                'desktop_path' => '/customers',
                'roles' => [User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER],
            ],
            [
                'key' => 'users',
                'title' => 'المستخدمين',
                'description' => 'حسابات الموظفين والصلاحيات',
                'icon' => 'user-cog',
                'tone' => 'slate',
                'desktop_path' => '/users',
                'roles' => [User::ROLE_ADMIN, User::ROLE_MANAGER],
            ],
            [
                'key' => 'invoices',
                'title' => 'الفواتير',
                'description' => 'الفواتير النهائية والتحميل',
                'icon' => 'file-text',
                'tone' => 'lime',
                'desktop_path' => '/invoices',
                'roles' => [User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER, User::ROLE_ACCOUNTS],
            ],
            [
                'key' => 'insurance-deposits',
                'title' => 'استرداد التأمين',
                'description' => 'متابعة تأمينات العملاء والاسترداد',
                'icon' => 'shield-check',
                'tone' => 'rose',
                'desktop_path' => '/insurance-deposits',
                'roles' => [User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER, User::ROLE_ACCOUNTS],
            ],
            [
                'key' => 'quotations',
                'title' => 'عروض الأسعار',
                'description' => 'إنشاء ومتابعة عروض الأسعار',
                'icon' => 'file-spreadsheet',
                'tone' => 'teal',
                'desktop_path' => '/quotations',
                'roles' => [User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER, User::ROLE_ACCOUNTS],
            ],
            [
                'key' => 'whatsapp',
                'title' => 'إعدادات واتساب',
                'description' => 'ربط وإعداد إشعارات واتساب',
                'icon' => 'message-circle',
                'tone' => 'green',
                'desktop_path' => '/settings/whatsapp',
                'roles' => [User::ROLE_ADMIN, User::ROLE_MANAGER],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        $role = (string) $user->role;

        return array_values(array_filter(
            self::all(),
            fn (array $module) => in_array($role, $module['roles'], true),
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findForUser(string $key, User $user): ?array
    {
        foreach (self::forUser($user) as $module) {
            if ($module['key'] === $key) {
                return $module;
            }
        }

        return null;
    }
}
