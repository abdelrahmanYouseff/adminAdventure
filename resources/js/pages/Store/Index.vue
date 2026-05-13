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
    addItem(product.id, product.product_name, Number(product.price), 1, imageUrl(product));
};
</script>

<template>
    <Head title="المتجر - عالم المغامرة" />

    <div class="min-h-screen bg-white dark:bg-neutral-950">
        <StoreHeader />

        <div class="mx-auto max-w-7xl px-3.5 sm:px-6 lg:px-8">
            <div class="flex flex-row-reverse gap-0 lg:gap-10">
                <!-- Sidebar: categories -->
                <aside
                    class="hidden w-[min(100%,22rem)] min-w-[17rem] max-w-[24rem] shrink-0 lg:block lg:ml-10 xl:ml-12"
                    aria-label="تصفية حسب الفئة"
                >
                    <div class="sticky top-24 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                        <h2 class="mb-3 border-b border-neutral-100 pb-2 text-sm font-bold text-neutral-800 dark:border-neutral-700 dark:text-neutral-100">
                            الأصناف
                        </h2>
                        <nav class="flex flex-col gap-1">
                            <button
                                type="button"
                                :class="[
                                    'flex w-full items-center justify-between gap-3 rounded-lg px-3.5 py-3 text-start text-sm transition-colors',
                                    selectedCategoryId === null
                                        ? 'bg-neutral-900 font-medium text-white dark:bg-white dark:text-neutral-900'
                                        : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white'
                                ]"
                                @click="selectedCategoryId = null"
                            >
                                <span class="min-w-0 truncate whitespace-nowrap">الكل</span>
                                <span
                                    :class="[
                                        'shrink-0 tabular-nums text-xs',
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
                                :title="cat.category_name"
                                :class="[
                                    'flex w-full items-center justify-between gap-3 rounded-lg px-3.5 py-3 text-start text-sm transition-colors',
                                    selectedCategoryId === cat.id
                                        ? 'bg-neutral-900 font-medium text-white dark:bg-white dark:text-neutral-900'
                                        : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white'
                                ]"
                                @click="selectedCategoryId = cat.id"
                            >
                                <span class="min-w-0 flex-1 truncate leading-none">{{ cat.category_name }}</span>
                                <span
                                    :class="[
                                        'shrink-0 tabular-nums text-xs',
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
                <main class="min-w-0 flex-1 pb-12 pt-4 sm:pb-16 sm:pt-8 lg:pt-10">
                    <div class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-center sm:justify-between">
                        <h1 class="text-xl font-semibold tracking-tight text-neutral-900 dark:text-white sm:text-2xl lg:text-3xl">
                            {{ selectedCategoryId == null ? 'كل المنتجات' : (categories.find(c => c.id === selectedCategoryId)?.category_name ?? 'المنتجات') }}
                        </h1>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">
                            {{ filteredProducts.length }} منتج
                        </p>
                    </div>

                    <!-- Mobile: بطاقة أصناف منظمة -->
                    <div class="mb-5 lg:hidden">
                        <div class="w-full rounded-xl border border-neutral-200 bg-white p-3.5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:rounded-2xl sm:p-4">
                            <h2 class="mb-3 border-b border-neutral-100 pb-2 text-sm font-bold text-neutral-800 dark:border-neutral-700 dark:text-neutral-100">الأصناف</h2>
                            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:gap-2">
                                <button
                                    type="button"
                                    :class="[
                                        'flex min-h-[3rem] w-full items-center justify-center rounded-xl px-2 py-2 text-center text-xs font-semibold leading-tight transition active:scale-[0.98] sm:inline-flex sm:min-h-[2.75rem] sm:max-w-full sm:px-4 sm:py-2.5 sm:text-sm',
                                        selectedCategoryId === null
                                            ? 'bg-neutral-900 text-white shadow-sm dark:bg-white dark:text-neutral-900'
                                            : 'border border-neutral-200 bg-neutral-50 text-neutral-800 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200'
                                    ]"
                                    @click="selectedCategoryId = null"
                                >
                                    <span class="line-clamp-2 sm:line-clamp-1 sm:max-w-[min(100%,20rem)] sm:truncate">الكل</span>
                                </button>
                                <button
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    type="button"
                                    :title="cat.category_name"
                                    :class="[
                                        'flex min-h-[3rem] w-full items-center justify-center rounded-xl px-2 py-2 text-center text-xs font-semibold leading-tight transition active:scale-[0.98] sm:inline-flex sm:min-h-[2.75rem] sm:max-w-full sm:px-4 sm:py-2.5 sm:text-sm',
                                        selectedCategoryId === cat.id
                                            ? 'bg-neutral-900 text-white shadow-sm dark:bg-white dark:text-neutral-900'
                                            : 'border border-neutral-200 bg-neutral-50 text-neutral-800 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200'
                                    ]"
                                    @click="selectedCategoryId = cat.id"
                                >
                                    <span class="line-clamp-2 sm:line-clamp-1 sm:max-w-[min(100%,20rem)] sm:truncate">{{ cat.category_name }}</span>
                                </button>
                            </div>
                        </div>
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
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 xl:grid-cols-3 [&>article]:flex [&>article]:h-full"
                    >
                        <article
                            v-for="product in filteredProducts"
                            :key="product.id"
                            class="group flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:rounded-2xl sm:border-0 sm:bg-transparent sm:shadow-none"
                        >
                            <Link
                                :href="route('store.product.show', product.id)"
                                class="flex min-h-0 flex-1 flex-col outline-none transition hover:bg-neutral-50/80 focus-visible:ring-2 focus-visible:ring-neutral-400 dark:hover:bg-neutral-800/50 dark:focus-visible:ring-neutral-500"
                            >
                                <div
                                    class="block aspect-[4/3] max-h-[72vw] shrink-0 overflow-hidden rounded-xl bg-neutral-100 dark:bg-neutral-800 sm:aspect-square sm:max-h-none sm:rounded-2xl"
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
                                <div class="flex min-h-0 flex-1 flex-col gap-2 p-3 sm:mt-3 sm:p-0 sm:pt-3">
                                    <span class="line-clamp-2 text-start text-sm font-medium text-neutral-900 dark:text-white sm:text-base">
                                        {{ product.product_name }}
                                    </span>
                                    <p class="text-start text-base font-semibold text-neutral-900 dark:text-white sm:text-lg">
                                        {{ Number(product.price).toLocaleString('ar-SA') }}
                                        <span class="text-xs font-normal text-neutral-500 dark:text-neutral-400 sm:text-sm">ريال</span>
                                    </p>
                                    <p class="text-start text-xs font-medium text-[#FF6B35] sm:hidden">
                                        عرض التفاصيل ←
                                    </p>
                                </div>
                            </Link>
                            <div class="flex flex-col gap-2 border-t border-neutral-100 p-3 dark:border-neutral-700 sm:flex-row sm:border-0 sm:pt-0">
                                <Link
                                    :href="route('store.product.show', product.id)"
                                    class="flex min-h-11 flex-1 items-center justify-center rounded-xl border border-neutral-300 py-3 text-sm font-medium text-neutral-700 transition hover:border-[#FF6B35] hover:text-[#FF6B35] dark:border-neutral-600 dark:text-neutral-200"
                                >
                                    التفاصيل
                                </Link>
                                <Button
                                    class="min-h-11 flex-1 rounded-xl py-3 text-sm font-medium"
                                    @click.stop="addToCart(product)"
                                >
                                    <ShoppingCart class="ms-1.5 h-4 w-4 shrink-0" />
                                    أضف
                                </Button>
                            </div>
                        </article>
                    </div>
                </main>
            </div>
        </div>

        <!-- Footer: minimal Shopify-style -->
        <footer class="mt-12 border-t border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900 sm:mt-16">
            <div class="mx-auto max-w-7xl px-3.5 py-6 sm:px-6 sm:py-8 lg:px-8">
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
