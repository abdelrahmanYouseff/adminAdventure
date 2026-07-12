<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type Auth, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, ShoppingBag, Users, Package, FileText, FileSpreadsheet, ShoppingCart, Tags, MessageCircle, HardHat } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';

const page = usePage();
const userRole = computed(() => (page.props.auth as Auth | undefined)?.user?.role);

const adminNavItems: NavItem[] = [
    {
        title: 'لوحة التحكم',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'المنتجات',
        href: route('products'),
        icon: ShoppingBag,
    },
    {
        title: 'الأصناف',
        href: route('categories.index'),
        icon: Tags,
    },
    {
        title: 'الباقات',
        href: '/packages',
        icon: Package,
    },
    {
        title: 'الطلبات',
        href: '/orders',
        icon: ShoppingCart,
    },
    {
        title: 'أوامر العمل',
        href: '/worker-orders',
        icon: HardHat,
    },
    {
        title: 'العملاء',
        href: '/customers',
        icon: Users,
    },
    {
        title: 'المستخدمين',
        href: '/users',
        icon: Users,
    },
    {
        title: 'الفواتير',
        href: '/invoices',
        icon: FileText,
    },
    {
        title: 'عروض الأسعار',
        href: '/quotations',
        icon: FileSpreadsheet,
    },
    {
        title: 'إعدادات واتساب',
        href: '/settings/whatsapp',
        icon: MessageCircle,
    },
];

const workerNavItems: NavItem[] = [
    {
        title: 'أوامر العمل',
        href: '/worker-orders',
        icon: HardHat,
    },
];

const mainNavItems = computed(() => (userRole.value === 'worker' ? workerNavItems : adminNavItems));

const homeHref = computed(() => (userRole.value === 'worker' ? route('worker-orders.index') : route('dashboard')));
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
