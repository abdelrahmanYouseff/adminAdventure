<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowRight, Edit, Package } from 'lucide-vue-next';
import { ref } from 'vue';

interface Product {
    id: number;
    product_name: string;
    description: string | null;
    price: number;
    image: string | null;
    image_url?: string | null;
    status: string;
    category_id: number | null;
}

interface Category {
    id: number;
    category_name: string;
    image?: string | null;
    products: Product[];
}

interface Props {
    category: Category;
}

const props = defineProps<Props>();

defineOptions({
    layout: AppLayout,
});

const imageUrl = (product: Product) =>
    product.image_url ?? (product.image ? `/storage/${product.image}` : null);

// Track which product IDs are being toggled to prevent double-clicks
const toggling = ref<Set<number>>(new Set());

function toggleStatus(product: Product) {
    if (toggling.value.has(product.id)) return;
    toggling.value.add(product.id);

    router.patch(
        `/products/${product.id}/toggle-status`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                // Update local state immediately (optimistic)
                product.status = product.status === 'active' ? 'inactive' : 'active';
            },
            onFinish: () => {
                toggling.value.delete(product.id);
            },
        },
    );
}

const formatPrice = (price: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'SAR' }).format(price);
</script>

<template>
    <Head :title="`فئة: ${category.category_name}`" />

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6">
                <Button variant="ghost" size="sm" as-child class="text-muted-foreground mb-2">
                    <Link href="/categories" class="flex items-center gap-1">
                        <ArrowRight class="w-4 h-4" />
                        كل الفئات
                    </Link>
                </Button>
                <div class="flex items-center gap-3">
                    <div v-if="category.image" class="flex-shrink-0">
                        <img
                            :src="`/storage/${category.image}`"
                            :alt="category.category_name"
                            class="w-14 h-14 object-cover rounded-lg border"
                        />
                    </div>
                    <div
                        v-else
                        class="flex-shrink-0 w-14 h-14 bg-neutral-100 dark:bg-neutral-700 rounded-lg flex items-center justify-center"
                    >
                        <Package class="w-7 h-7 text-neutral-400" />
                    </div>
                    <h1 class="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ category.category_name }}
                    </h1>
                    <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                        ({{ category.products.length }} منتج)
                    </span>
                </div>
            </div>

            <!-- Products Table -->
            <div class="overflow-hidden bg-white shadow-sm dark:bg-neutral-800 sm:rounded-lg">
                <div class="p-6 text-neutral-900 dark:text-neutral-100">
                    <h2 class="text-lg font-medium mb-4">المنتجات في هذه الفئة</h2>

                    <div v-if="category.products.length === 0" class="text-center py-12 text-neutral-500">
                        لا توجد منتجات في هذه الفئة.
                        <Button as-child variant="link" class="mt-2">
                            <Link href="/products">إضافة منتجات</Link>
                        </Button>
                    </div>

                    <div v-else class="overflow-x-auto">
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
                                        اسم المنتج
                                    </th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-right font-medium">
                                        السعر
                                    </th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-center font-medium">
                                        الحالة
                                    </th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-center font-medium">
                                        الإجراءات
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="product in category.products"
                                    :key="product.id"
                                    class="hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors"
                                    :class="{ 'opacity-60': product.status === 'inactive' }"
                                >
                                    <!-- ID -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-sm text-neutral-500">
                                        #{{ product.id }}
                                    </td>

                                    <!-- Image — always shown regardless of status -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="flex justify-center">
                                            <img
                                                v-if="imageUrl(product)"
                                                :src="imageUrl(product)!"
                                                :alt="product.product_name"
                                                class="w-12 h-12 object-cover rounded border"
                                            />
                                            <div
                                                v-else
                                                class="w-12 h-12 bg-neutral-200 dark:bg-neutral-600 rounded flex items-center justify-center"
                                            >
                                                <Package class="w-5 h-5 text-neutral-400" />
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Name -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 font-medium">
                                        {{ product.product_name }}
                                    </td>

                                    <!-- Price -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 tabular-nums" dir="ltr">
                                        {{ formatPrice(Number(product.price)) }}
                                    </td>

                                    <!-- Status Toggle -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-center">
                                        <button
                                            type="button"
                                            :disabled="toggling.has(product.id)"
                                            :title="product.status === 'active' ? 'انقر لتعطيل المنتج' : 'انقر لتفعيل المنتج'"
                                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold transition-all focus:outline-none disabled:opacity-50 disabled:cursor-wait"
                                            :class="
                                                product.status === 'active'
                                                    ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'bg-neutral-100 text-neutral-500 hover:bg-neutral-200 dark:bg-neutral-700 dark:text-neutral-400'
                                            "
                                            @click="toggleStatus(product)"
                                        >
                                            <!-- Active dot / spinner -->
                                            <span
                                                v-if="!toggling.has(product.id)"
                                                class="w-1.5 h-1.5 rounded-full"
                                                :class="product.status === 'active' ? 'bg-green-500' : 'bg-neutral-400'"
                                            />
                                            <svg
                                                v-else
                                                class="w-3 h-3 animate-spin"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                            >
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                <path
                                                    class="opacity-75"
                                                    fill="currentColor"
                                                    d="M4 12a8 8 0 018-8v8z"
                                                />
                                            </svg>
                                            {{ product.status === 'active' ? 'نشط' : 'غير نشط' }}
                                        </button>
                                    </td>

                                    <!-- Actions -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-center">
                                        <Button variant="outline" size="sm" as-child>
                                            <Link :href="`/products/${product.id}/edit`">
                                                <Edit class="w-4 h-4" />
                                            </Link>
                                        </Button>
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
