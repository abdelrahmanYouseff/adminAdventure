<script setup lang="ts">
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    items: NavItem[];
}>();

const page = usePage();
</script>

<template>
    <SidebarGroup class="px-3 py-2">
        <SidebarGroupContent>
            <SidebarMenu class="gap-0.5">
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton as-child :is-active="item.href === page.url" :tooltip="item.title">
                        <Link :href="item.href" class="flex items-center gap-3 w-full">
                            <component :is="item.icon" class="size-4 shrink-0 text-[var(--sidebar-foreground)]" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
