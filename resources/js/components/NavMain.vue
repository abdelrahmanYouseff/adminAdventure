<script setup lang="ts">
import { SidebarGroup, SidebarGroupContent, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    items: NavItem[];
}>();

const page = usePage();

const isItemActive = (href: string) => {
    const current = page.url.split('?')[0];
    try {
        const path = new URL(href, window.location.origin).pathname;
        if (path === '/dashboard') {
            return current === '/dashboard';
        }
        return current === path || current.startsWith(`${path}/`);
    } catch {
        return current === href;
    }
};

const activeHref = computed(() => {
    const match = props.items.find((item) => isItemActive(item.href));
    return match?.href ?? null;
});
</script>

<template>
    <SidebarGroup class="px-4 py-2">
        <SidebarGroupLabel
            class="mb-2 h-auto px-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-400 dark:text-neutral-500"
        >
            القائمة الرئيسية
        </SidebarGroupLabel>
        <SidebarGroupContent>
            <SidebarMenu class="gap-1">
                <SidebarMenuItem v-for="item in items" :key="item.title" class="relative">
                    <span
                        v-if="activeHref === item.href"
                        class="pointer-events-none absolute inset-y-1 start-0 w-[3px] rounded-full bg-teal-600 dark:bg-teal-400"
                        aria-hidden="true"
                    />
                    <SidebarMenuButton
                        as-child
                        size="lg"
                        :is-active="activeHref === item.href"
                        :tooltip="item.title"
                        class="rounded-xl text-gray-500 hover:text-teal-700 data-[active=true]:bg-[#e6f7f5] data-[active=true]:text-teal-700 dark:text-neutral-400 dark:hover:text-teal-300 dark:data-[active=true]:bg-teal-950/50 dark:data-[active=true]:text-teal-300"
                    >
                        <Link :href="item.href" class="flex w-full items-center gap-3.5">
                            <component
                                :is="item.icon"
                                class="size-[1.15rem] shrink-0 stroke-[1.75]"
                                :class="activeHref === item.href ? 'text-teal-600 dark:text-teal-300' : 'opacity-80'"
                            />
                            <span class="truncate">{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
