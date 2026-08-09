<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowUpLeft, ChevronLeft, ExternalLink, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import MainAppLayout from '../layouts/MainAppLayout.vue';
import { badgeToneClass, moduleIcon, moduleTone, type AppModule } from '../lib/modules';

interface ListItem {
    id: string | number;
    title: string;
    subtitle?: string | null;
    meta?: string | null;
    badge?: string | null;
    badge_tone?: string | null;
    href?: string | null;
    image?: string | null;
}

interface Props {
    module: AppModule;
    search: string;
    stats: Array<{ label: string; value: string | number }>;
    items: ListItem[];
    empty_message: string;
    desktop_path: string;
    can_open_desktop: boolean;
}

const props = defineProps<Props>();
const searchInput = ref(props.search || '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.search,
    (value) => {
        searchInput.value = value || '';
    },
);

function applySearch() {
    router.get(
        `/main-app/m/${props.module.key}`,
        { search: searchInput.value.trim() || undefined },
        { preserveState: true, replace: true },
    );
}

function onSearchInput() {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(applySearch, 350);
}
</script>

<template>
    <Head :title="module.title" />

    <MainAppLayout active-nav="modules">
        <div class="px-4 pt-4" style="padding-top: max(1rem, env(safe-area-inset-top))">
            <header class="mb-4 flex items-center gap-2">
                <Link
                    href="/main-app/modules"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm"
                >
                    <ChevronLeft class="h-5 w-5 rotate-180" />
                </Link>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl"
                            :class="moduleTone(module.tone).icon"
                        >
                            <component :is="moduleIcon(module.icon)" class="h-4 w-4" />
                        </div>
                        <div class="min-w-0">
                            <h1 class="truncate text-lg font-extrabold text-slate-900">{{ module.title }}</h1>
                            <p class="truncate text-xs text-slate-500">{{ module.description }}</p>
                        </div>
                    </div>
                </div>
            </header>

            <div v-if="stats.length" class="mb-3 flex gap-2 overflow-x-auto pb-1">
                <div
                    v-for="(stat, index) in stats"
                    :key="`${stat.label}-${index}`"
                    class="min-w-[6.5rem] shrink-0 rounded-2xl border border-white/70 bg-white px-3 py-2.5 shadow-sm"
                >
                    <p class="text-[10px] font-semibold text-slate-500">{{ stat.label }}</p>
                    <p class="mt-0.5 text-lg font-extrabold tabular-nums text-slate-900">{{ stat.value }}</p>
                </div>
            </div>

            <label class="mb-3 flex h-11 items-center gap-2 rounded-2xl border border-slate-200/80 bg-white px-3.5 text-slate-400 shadow-sm">
                <Search class="h-4 w-4 shrink-0" />
                <input
                    v-model="searchInput"
                    type="search"
                    placeholder="بحث..."
                    class="w-full bg-transparent text-sm text-slate-800 outline-none placeholder:text-slate-400"
                    @input="onSearchInput"
                    @keydown.enter.prevent="applySearch"
                />
            </label>

            <a
                v-if="can_open_desktop"
                :href="desktop_path"
                class="mb-3 flex items-center justify-between gap-2 rounded-2xl bg-gradient-to-l from-teal-700 to-teal-900 px-4 py-3 text-white shadow-md shadow-teal-900/20"
            >
                <div>
                    <p class="text-sm font-bold">فتح النسخة الكاملة</p>
                    <p class="text-[11px] text-teal-100/80">كل الأدوات والإجراءات المتقدمة</p>
                </div>
                <ExternalLink class="h-4 w-4 shrink-0 opacity-90" />
            </a>

            <div v-if="items.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-white/70 px-4 py-12 text-center text-sm text-slate-500">
                {{ empty_message }}
            </div>

            <div v-else class="space-y-2.5">
                <component
                    :is="item.href ? 'a' : 'div'"
                    v-for="item in items"
                    :key="item.id"
                    :href="item.href || undefined"
                    class="flex items-center gap-3 rounded-2xl border border-white/70 bg-white p-3.5 shadow-sm transition active:scale-[0.99]"
                >
                    <div
                        v-if="item.image"
                        class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-slate-100"
                    >
                        <img :src="item.image" alt="" class="h-full w-full object-cover" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-bold text-slate-900" dir="auto">{{ item.title }}</p>
                            <span
                                v-if="item.badge"
                                class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset"
                                :class="badgeToneClass(item.badge_tone)"
                            >
                                {{ item.badge }}
                            </span>
                        </div>
                        <p v-if="item.subtitle" class="mt-0.5 truncate text-xs text-slate-500">{{ item.subtitle }}</p>
                        <p v-if="item.meta" class="mt-1 text-xs font-semibold tabular-nums text-slate-700" dir="ltr">
                            {{ item.meta }}
                        </p>
                    </div>
                    <ArrowUpLeft v-if="item.href" class="h-4 w-4 shrink-0 text-slate-300" />
                </component>
            </div>
        </div>
    </MainAppLayout>
</template>
