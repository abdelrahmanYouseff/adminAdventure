<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { LayoutGrid, ShoppingBag, Users, Receipt, Package, FileText, FileSpreadsheet, ShoppingCart } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'لوحة التحكم',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'المنتجات',
        href: '/products',
        icon: ShoppingBag,
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
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
