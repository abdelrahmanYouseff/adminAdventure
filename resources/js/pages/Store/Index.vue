<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { ShoppingCart, ImageIcon } from 'lucide-vue-next';
import StoreHeader from '@/components/StoreHeader.vue';
import { useStoreCart } from '@/composables/useStoreCart';

interface Category {
    id: number;
    category_name: string;
}

interface Product {
    id: number;
    product_name: string;
    price: number;
    image: string | null;
    image_url?: string | null;
    category_id: number | null;
    category?: Category | null;
}

const props = defineProps<{
    products: Product[];
    categories: Category[];
}>();

const { count, addItem, syncFromStorage } = useStoreCart();
const selectedCategoryId = ref<number | null>(null);

onMounted(() => syncFromStorage());

const filteredProducts = computed(() => {
    if (selectedCategoryId.value == null) return props.products;
    return props.products.filter((p) => p.category_id === selectedCategoryId.value);
});

const categoryCount = (categoryId: number) =>
    props.products.filter((p) => p.category_id === categoryId).length;

const imageUrl = (product: Product): string | null => {
    if (product.image) return `/storage/${product.image.replace(/^\//, '')}`;
    if (product.image_url) return product.image_url;
    return null;
};

const addToCart = (product: Product) => {
    addItem(product.id, product.product_name, Number(product.price), 1);
};
</script>

<template>
    <Head title="المتجر - عالم المغامرة" />

    <div class="min-h-screen bg-white dark:bg-neutral-950">
        <StoreHeader />

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-row-reverse gap-0 lg:gap-10">
                <!-- Sidebar: categories -->
                <aside
                    class="hidden w-64 shrink-0 lg:block"
                    aria-label="تصفية حسب الفئة"
                >
                    <div class="sticky top-24 pt-10 pb-8">
                        <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            الأصناف
                        </h2>
                        <nav class="flex flex-col gap-0.5">
                            <button
                                type="button"
                                :class="[
                                    'flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-right text-sm transition-colors',
                                    selectedCategoryId === null
                                        ? 'bg-neutral-900 font-medium text-white dark:bg-white dark:text-neutral-900'
                                        : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white'
                                ]"
                                @click="selectedCategoryId = null"
                            >
                                <span>الكل</span>
                                <span
                                    :class="[
                                        'tabular-nums',
                                        selectedCategoryId === null
                                            ? 'text-white/80 dark:text-neutral-900/80'
                                            : 'text-neutral-400 dark:text-neutral-500'
                                    ]"
                                >
                                    {{ products.length }}
                                </span>
                            </button>
                            <button
                                v-for="cat in categories"
                                :key="cat.id"
                                type="button"
                                :class="[
                                    'flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-right text-sm transition-colors',
                                    selectedCategoryId === cat.id
                                        ? 'bg-neutral-900 font-medium text-white dark:bg-white dark:text-neutral-900'
                                        : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white'
                                ]"
                                @click="selectedCategoryId = cat.id"
                            >
                                <span>{{ cat.category_name }}</span>
                                <span
                                    :class="[
                                        'tabular-nums',
                                        selectedCategoryId === cat.id
                                            ? 'text-white/80 dark:text-neutral-900/80'
                                            : 'text-neutral-400 dark:text-neutral-500'
                                    ]"
                                >
                                    {{ categoryCount(cat.id) }}
                                </span>
                            </button>
                        </nav>
                    </div>
                </aside>

                <!-- Main: product grid -->
                <main class="min-w-0 flex-1 pt-8 pb-16 lg:pt-10">
                    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <h1 class="text-2xl font-semibold tracking-tight text-neutral-900 dark:text-white sm:text-3xl">
                            {{ selectedCategoryId == null ? 'كل المنتجات' : (categories.find(c => c.id === selectedCategoryId)?.category_name ?? 'المنتجات') }}
                        </h1>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">
                            {{ filteredProducts.length }} منتج
                        </p>
                    </div>

                    <!-- Mobile category filter -->
                    <div class="mb-6 flex gap-2 overflow-x-auto pb-2 lg:hidden">
                        <button
                            type="button"
                            :class="[
                                'shrink-0 rounded-full px-4 py-2 text-sm font-medium transition-colors',
                                selectedCategoryId === null
                                    ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                                    : 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300'
                            ]"
                            @click="selectedCategoryId = null"
                        >
                            الكل
                        </button>
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            type="button"
                            :class="[
                                'shrink-0 rounded-full px-4 py-2 text-sm font-medium transition-colors',
                                selectedCategoryId === cat.id
                                    ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                                    : 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300'
                            ]"
                            @click="selectedCategoryId = cat.id"
                        >
                            {{ cat.category_name }}
                        </button>
                    </div>

                    <!-- Empty state -->
                    <div
                        v-if="filteredProducts.length === 0"
                        class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/50 py-20 text-center"
                    >
                        <p class="text-neutral-500 dark:text-neutral-400">لا توجد منتجات في هذه الفئة.</p>
                        <Button
                            type="button"
                            variant="outline"
                            class="mt-4 rounded-full"
                            @click="selectedCategoryId = null"
                        >
                            عرض الكل
                        </Button>
                    </div>

                    <!-- Product grid: Shopify-style cards (أزرار السلة في مستوي واحد) -->
                    <div
                        v-else
                        class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3 [&>article]:flex [&>article]:h-full"
                    >
                        <article
                            v-for="product in filteredProducts"
                            :key="product.id"
                            class="group flex flex-col h-full"
                        >
                            <div
                                class="block overflow-hidden rounded-2xl bg-neutral-100 dark:bg-neutral-800 aspect-square shrink-0"
                            >
                                <img
                                    v-if="imageUrl(product)"
                                    :src="imageUrl(product)"
                                    :alt="product.product_name"
                                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center"
                                >
                                    <ImageIcon class="h-16 w-16 text-neutral-400 dark:text-neutral-500" />
                                </div>
                            </div>
                            <div class="mt-4 flex min-h-0 flex-1 flex-col">
                                <Link
                                    :href="route('store.product.show', product.id)"
                                    class="line-clamp-2 text-base font-medium text-neutral-900 dark:text-white hover:text-[#FF6B35] transition-colors"
                                >
                                    {{ product.product_name }}
                                </Link>
                                <p class="mt-1 text-lg font-semibold text-neutral-900 dark:text-white">
                                    {{ Number(product.price).toLocaleString('ar-SA') }}
                                    <span class="text-sm font-normal text-neutral-500 dark:text-neutral-400">ريال</span>
                                </p>
                                <div class="mt-auto flex gap-2">
                                    <Link
                                        :href="route('store.product.show', product.id)"
                                        class="flex flex-1 items-center justify-center rounded-xl border border-neutral-300 py-3 text-sm font-medium text-neutral-700 transition hover:border-[#FF6B35] hover:text-[#FF6B35]"
                                    >
                                        التفاصيل
                                    </Link>
                                    <Button
                                        class="flex-1 rounded-xl py-6 text-sm font-medium"
                                        @click="addToCart(product)"
                                    >
                                        <ShoppingCart class="ml-1.5 h-4 w-4" />
                                        أضف
                                    </Button>
                                </div>
                            </div>
                        </article>
                    </div>
                </main>
            </div>
        </div>

        <!-- Footer: minimal Shopify-style -->
        <footer class="border-t border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 mt-16">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <Link href="/store" class="flex items-center">
                        <AppLogo />
                    </Link>
                    <div class="flex gap-6 text-sm text-neutral-500 dark:text-neutral-400">
                        <Link :href="route('store.index')" class="hover:text-neutral-900 dark:hover:text-white">المتجر</Link>
                        <Link :href="route('store.cart')" class="hover:text-neutral-900 dark:hover:text-white">السلة</Link>
                        <Link href="/privacy" class="hover:text-neutral-900 dark:hover:text-white">سياسة الخصوصية</Link>
                    </div>
                </div>
                <p class="mt-6 text-center text-xs text-neutral-400 dark:text-neutral-500">
                    © {{ new Date().getFullYear() }}. جميع الحقوق محفوظة.
                </p>
            </div>
        </footer>
    </div>
</template>
