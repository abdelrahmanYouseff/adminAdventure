<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Building2, FileSpreadsheet, ImageIcon } from 'lucide-vue-next';

interface Brand {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
    is_active: boolean;
}

defineProps<{ brands: Brand[] }>();
defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="إعدادات عروض الأسعار" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6" dir="rtl">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                <FileSpreadsheet class="h-5 w-5" />
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">إعدادات عروض الأسعار</h1>
                <p class="mt-1 text-sm text-muted-foreground">اختر البراند لتحديث اللوجو الظاهر في عرض السعر</p>
            </div>
        </div>

        <div v-if="brands.length === 0" class="rounded-2xl border border-dashed p-12 text-center">
            <Building2 class="mx-auto h-12 w-12 text-muted-foreground/50" />
            <p class="mt-3 font-semibold">لا توجد براندات بعد</p>
            <p class="mt-1 text-sm text-muted-foreground">أضف البراندات من إدارة البراندات أولاً.</p>
        </div>

        <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="brand in brands"
                :key="brand.id"
                :href="route('settings.quotations.show', brand.slug)"
                class="group overflow-hidden rounded-2xl border bg-card shadow-sm transition hover:-translate-y-1 hover:border-primary/30 hover:shadow-lg"
            >
                <div class="relative flex h-44 items-center justify-center overflow-hidden bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-900">
                    <img
                        v-if="brand.logo_url"
                        :src="brand.logo_url"
                        :alt="brand.name"
                        class="h-full w-full object-contain p-6 transition duration-300 group-hover:scale-105"
                    />
                    <ImageIcon v-else class="h-14 w-14 text-muted-foreground/30" />
                    <span
                        v-if="!brand.is_active"
                        class="absolute end-3 top-3 rounded-full bg-slate-900/80 px-2.5 py-1 text-xs font-medium text-white"
                    >
                        غير نشط
                    </span>
                </div>
                <div class="p-5">
                    <h2 class="truncate text-lg font-bold group-hover:text-primary">{{ brand.name }}</h2>
                    <p class="mt-3 border-t pt-3 text-sm font-medium text-primary">تحديث اللوجو</p>
                </div>
            </Link>
        </div>
    </div>
</template>
