<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { useAdminTheme } from '@/composables/useAdminTheme';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { isSalla } = useAdminTheme();
</script>

<template>
    <header
        :class="isSalla
            ? 'salla-topbar flex h-16 shrink-0 items-center gap-2 px-4 sm:px-6'
            : 'flex h-14 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background/80 px-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 sm:h-16 sm:px-4'"
    >
        <div class="flex w-full min-w-0 items-center gap-2">
            <SidebarTrigger class="-me-1 shrink-0" :class="isSalla ? 'text-slate-500 hover:bg-slate-100 hover:text-slate-800' : ''" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <div class="min-w-0 overflow-hidden">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </div>
            </template>
        </div>
    </header>
</template>
