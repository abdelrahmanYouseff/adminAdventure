<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Plus, Edit, Trash2, ImageIcon } from 'lucide-vue-next';
import { formatDate, formatTime, formatInteger } from '@/lib/formatNumber';

interface Category {
    id: number;
    category_name: string;
    image?: string | null;
    products_count: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    categories: Category[];
}

defineProps<Props>();

defineOptions({
    layout: AppLayout,
});

const deleteForm = useForm({});

const deleteCategory = (categoryId: number) => {
    if (confirm('هل أنت متأكد من حذف هذه الفئة؟')) {
        deleteForm.delete(`/categories/${categoryId}`);
    }
};

</script>

<template>
    <Head title="الأصناف" />

    <div class="py-8 sm:py-12" dir="rtl">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div
                class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900"
            >
                <div class="border-b border-neutral-200 px-5 py-5 dark:border-neutral-700 sm:px-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h1 class="text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100 sm:text-2xl">
                                الأصناف
                            </h1>
                            <p class="mt-1 text-sm leading-relaxed text-neutral-500 dark:text-neutral-400">
                                عرض وتعديل تصنيفات المنتجات مع عدد المنتجات المرتبطة بكل صنف.
                            </p>
                        </div>
                        <Button as-child class="shrink-0">
                            <Link href="/categories/create">
                                <Plus class="size-4 ms-2" />
                                إضافة صنف جديد
                            </Link>
                        </Button>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <Table class="text-sm">
                        <TableHeader>
                            <TableRow class="hover:bg-transparent">
                                <TableHead class="w-[4.5rem] whitespace-nowrap text-center text-xs font-semibold text-neutral-600 dark:text-neutral-300">
                                    #
                                </TableHead>
                                <TableHead class="w-[5.5rem] text-center text-xs font-semibold text-neutral-600 dark:text-neutral-300">
                                    الصورة
                                </TableHead>
                                <TableHead class="min-w-[12rem] text-start text-xs font-semibold text-neutral-600 dark:text-neutral-300">
                                    الصنف والبيانات
                                </TableHead>
                                <TableHead class="w-[7.5rem] text-center text-xs font-semibold text-neutral-600 dark:text-neutral-300">
                                    المنتجات
                                </TableHead>
                                <TableHead class="w-[8.5rem] text-center text-xs font-semibold text-neutral-600 dark:text-neutral-300">
                                    إجراءات
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="categories.length === 0" class="hover:bg-transparent">
                                <TableCell colspan="5" class="h-32 text-center align-middle">
                                    <p class="text-neutral-500 dark:text-neutral-400">
                                        لا توجد أصناف بعد. اضغط «إضافة صنف جديد» للبدء.
                                    </p>
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="category in categories" :key="category.id">
                                <TableCell class="align-middle text-center">
                                    <div class="flex flex-col items-center gap-0.5 py-0.5">
                                        <span class="text-[10px] font-medium uppercase tracking-wide text-neutral-400">معرّف</span>
                                        <span class="font-mono text-sm font-semibold tabular-nums text-neutral-900 dark:text-neutral-100">
                                            {{ formatInteger(category.id) }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell class="align-middle">
                                    <div class="flex justify-center">
                                        <div
                                            v-if="category.image"
                                            class="size-14 shrink-0 overflow-hidden rounded-lg border border-neutral-200 bg-neutral-50 dark:border-neutral-600 dark:bg-neutral-800"
                                        >
                                            <img
                                                :src="`/storage/${category.image}`"
                                                :alt="category.category_name"
                                                class="size-full object-cover"
                                            />
                                        </div>
                                        <div
                                            v-else
                                            class="flex size-14 shrink-0 flex-col items-center justify-center gap-0.5 rounded-lg border border-dashed border-neutral-200 bg-neutral-50 text-neutral-400 dark:border-neutral-600 dark:bg-neutral-800/80 dark:text-neutral-500"
                                        >
                                            <ImageIcon class="size-5 shrink-0" />
                                            <span class="max-w-[3rem] text-center text-[9px] leading-tight">بدون صورة</span>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="align-middle">
                                    <div class="flex max-w-lg flex-col gap-2 py-0.5 text-start">
                                        <Link
                                            :href="`/categories/${category.id}`"
                                            class="text-base font-semibold leading-snug text-neutral-900 underline-offset-4 transition hover:text-primary hover:underline dark:text-neutral-100"
                                        >
                                            {{ category.category_name }}
                                        </Link>
                                        <div class="flex flex-col gap-1 border-s border-neutral-200 ps-3 dark:border-neutral-600">
                                            <span class="text-xs leading-relaxed text-neutral-500 dark:text-neutral-400">
                                                <span class="font-medium text-neutral-600 dark:text-neutral-300">تاريخ الإنشاء:</span>
                                                {{ formatDate(category.created_at) }}
                                                <span v-if="formatTime(category.created_at)" class="text-neutral-400">
                                                    — {{ formatTime(category.created_at) }}
                                                </span>
                                            </span>
                                            <span
                                                v-if="category.updated_at && category.updated_at !== category.created_at"
                                                class="text-xs leading-relaxed text-neutral-500 dark:text-neutral-400"
                                            >
                                                <span class="font-medium text-neutral-600 dark:text-neutral-300">آخر تحديث:</span>
                                                {{ formatDate(category.updated_at) }}
                                                <span v-if="formatTime(category.updated_at)" class="text-neutral-400">
                                                    — {{ formatTime(category.updated_at) }}
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="align-middle text-center">
                                    <div class="flex flex-col items-center gap-1.5 py-0.5">
                                        <span class="text-[11px] font-medium text-neutral-500 dark:text-neutral-400">مرتبطة</span>
                                        <span
                                            class="inline-flex min-h-8 min-w-[2.25rem] items-center justify-center rounded-full bg-neutral-100 px-2.5 text-sm font-bold tabular-nums text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100"
                                        >
                                            {{ formatInteger(category.products_count) }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell class="align-middle">
                                    <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:justify-center">
                                        <Button variant="outline" size="sm" class="h-9 w-full sm:w-auto" as-child>
                                            <Link :href="`/categories/${category.id}/edit`" class="inline-flex items-center justify-center gap-1.5">
                                                <Edit class="size-4 shrink-0" />
                                                <span class="text-xs font-medium sm:hidden">تعديل</span>
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-9 w-full text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/30 sm:w-auto"
                                            :disabled="category.products_count > 0 || deleteForm.processing"
                                            @click="deleteCategory(category.id)"
                                        >
                                            <span class="inline-flex items-center justify-center gap-1.5">
                                                <Trash2 class="size-4 shrink-0" />
                                                <span class="text-xs font-medium sm:hidden">حذف</span>
                                            </span>
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>
    </div>
</template>
