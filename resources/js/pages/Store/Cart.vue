<script setup lang="ts">
import { onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { useStoreCart } from '@/composables/useStoreCart';
import { ShoppingBag, Plus, Minus, ArrowRight } from 'lucide-vue-next';

const { cartItems, count, total, setQuantity, removeItem, syncFromStorage } = useStoreCart();

onMounted(() => syncFromStorage());

const lineTotal = (price: number, qty: number) => (price * qty).toLocaleString('ar-SA');
const increment = (productId: number, currentQty: number) => setQuantity(productId, currentQty + 1);
const decrement = (productId: number, currentQty: number) =>
    setQuantity(productId, Math.max(1, currentQty - 1));
</script>

<template>
    <Head title="السلة - عالم المغامرة" />

    <div class="min-h-screen bg-neutral-50 dark:bg-neutral-950">
        <header class="sticky top-0 z-10 border-b border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <Link href="/store" class="flex items-center">
                    <AppLogo />
                </Link>
                <Link
                    :href="route('store.index')"
                    class="text-sm font-medium text-neutral-600 underline-offset-4 hover:underline dark:text-neutral-400 dark:hover:text-neutral-200"
                >
                    العودة للمتجر
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:py-12">
            <h1 class="mb-8 text-2xl font-semibold tracking-tight text-neutral-900 dark:text-white sm:text-3xl">
                السلة
                <span v-if="cartItems.length > 0" class="text-neutral-500 dark:text-neutral-400">
                    ({{ count }} {{ count === 1 ? 'منتج' : 'منتجات' }})
                </span>
            </h1>

            <!-- Empty state -->
            <div
                v-if="cartItems.length === 0"
                class="flex flex-col items-center justify-center rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 py-16 px-6 text-center sm:py-24"
            >
                <div class="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-neutral-100 dark:bg-neutral-800">
                    <ShoppingBag class="h-10 w-10 text-neutral-400 dark:text-neutral-500" />
                </div>
                <h2 class="mb-2 text-xl font-semibold text-neutral-900 dark:text-white">
                    سلة التسوق فارغة
                </h2>
                <p class="mb-8 max-w-sm text-neutral-500 dark:text-neutral-400">
                    لم تضف أي منتجات بعد. تصفح المتجر واختر ما يناسبك.
                </p>
                <Button as-child size="lg" class="rounded-full px-8">
                    <Link :href="route('store.index')">تصفح المنتجات</Link>
                </Button>
            </div>

            <!-- Cart with items: Shopify-style two columns -->
            <div v-else class="lg:grid lg:grid-cols-12 lg:gap-x-12 lg:gap-y-8">
                <div class="lg:col-span-7">
                    <ul class="divide-y divide-neutral-200 dark:divide-neutral-800 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 overflow-hidden">
                        <li
                            v-for="item in cartItems"
                            :key="item.product_id"
                            class="flex gap-4 p-4 sm:p-5 sm:gap-6"
                        >
                            <div class="flex flex-1 min-w-0 gap-4 sm:gap-5">
                                <div class="h-24 w-24 shrink-0 rounded-xl bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center overflow-hidden">
                                    <span class="text-2xl font-semibold text-neutral-400 dark:text-neutral-500">
                                        {{ item.product_name.charAt(0) }}
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-neutral-900 dark:text-white truncate">
                                        {{ item.product_name }}
                                    </p>
                                    <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">
                                        {{ Number(item.price).toLocaleString('ar-SA') }} ريال للوحدة
                                    </p>
                                    <div class="mt-3 flex items-center gap-3">
                                        <div class="inline-flex items-center rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800">
                                            <button
                                                type="button"
                                                class="flex h-9 w-9 items-center justify-center text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700 rounded-r-lg transition-colors"
                                                aria-label="تقليل"
                                                @click="decrement(item.product_id, item.quantity)"
                                            >
                                                <Minus class="h-4 w-4" />
                                            </button>
                                            <span class="flex h-9 min-w-[2.25rem] items-center justify-center border-x border-neutral-200 dark:border-neutral-700 px-2 text-sm font-medium tabular-nums">
                                                {{ item.quantity }}
                                            </span>
                                            <button
                                                type="button"
                                                class="flex h-9 w-9 items-center justify-center text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700 rounded-l-lg transition-colors"
                                                aria-label="زيادة"
                                                @click="increment(item.product_id, item.quantity)"
                                            >
                                                <Plus class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <button
                                            type="button"
                                            class="text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 underline-offset-2 hover:underline"
                                            @click="removeItem(item.product_id)"
                                        >
                                            إزالة
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0 text-left">
                                <p class="font-semibold text-neutral-900 dark:text-white">
                                    {{ lineTotal(item.price, item.quantity) }} ريال
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="mt-8 lg:col-span-5 lg:mt-0">
                    <div class="sticky top-24 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-neutral-900 dark:text-white mb-4">
                            ملخص الطلب
                        </h2>
                        <div class="space-y-3 border-b border-neutral-200 dark:border-neutral-800 pb-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-neutral-500 dark:text-neutral-400">المجموع الفرعي</span>
                                <span class="font-medium text-neutral-900 dark:text-white">
                                    {{ total.toLocaleString('ar-SA') }} ريال
                                </span>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-between text-lg font-semibold">
                            <span class="text-neutral-900 dark:text-white">الإجمالي</span>
                            <span class="text-neutral-900 dark:text-white">
                                {{ total.toLocaleString('ar-SA') }} ريال
                            </span>
                        </div>
                        <Button as-child size="lg" class="mt-6 w-full rounded-xl py-6 text-base font-medium" type="button">
                            <Link :href="route('store.checkout')" class="flex items-center justify-center gap-2">
                                إتمام الطلب
                                <ArrowRight class="h-5 w-5" />
                            </Link>
                        </Button>
                        <Link
                            :href="route('store.index')"
                            class="mt-4 block text-center text-sm font-medium text-neutral-600 underline-offset-4 hover:underline dark:text-neutral-400"
                        >
                            متابعة التسوق
                        </Link>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
