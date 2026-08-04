<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type Auth, type NavItem, type StaffRole } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, ShoppingBag, Users, Package, FileText, FileSpreadsheet, ShoppingCart, Tags, MessageCircle, HardHat, ShieldCheck, Receipt, Building2, Search, Undo2 } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed, ref } from 'vue';

type NavItemWithRoles = NavItem & { roles: StaffRole[] };

const page = usePage();
const userRole = computed(() => (page.props.auth as Auth | undefined)?.user?.role ?? null);
const searchQuery = ref('');

const allNavItems: NavItemWithRoles[] = [
    {
        title: 'لوحة التحكم',
        href: '/dashboard',
        icon: LayoutGrid,
        roles: ['admin', 'general_manager', 'manager'],
    },
    {
        title: 'المنتجات',
        href: route('products'),
        icon: ShoppingBag,
        roles: ['admin', 'general_manager', 'manager', 'warehouse_keeper'],
    },
    {
        title: 'الاسترجاع',
        href: '/returns',
        icon: Undo2,
        roles: ['admin', 'general_manager', 'manager', 'warehouse_keeper'],
    },
    {
        title: 'الأصناف',
        href: route('categories.index'),
        icon: Tags,
        roles: ['admin', 'general_manager', 'manager', 'warehouse_keeper'],
    },
    {
        title: 'البراندات',
        href: route('brands.index'),
        icon: Building2,
        roles: ['admin', 'general_manager', 'manager', 'warehouse_keeper'],
    },
    {
        title: 'الباقات',
        href: '/packages',
        icon: Package,
        roles: ['admin', 'manager'],
    },
    {
        title: 'الطلبات',
        href: '/orders',
        icon: ShoppingCart,
        roles: ['admin', 'general_manager', 'manager'],
    },
    {
        title: 'سندات القبض',
        href: '/payment-receipts',
        icon: Receipt,
        roles: ['admin', 'general_manager', 'manager', 'accounts'],
    },
    {
        title: 'أوامر العمل',
        href: '/worker-orders',
        icon: HardHat,
        roles: ['admin', 'general_manager', 'manager', 'workers_manager'],
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
        title: 'الفواتير',
        href: '/invoices',
        icon: FileText,
        roles: ['admin', 'general_manager', 'manager', 'accounts'],
    },
    {
        title: 'استرداد التأمين',
        href: '/insurance-deposits',
        icon: ShieldCheck,
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
];

const roleNavItems = computed(() => {
    const role = userRole.value;
    if (!role) {
        return [];
    }

    return allNavItems
        .filter((item) => item.roles.includes(role as StaffRole))
        .map(({ roles: _roles, ...item }) => item);
});

const mainNavItems = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) {
        return roleNavItems.value;
    }

    return roleNavItems.value.filter((item) => item.title.toLowerCase().includes(query));
});

const homeHref = computed(() => {
    switch (userRole.value) {
        case 'workers_manager':
            return route('worker-orders.index');
        case 'accounts':
            return route('quotations.index');
        case 'warehouse_keeper':
            return route('products');
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
        class="!bg-white dark:!bg-[hsl(0,0%,11%)] border-s border-gray-100 dark:border-neutral-800 shadow-[0_0_0_1px_rgba(0,0,0,0.02)]"
    >
        <SidebarHeader class="gap-3 p-4 pb-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="min-h-0 rounded-xl p-0 hover:bg-transparent">
                        <Link :href="homeHref" class="flex items-center gap-3 py-1">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <div class="group-data-[collapsible=icon]:hidden px-0.5">
                <label class="flex h-10 items-center gap-2.5 rounded-full border border-gray-200 bg-white px-3.5 text-gray-400 transition-colors focus-within:border-teal-300 focus-within:ring-2 focus-within:ring-teal-100 dark:border-neutral-700 dark:bg-neutral-900 dark:focus-within:border-teal-700 dark:focus-within:ring-teal-950">
                    <Search class="size-4 shrink-0 stroke-[1.75]" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="بحث"
                        class="w-full bg-transparent text-sm text-gray-700 outline-none placeholder:text-gray-400 dark:text-neutral-200 dark:placeholder:text-neutral-500"
                    />
                </label>
            </div>
        </SidebarHeader>

        <SidebarContent class="flex-1 px-0 py-2">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="border-t border-gray-100 bg-white p-3 dark:border-neutral-800 dark:bg-[hsl(0,0%,11%)]">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
