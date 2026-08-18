<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type Auth, type NavItem, type StaffRole } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutGrid,
    ShoppingBag,
    Users,
    Package,
    FileText,
    FileSpreadsheet,
    ShoppingCart,
    Tags,
    MessageCircle,
    HardHat,
    ShieldCheck,
    Receipt,
    Building2,
    Search,
    Undo2,
    Wallet,
    BarChart3,
    Route,
    Settings,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed, ref } from 'vue';
import { useAdminTheme } from '@/composables/useAdminTheme';

type NavItemWithRoles = NavItem & {
    roles: StaffRole[];
    children?: (NavItem & { roles: StaffRole[] })[];
};

const page = usePage();
const userRole = computed(() => (page.props.auth as Auth | undefined)?.user?.role ?? null);
const sidebarBadges = computed(() => (page.props.sidebarBadges as {
    work_orders?: number;
    returns?: number;
    payment_receipts?: number;
} | undefined) ?? {});
const searchQuery = ref('');
const { isSalla } = useAdminTheme();

const allNavItems: NavItemWithRoles[] = [
    {
        title: 'لوحة التحكم',
        href: '/dashboard',
        icon: LayoutGrid,
        roles: ['admin', 'general_manager', 'manager'],
    },
    {
        title: 'المنتجات',
        icon: ShoppingBag,
        roles: ['admin', 'general_manager', 'manager'],
        children: [
            {
                title: 'المنتجات',
                href: route('products'),
                icon: ShoppingBag,
                roles: ['admin', 'general_manager', 'manager'],
            },
            {
                title: 'الأصناف',
                href: route('categories.index'),
                icon: Tags,
                roles: ['admin', 'general_manager', 'manager'],
            },
            {
                title: 'البراندات',
                href: route('brands.index'),
                icon: Building2,
                roles: ['admin', 'general_manager', 'manager'],
            },
            {
                title: 'الباقات',
                href: '/packages',
                icon: Package,
                roles: ['admin', 'manager'],
            },
        ],
    },
    {
        title: 'الطلبات',
        href: '/orders',
        icon: ShoppingCart,
        roles: ['admin', 'general_manager', 'manager'],
    },
    {
        title: 'رحلة الطلب',
        href: '/order-journey',
        icon: Route,
        roles: ['admin'],
    },
    {
        title: 'أوامر العمل',
        icon: HardHat,
        roles: ['admin', 'general_manager', 'manager', 'workers_manager', 'warehouse_keeper'],
        children: [
            {
                title: 'أوامر العمل',
                href: '/worker-orders',
                icon: HardHat,
                roles: ['admin', 'general_manager', 'manager', 'workers_manager'],
            },
            {
                title: 'الاسترجاع',
                href: '/returns',
                icon: Undo2,
                roles: ['admin', 'general_manager', 'manager', 'warehouse_keeper', 'workers_manager'],
            },
        ],
    },
    {
        title: 'العملاء',
        href: '/customers',
        icon: Users,
        roles: ['admin', 'general_manager', 'manager'],
    },
    {
        title: 'المستخدمين',
        href: '/users',
        icon: Users,
        roles: ['admin', 'manager'],
    },
    {
        title: 'الحسابات',
        icon: Wallet,
        roles: ['admin', 'general_manager', 'manager', 'accounts'],
        children: [
            {
                title: 'الفواتير',
                href: '/invoices',
                icon: FileText,
                roles: ['admin', 'general_manager', 'manager', 'accounts'],
            },
            {
                title: 'سندات القبض',
                href: '/payment-receipts',
                icon: Receipt,
                roles: ['admin', 'general_manager', 'manager', 'accounts'],
            },
            {
                title: 'استرداد التأمين',
                href: '/insurance-deposits',
                icon: ShieldCheck,
                roles: ['admin', 'general_manager', 'manager', 'accounts'],
            },
        ],
    },
    {
        title: 'التقارير',
        href: '/reports',
        icon: BarChart3,
        roles: ['admin', 'general_manager', 'manager', 'accounts'],
    },
    {
        title: 'عروض الأسعار',
        href: '/quotations',
        icon: FileSpreadsheet,
        roles: ['admin', 'general_manager', 'manager', 'accounts'],
    },
    {
        title: 'إعدادات واتساب',
        href: '/settings/whatsapp',
        icon: MessageCircle,
        roles: ['admin', 'manager'],
    },
    {
        title: 'إعدادات',
        href: '/settings/quotations',
        icon: Settings,
        roles: ['admin'],
    },
];

function badgeForHref(href?: string): number | undefined {
    const badges = sidebarBadges.value;

    if (href === '/worker-orders' || href === route('worker-orders.index')) {
        return badges.work_orders || undefined;
    }

    if (href === '/returns' || href === route('returns.index')) {
        return badges.returns || undefined;
    }

    if (href === '/payment-receipts' || href === route('payment-receipts.index')) {
        return badges.payment_receipts || undefined;
    }

    return undefined;
}

function stripRoles(item: NavItemWithRoles): NavItem {
    const { roles: _roles, children, ...rest } = item;
    const mappedChildren = children?.map(({ roles: _childRoles, ...child }) => ({
        ...child,
        badge: badgeForHref(child.href),
    }));
    const childrenBadgeTotal = mappedChildren?.reduce((sum, child) => sum + (child.badge ?? 0), 0) ?? 0;

    return {
        ...rest,
        badge: childrenBadgeTotal || badgeForHref(item.href),
        children: mappedChildren,
    };
}

const roleNavItems = computed(() => {
    const role = userRole.value;
    if (!role) {
        return [];
    }

    void sidebarBadges.value;

    return allNavItems
        .map((item) => {
            if (item.children?.length) {
                const children = item.children.filter((child) => child.roles.includes(role as StaffRole));
                if (children.length === 0) {
                    return null;
                }

                return stripRoles({ ...item, children });
            }

            if (!item.roles.includes(role as StaffRole)) {
                return null;
            }

            return stripRoles(item);
        })
        .filter((item): item is NavItem => item !== null);
});

const mainNavItems = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) {
        return roleNavItems.value;
    }

    return roleNavItems.value
        .map((item) => {
            const titleMatch = item.title.toLowerCase().includes(query);

            if (item.children?.length) {
                const children = titleMatch
                    ? item.children
                    : item.children.filter((child) => child.title.toLowerCase().includes(query));

                if (children.length === 0) {
                    return null;
                }

                return { ...item, children };
            }

            return titleMatch ? item : null;
        })
        .filter((item): item is NavItem => item !== null);
});

const homeHref = computed(() => {
    switch (userRole.value) {
        case 'workers_manager':
            return route('worker-orders.index');
        case 'accounts':
            return route('quotations.index');
        case 'warehouse_keeper':
            return '/returns';
        default:
            return route('dashboard');
    }
});
</script>

<template>
    <Sidebar
        side="right"
        collapsible="icon"
        variant="sidebar"
        :class="isSalla
            ? '!bg-[#12141c] border-s-0 shadow-none'
            : '!bg-white dark:!bg-[hsl(0,0%,11%)] border-s border-gray-100 dark:border-neutral-800 shadow-[0_0_0_1px_rgba(0,0,0,0.02)]'"
    >
        <SidebarHeader :class="isSalla ? 'gap-4 p-4 pb-3' : 'gap-3 p-4 pb-2'">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="min-h-0 rounded-xl p-0 hover:bg-transparent">
                        <Link :href="homeHref" class="flex items-center gap-3 py-1">
                            <AppLogo admin />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <div class="group-data-[collapsible=icon]:hidden px-0.5">
                <label
                    :class="isSalla
                        ? 'flex h-10 items-center gap-2.5 rounded-xl border border-white/10 bg-white/5 px-3.5 text-white/40 transition-colors focus-within:border-[#7048e8]/50 focus-within:ring-2 focus-within:ring-[#7048e8]/20'
                        : 'flex h-10 items-center gap-2.5 rounded-full border border-gray-200 bg-white px-3.5 text-gray-400 transition-colors focus-within:border-[var(--nav-accent)]/40 focus-within:ring-2 focus-within:ring-[var(--nav-accent-soft)] dark:border-neutral-700 dark:bg-neutral-900 dark:focus-within:border-[var(--nav-accent)] dark:focus-within:ring-[var(--nav-accent-soft-dark)]'"
                >
                    <Search class="size-4 shrink-0 stroke-[1.75]" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="بحث"
                        :class="isSalla
                            ? 'w-full bg-transparent text-sm text-white outline-none placeholder:text-white/35'
                            : 'w-full bg-transparent text-sm text-gray-700 outline-none placeholder:text-gray-400 dark:text-neutral-200 dark:placeholder:text-neutral-500'"
                    />
                </label>
            </div>
        </SidebarHeader>

        <SidebarContent class="flex-1 px-0 py-2">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter
            :class="isSalla
                ? 'border-t border-white/10 bg-transparent p-3'
                : 'border-t border-gray-100 bg-white p-3 dark:border-neutral-800 dark:bg-[hsl(0,0%,11%)]'"
        >
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
