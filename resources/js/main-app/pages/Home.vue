<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import MainAppLayout from '../layouts/MainAppLayout.vue';
import { moduleIcon, moduleTone, type AppModule } from '../lib/modules';

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        role: string;
        role_label: string;
    };
    modules: AppModule[];
    quickStats: Array<{ label: string; value: string | number; hint?: string }>;
}

const props = defineProps<Props>();
const query = ref('');

const filteredModules = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.modules;
    return props.modules.filter(
        (m) => m.title.toLowerCase().includes(q) || m.description.toLowerCase().includes(q),
    );
});

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'صباح الخير';
    if (hour < 18) return 'مساء الخير';
    return 'مرحباً';
});
</script>

<template>
    <Head title="الرئيسية" />

    <MainAppLayout active-nav="home">
        <div class="px-4 pt-5" style="padding-top: max(1.25rem, env(safe-area-inset-top))">
            <header class="mb-5 flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-teal-700/80">{{ greeting }}</p>
                    <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900">
                        {{ user.name }}
                    </h1>
                    <p class="mt-1 inline-flex items-center rounded-full bg-white/80 px-2.5 py-1 text-[11px] font-semibold text-teal-800 ring-1 ring-teal-100">
                        {{ user.role_label }}
                    </p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/80">
                    <img src="/assets/logo.png" alt="" class="h-9 w-9 object-contain" />
                </div>
            </header>

            <section v-if="quickStats.length" class="mb-5 grid grid-cols-2 gap-2.5">
                <div
                    v-for="(stat, index) in quickStats"
                    :key="`${stat.label}-${index}`"
                    class="rounded-2xl border border-white/70 bg-white/90 p-3.5 shadow-sm shadow-slate-900/5"
                >
                    <p class="text-[11px] font-semibold text-slate-500">{{ stat.label }}</p>
                    <p class="mt-1 text-2xl font-extrabold tabular-nums text-slate-900">{{ stat.value }}</p>
                    <p v-if="stat.hint" class="mt-1 text-[10px] text-slate-400">{{ stat.hint }}</p>
                </div>
            </section>

            <label class="mb-4 flex h-11 items-center gap-2 rounded-2xl border border-slate-200/80 bg-white px-3.5 text-slate-400 shadow-sm">
                <Search class="h-4 w-4 shrink-0" />
                <input
                    v-model="query"
                    type="search"
                    placeholder="ابحث في الوحدات..."
                    class="w-full bg-transparent text-sm text-slate-800 outline-none placeholder:text-slate-400"
                />
            </label>

            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-800">وحداتك</h2>
                <Link href="/main-app/modules" class="text-xs font-semibold text-teal-700">عرض الكل</Link>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <Link
                    v-for="mod in filteredModules"
                    :key="mod.key"
                    :href="`/main-app/m/${mod.key}`"
                    class="group relative overflow-hidden rounded-[1.35rem] border border-white/60 bg-white p-3.5 shadow-sm shadow-slate-900/5 transition active:scale-[0.98]"
                >
                    <div
                        class="mb-3 flex h-11 w-11 items-center justify-center rounded-2xl"
                        :class="moduleTone(mod.tone).icon"
                    >
                        <component :is="moduleIcon(mod.icon)" class="h-5 w-5" />
                    </div>
                    <p class="text-sm font-bold text-slate-900">{{ mod.title }}</p>
                    <p class="mt-1 line-clamp-2 text-[11px] leading-relaxed text-slate-500">
                        {{ mod.description }}
                    </p>
                    <ArrowLeft class="absolute end-3 top-3 h-4 w-4 text-slate-300 transition group-hover:text-teal-600" />
                </Link>
            </div>

            <p v-if="filteredModules.length === 0" class="py-10 text-center text-sm text-slate-500">
                لا توجد وحدات مطابقة للبحث.
            </p>
        </div>
    </MainAppLayout>
</template>
