<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowRight, Edit, Package } from 'lucide-vue-next';

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
</script>

<template>
    <Head :title="`فئة: ${category.category_name}`" />

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
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
                    <div v-else class="flex-shrink-0 w-14 h-14 bg-neutral-100 dark:bg-neutral-700 rounded-lg flex items-center justify-center">
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
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">المعرف</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">الصورة</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">اسم المنتج</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">السعر</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">الحالة</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in category.products" :key="product.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-700">
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">{{ product.id }}</td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div v-if="imageUrl(product)" class="flex justify-center">
                                            <img
                                                :src="imageUrl(product)!"
                                                :alt="product.product_name"
                                                class="w-12 h-12 object-cover rounded border"
                                            />
                                        </div>
                                        <div v-else class="flex justify-center">
                                            <div class="w-12 h-12 bg-neutral-200 dark:bg-neutral-600 rounded flex items-center justify-center">
                                                <span class="text-neutral-400 text-xs">—</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 font-medium">{{ product.product_name }}</td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">{{ product.price }}</td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">{{ product.status }}</td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
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
