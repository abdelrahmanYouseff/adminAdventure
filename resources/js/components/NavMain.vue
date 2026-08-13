<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import { useAdminTheme } from '@/composables/useAdminTheme';

const props = defineProps<{
    items: NavItem[];
}>();

const page = usePage();
const { isSalla } = useAdminTheme();

const isItemActive = (href?: string) => {
    if (!href) {
        return false;
    }

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

const hasActiveChild = (item: NavItem) =>
    Boolean(item.children?.some((child) => isItemActive(child.href)));

const activeHref = computed(() => {
    for (const item of props.items) {
        if (item.children?.length) {
            const child = item.children.find((c) => isItemActive(c.href));
            if (child?.href) {
                return child.href;
            }
            continue;
        }

        if (item.href && isItemActive(item.href)) {
            return item.href;
        }
    }

    return null;
});

const navButtonClass = computed(() =>
    isSalla.value
        ? 'rounded-[0.7rem] text-[#c5c9d6] hover:text-white data-[active=true]:bg-[#7048e8] data-[active=true]:text-white'
        : 'rounded-xl text-gray-500 hover:text-[var(--nav-accent-text)] data-[active=true]:bg-[var(--nav-accent-soft)] data-[active=true]:text-[var(--nav-accent-text)] dark:text-neutral-400 dark:hover:text-[var(--nav-accent)] dark:data-[active=true]:bg-[var(--nav-accent-soft-dark)] dark:data-[active=true]:text-[var(--nav-accent)]',
);

const navIconClass = (active: boolean) => {
    if (isSalla.value) {
        return active ? 'text-white' : 'text-[#9aa0b2]';
    }

    return active ? 'text-[var(--nav-accent)] dark:text-[var(--nav-accent)]' : 'opacity-80';
};

const subButtonClass = computed(() =>
    isSalla.value
        ? 'h-9 rounded-lg text-[#8b90a0] hover:bg-white/5 hover:text-white data-[active=true]:bg-[#7048e8]/25 data-[active=true]:font-semibold data-[active=true]:text-white'
        : 'h-9 rounded-lg text-gray-500 hover:bg-[var(--nav-accent-soft)] hover:text-[var(--nav-accent-text)] data-[active=true]:bg-[var(--nav-accent-soft)] data-[active=true]:font-semibold data-[active=true]:text-[var(--nav-accent-text)] dark:text-neutral-400 dark:hover:text-[var(--nav-accent)] dark:data-[active=true]:bg-[var(--nav-accent-soft-dark)] dark:data-[active=true]:text-[var(--nav-accent)]',
);
</script>

<template>
    <SidebarGroup class="px-4 py-2">
        <SidebarGroupLabel
            class="mb-2 h-auto px-3 text-[11px] font-semibold uppercase tracking-[0.14em]"
            :class="isSalla ? 'text-[#6d7385]' : 'text-gray-400 dark:text-neutral-500'"
        >
            القائمة الرئيسية
        </SidebarGroupLabel>
        <SidebarGroupContent>
            <SidebarMenu class="gap-1">
                <template v-for="item in items" :key="item.title">
                    <Collapsible
                        v-if="item.children?.length"
                        as-child
                        :default-open="hasActiveChild(item)"
                        class="group/collapsible"
                    >
                        <SidebarMenuItem class="relative">
                            <span
                                v-if="hasActiveChild(item) && !isSalla"
                                class="pointer-events-none absolute inset-y-1 start-0 w-[3px] rounded-full bg-[var(--nav-accent)]"
                                aria-hidden="true"
                            />
                            <CollapsibleTrigger as-child>
                                <SidebarMenuButton
                                    size="lg"
                                    :is-active="isSalla ? false : hasActiveChild(item)"
                                    :tooltip="item.title"
                                    :class="navButtonClass"
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-[1.15rem] shrink-0 stroke-[1.75]"
                                        :class="navIconClass(hasActiveChild(item))"
                                    />
                                    <span class="truncate">{{ item.title }}</span>
                                    <ChevronDown
                                        class="ms-auto size-4 shrink-0 opacity-60 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-180"
                                    />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>

                            <CollapsibleContent>
                                <SidebarMenuSub class="mx-0 me-3.5 ms-4 border-s border-e-0 px-2.5">
                                    <SidebarMenuSubItem v-for="child in item.children" :key="child.title">
                                        <SidebarMenuSubButton
                                            as-child
                                            :is-active="activeHref === child.href"
                                            class="h-9 rounded-lg"
                                            :class="subButtonClass"
                                        >
                                            <Link :href="child.href || '#'" class="flex w-full items-center gap-2.5">
                                                <component
                                                    v-if="child.icon"
                                                    :is="child.icon"
                                                    class="size-4 shrink-0 stroke-[1.75]"
                                                    :class="navIconClass(activeHref === child.href)"
                                                />
                                                <span class="truncate">{{ child.title }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </SidebarMenuItem>
                    </Collapsible>

                    <SidebarMenuItem v-else class="relative">
                        <span
                            v-if="activeHref === item.href && !isSalla"
                            class="pointer-events-none absolute inset-y-1 start-0 w-[3px] rounded-full bg-[var(--nav-accent)]"
                            aria-hidden="true"
                        />
                        <SidebarMenuButton
                            as-child
                            size="lg"
                            :is-active="activeHref === item.href"
                            :tooltip="item.title"
                            :class="navButtonClass"
                        >
                            <Link :href="item.href || '#'" class="flex w-full items-center gap-3.5">
                                <component
                                    :is="item.icon"
                                    class="size-[1.15rem] shrink-0 stroke-[1.75]"
                                    :class="navIconClass(activeHref === item.href)"
                                />
                                <span class="truncate">{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </template>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
