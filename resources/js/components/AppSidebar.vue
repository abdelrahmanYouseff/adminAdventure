<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type Auth, type NavItem, type StaffRole } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, ShoppingBag, Users, Package, FileText, FileSpreadsheet, ShoppingCart, Tags, MessageCircle, HardHat, Building2 } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';

type NavItemWithRoles = NavItem & { roles: StaffRole[] };

const page = usePage();
const userRole = computed(() => (page.props.auth as Auth | undefined)?.user?.role ?? null);

const allNavItems: NavItemWithRoles[] = [
    {
        title: 'لوحة التحكم',
        href: '/dashboard',
        icon: LayoutGrid,
        roles: ['admin', 'manager'],
    },
    {
        title: 'المنتجات',
        href: route('products'),
        icon: ShoppingBag,
        roles: ['admin', 'manager'],
    },
    {
        title: 'الأصناف',
        href: route('categories.index'),
        icon: Tags,
        roles: ['admin', 'manager'],
    },
    {
        title: 'الباقات',
        href: '/packages',
        icon: Package,
        roles: ['admin'],
    },
    {
        title: 'الطلبات',
        href: '/orders',
        icon: ShoppingCart,
        roles: ['admin'],
    },
    {
        title: 'أوامر العمل',
        href: '/worker-orders',
        icon: HardHat,
        roles: ['admin', 'manager', 'workers_manager', 'worker'],
    },
    {
        title: 'العملاء',
        href: '/customers',
        icon: Users,
        roles: ['admin'],
    },
    {
        title: 'عملاء الشركات',
        href: '/company-clients',
        icon: Building2,
        roles: ['admin', 'manager'],
    },
    {
        title: 'المستخدمين',
        href: '/users',
        icon: Users,
        roles: ['admin'],
    },
    {
        title: 'الفواتير',
        href: '/invoices',
        icon: FileText,
        roles: ['admin', 'manager', 'accounts'],
    },
    {
        title: 'عروض الأسعار',
        href: '/quotations',
        icon: FileSpreadsheet,
        roles: ['admin', 'manager', 'accounts'],
    },
    {
        title: 'إعدادات واتساب',
        href: '/settings/whatsapp',
        icon: MessageCircle,
        roles: ['admin'],
    },
];

const mainNavItems = computed(() => {
    const role = userRole.value;
    if (!role) {
        return [];
    }

    return allNavItems
        .filter((item) => item.roles.includes(role as StaffRole))
        .map(({ roles: _roles, ...item }) => item);
});

const homeHref = computed(() => {
    switch (userRole.value) {
        case 'worker':
        case 'workers_manager':
            return route('worker-orders.index');
        case 'accounts':
            return route('quotations.index');
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
        class="!bg-[#f5f5f5] dark:!bg-[hsl(0,0%,11%)] shadow-sm"
    >
        <SidebarHeader class="p-5 pb-3 border-b border-neutral-200/80 dark:border-neutral-700/80">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="rounded-xl p-0 hover:bg-transparent min-h-0">
                        <Link :href="homeHref" class="flex items-center gap-3 py-1">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="flex-1 px-0 py-4">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="p-4 border-t border-neutral-200 dark:border-neutral-700 bg-white/50 dark:bg-black/20">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
