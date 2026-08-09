<script setup lang="ts">
import BottomNav from '../components/BottomNav.vue';

interface Props {
    activeNav?: 'home' | 'modules' | 'more';
    showNav?: boolean;
}

withDefaults(defineProps<Props>(), {
    activeNav: 'home',
    showNav: true,
});
</script>

<template>
    <div class="relative min-h-dvh overflow-x-hidden bg-[#f4f7f6] text-slate-900">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(ellipse_at_top,_rgba(15,118,110,0.16),_transparent_65%)]" />
        <div class="pointer-events-none absolute -start-16 top-40 h-56 w-56 rounded-full bg-teal-200/30 blur-3xl" />
        <div class="pointer-events-none absolute -end-10 top-24 h-44 w-44 rounded-full bg-emerald-200/25 blur-3xl" />

        <div
            class="relative mx-auto min-h-dvh w-full max-w-lg"
            :class="showNav ? 'pb-[calc(5.25rem+env(safe-area-inset-bottom))]' : ''"
        >
            <slot />
        </div>

        <BottomNav v-if="showNav" :active="activeNav" />
    </div>
</template>
