<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Grid2x2, Home, UserRound } from 'lucide-vue-next';

interface Props {
    active: 'home' | 'modules' | 'more';
}

defineProps<Props>();
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-50 border-t border-slate-200/80 bg-white/95 backdrop-blur-xl"
        style="padding-bottom: max(0.45rem, env(safe-area-inset-bottom))"
    >
        <div class="mx-auto grid max-w-lg grid-cols-3 gap-1 px-3 pt-1.5">
            <Link
                href="/main-app"
                class="flex flex-col items-center gap-1 rounded-2xl px-2 py-2.5 text-[11px] font-semibold transition"
                :class="active === 'home' ? 'bg-teal-50 text-teal-800' : 'text-slate-500 hover:bg-slate-50'"
            >
                <Home class="h-5 w-5" />
                الرئيسية
            </Link>
            <Link
                href="/main-app/modules"
                class="flex flex-col items-center gap-1 rounded-2xl px-2 py-2.5 text-[11px] font-semibold transition"
                :class="active === 'modules' ? 'bg-teal-50 text-teal-800' : 'text-slate-500 hover:bg-slate-50'"
            >
                <Grid2x2 class="h-5 w-5" />
                الوحدات
            </Link>
            <Link
                href="/main-app/more"
                class="flex flex-col items-center gap-1 rounded-2xl px-2 py-2.5 text-[11px] font-semibold transition"
                :class="active === 'more' ? 'bg-teal-50 text-teal-800' : 'text-slate-500 hover:bg-slate-50'"
            >
                <UserRound class="h-5 w-5" />
                المزيد
            </Link>
        </div>
    </nav>
</template>
