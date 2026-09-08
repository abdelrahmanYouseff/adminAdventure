<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Rating {
    id: number;
    score: number | null;
    comment: string | null;
    status: string;
    completed_at: string | null;
    conversation?: { contact?: { phone_number?: string; name?: string } };
    order?: { order_number?: string } | null;
}

const props = defineProps<{
    ratings: { data: Rating[] };
    stats: { average: number; completed: number; total: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'التقارير', href: '/reports' },
    { title: 'تقييم الخدمة', href: '/reports/qa' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="تقرير تقييم الخدمة" />

        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <h1 class="text-2xl font-bold">تقييم الخدمة</h1>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-xs text-zinc-500">المتوسط</div>
                    <div class="text-2xl font-semibold">{{ stats.average || '—' }}</div>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-xs text-zinc-500">مكتمل</div>
                    <div class="text-2xl font-semibold">{{ stats.completed }}</div>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-xs text-zinc-500">الإجمالي</div>
                    <div class="text-2xl font-semibold">{{ stats.total }}</div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 text-start text-xs text-zinc-500 dark:bg-zinc-900">
                        <tr>
                            <th class="px-3 py-2">الجهة</th>
                            <th class="px-3 py-2">الطلب</th>
                            <th class="px-3 py-2">الدرجة</th>
                            <th class="px-3 py-2">التعليق</th>
                            <th class="px-3 py-2">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in ratings.data" :key="row.id" class="border-t border-zinc-100 dark:border-zinc-800">
                            <td dir="ltr" class="px-3 py-2 font-mono text-xs">{{ row.conversation?.contact?.phone_number }}</td>
                            <td class="px-3 py-2">{{ row.order?.order_number || '—' }}</td>
                            <td class="px-3 py-2">{{ row.score ?? '—' }}</td>
                            <td class="px-3 py-2">{{ row.comment || '—' }}</td>
                            <td class="px-3 py-2">{{ row.status }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
