<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Plus, Edit, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Category {
    id: number;
    category_name: string;
    is_visible: boolean;
    products_count: number;
    image?: string | null;
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

// Track toggling state per category
const toggling = ref<Set<number>>(new Set());

function toggleVisibility(category: Category) {
    if (toggling.value.has(category.id)) return;
    toggling.value.add(category.id);

    router.patch(
        `/categories/${category.id}/toggle-visibility`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                category.is_visible = !category.is_visible;
            },
            onFinish: () => {
                toggling.value.delete(category.id);
            },
        },
    );
}
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

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-neutral-200 dark:border-neutral-700">
                            <thead>
                                <tr class="bg-neutral-50 dark:bg-neutral-700">
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-right font-medium">
                                        المعرف
                                    </th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-right font-medium">
                                        الصورة
                                    </th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-right font-medium">
                                        اسم الفئة
                                    </th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-right font-medium">
                                        عدد المنتجات
                                    </th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-center font-medium">
                                        ظهور في API
                                    </th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-center font-medium">
                                        الإجراءات
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="categories.length === 0" class="text-center">
                                    <td
                                        colspan="6"
                                        class="border border-neutral-200 dark:border-neutral-600 px-4 py-8 text-neutral-500"
                                    >
                                        لا توجد فئات. اضغط «إضافة فئة جديدة» للبدء.
                                    </td>
                                </tr>

                                <tr
                                    v-for="category in categories"
                                    :key="category.id"
                                    class="hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors"
                                    :class="{ 'opacity-60': !category.is_visible }"
                                >
                                    <!-- ID -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-sm text-neutral-500">
                                        #{{ category.id }}
                                    </td>

                                    <!-- Image -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="flex justify-center">
                                            <img
                                                v-if="category.image"
                                                :src="`/storage/${category.image}`"
                                                :alt="category.category_name"
                                                class="w-12 h-12 object-cover rounded border"
                                            />
                                            <div
                                                v-else
                                                class="w-12 h-12 bg-neutral-200 dark:bg-neutral-600 rounded flex items-center justify-center"
                                            >
                                                <span class="text-neutral-400 text-xs">لا توجد</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Name -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <Link
                                            :href="`/categories/${category.id}`"
                                            class="font-medium text-primary hover:underline"
                                        >
                                            {{ category.category_name }}
                                        </Link>
                                    </td>

                                    <!-- Products count -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 tabular-nums text-center">
                                        {{ category.products_count }}
                                    </td>

                                    <!-- Visibility Toggle -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-center">
                                        <button
                                            type="button"
                                            :disabled="toggling.has(category.id)"
                                            :title="category.is_visible ? 'انقر لإخفاء الفئة من API' : 'انقر لإظهار الفئة في API'"
                                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold transition-all focus:outline-none disabled:opacity-50 disabled:cursor-wait"
                                            :class="
                                                category.is_visible
                                                    ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'bg-neutral-100 text-neutral-500 hover:bg-neutral-200 dark:bg-neutral-700 dark:text-neutral-400'
                                            "
                                            @click="toggleVisibility(category)"
                                        >
                                            <span
                                                v-if="!toggling.has(category.id)"
                                                class="w-1.5 h-1.5 rounded-full"
                                                :class="category.is_visible ? 'bg-green-500' : 'bg-neutral-400'"
                                            />
                                            <svg
                                                v-else
                                                class="w-3 h-3 animate-spin"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                            >
                                                <circle
                                                    class="opacity-25"
                                                    cx="12"
                                                    cy="12"
                                                    r="10"
                                                    stroke="currentColor"
                                                    stroke-width="4"
                                                />
                                                <path
                                                    class="opacity-75"
                                                    fill="currentColor"
                                                    d="M4 12a8 8 0 018-8v8z"
                                                />
                                            </svg>
                                            {{ category.is_visible ? 'ظاهر' : 'مخفي' }}
                                        </button>
                                    </td>

                                    <!-- Actions -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="flex justify-center gap-2">
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
