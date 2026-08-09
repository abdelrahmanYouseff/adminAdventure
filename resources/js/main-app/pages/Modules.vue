<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import MainAppLayout from '../layouts/MainAppLayout.vue';
import { moduleIcon, moduleTone, type AppModule } from '../lib/modules';

interface Props {
    modules: AppModule[];
    user: {
        name: string;
        role_label: string;
    };
}

const props = defineProps<Props>();
const query = ref('');

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.modules;
    return props.modules.filter(
        (m) => m.title.toLowerCase().includes(q) || m.description.toLowerCase().includes(q),
    );
});
</script>

<template>
    <Head title="الوحدات" />

    <MainAppLayout active-nav="modules">
        <div class="px-4 pt-5" style="padding-top: max(1.25rem, env(safe-area-inset-top))">
            <header class="mb-4">
                <h1 class="text-2xl font-extrabold text-slate-900">كل الوحدات</h1>
                <p class="mt-1 text-sm text-slate-500">
                    حسب صلاحية
                    <span class="font-semibold text-teal-700">{{ user.role_label }}</span>
                    — {{ modules.length }} وحدة
                </p>
            </header>

            <label class="mb-4 flex h-11 items-center gap-2 rounded-2xl border border-slate-200/80 bg-white px-3.5 text-slate-400 shadow-sm">
                <Search class="h-4 w-4 shrink-0" />
                <input
                    v-model="query"
                    type="search"
                    placeholder="ابحث..."
                    class="w-full bg-transparent text-sm text-slate-800 outline-none placeholder:text-slate-400"
                />
            </label>

            <div class="space-y-2.5">
                <Link
                    v-for="mod in filtered"
                    :key="mod.key"
                    :href="`/main-app/m/${mod.key}`"
                    class="flex items-center gap-3 rounded-2xl border border-white/70 bg-white p-3.5 shadow-sm transition active:scale-[0.99]"
                >
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
                        :class="moduleTone(mod.tone).icon"
                    >
                        <component :is="moduleIcon(mod.icon)" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-slate-900">{{ mod.title }}</p>
                        <p class="mt-0.5 line-clamp-1 text-xs text-slate-500">{{ mod.description }}</p>
                    </div>
                    <ChevronLeft class="h-4 w-4 shrink-0 text-slate-300" />
                </Link>
            </div>
        </div>
    </MainAppLayout>
</template>
