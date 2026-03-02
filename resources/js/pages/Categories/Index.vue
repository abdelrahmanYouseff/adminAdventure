<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Plus, Edit, Trash2 } from 'lucide-vue-next';

interface Category {
    id: number;
    category_name: string;
    products_count: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    categories: Category[];
}

const props = defineProps<Props>();

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
    <Head title="الفئات" />

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-neutral-800 sm:rounded-lg">
                <div class="p-6 text-neutral-900 dark:text-neutral-100">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-semibold">الفئات</h1>
                        <Button as-child>
                            <Link href="/categories/create">
                                <Plus class="w-4 h-4 ml-2" />
                                إضافة فئة جديدة
                            </Link>
                        </Button>
                    </div>

                    <!-- جدول الفئات -->
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-neutral-200 dark:border-neutral-700">
                            <thead>
                                <tr class="bg-neutral-50 dark:bg-neutral-700">
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">المعرف</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">الصورة</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">اسم الفئة</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">عدد المنتجات</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="categories.length === 0" class="text-center">
                                    <td colspan="5" class="border border-neutral-200 dark:border-neutral-600 px-4 py-8 text-neutral-500">
                                        لا توجد فئات. اضغط «إضافة فئة جديدة» للبدء.
                                    </td>
                                </tr>
                                <tr v-for="category in categories" :key="category.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-700">
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">{{ category.id }}</td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div v-if="category.image" class="flex justify-center">
                                            <img 
                                                :src="`/storage/${category.image}`" 
                                                :alt="category.category_name"
                                                class="w-12 h-12 object-cover rounded border"
                                            />
                                        </div>
                                        <div v-else class="flex justify-center">
                                            <div class="w-12 h-12 bg-gray-200 dark:bg-gray-600 rounded flex items-center justify-center">
                                                <span class="text-gray-400 text-xs">لا توجد</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <Link :href="`/categories/${category.id}`" class="font-medium text-primary hover:underline">
                                            {{ category.category_name }}
                                        </Link>
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">{{ category.products_count }}</td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="flex space-x-2">
                                            <Button variant="outline" size="sm" as-child>
                                                <Link :href="`/categories/${category.id}/edit`">
                                                    <Edit class="w-4 h-4" />
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="text-red-600 hover:text-red-700"
                                                :disabled="category.products_count > 0 || deleteForm.processing"
                                                @click="deleteCategory(category.id)"
                                            >
                                                <Trash2 class="w-4 h-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
