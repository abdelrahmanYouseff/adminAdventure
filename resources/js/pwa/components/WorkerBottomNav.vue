<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { HardHat, History } from 'lucide-vue-next';

interface Props {
    active: 'current' | 'history';
    currentCount?: number;
}

withDefaults(defineProps<Props>(), {
    currentCount: 0,
});
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-50 border-t border-slate-200/80 bg-white/95 backdrop-blur-md"
        style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom))"
    >
        <div class="mx-auto grid max-w-md grid-cols-2 gap-1 px-3 pt-2">
            <Link
                href="/worker-app"
                class="relative flex flex-col items-center gap-1 rounded-2xl px-3 py-2.5 text-xs font-semibold transition"
                :class="active === 'current' ? 'bg-sky-50 text-sky-700' : 'text-slate-500 hover:bg-slate-50'"
            >
                <span class="relative">
                    <HardHat class="h-5 w-5" />
                    <span
                        v-if="currentCount > 0"
                        class="absolute -end-2.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-sky-600 px-1 text-[10px] font-bold text-white"
                    >
                        {{ currentCount > 99 ? '99+' : currentCount }}
                    </span>
                </span>
                التركيبات الحالية
            </Link>

            <Link
                href="/worker-app/history"
                class="flex flex-col items-center gap-1 rounded-2xl px-3 py-2.5 text-xs font-semibold transition"
                :class="active === 'history' ? 'bg-slate-900 text-white' : 'text-slate-500 hover:bg-slate-50'"
            >
                <History class="h-5 w-5" />
                التركيبات السابقة
            </Link>
        </div>
    </nav>
</template>
