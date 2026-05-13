<script setup lang="ts">
import { onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ShoppingCart, Search } from 'lucide-vue-next';
import { useStoreCart } from '@/composables/useStoreCart';

const { count, syncFromStorage } = useStoreCart();
onMounted(() => syncFromStorage());
</script>

<template>
    <header
        class="sticky top-0 z-50 border-b border-neutral-200/90 bg-white pt-[env(safe-area-inset-top,0px)] shadow-[0_1px_0_rgba(0,0,0,0.04)]"
        style="font-family: 'Noto Kufi Arabic', sans-serif"
        dir="rtl"
    >
        <div class="mx-auto flex min-h-[3.5rem] max-w-7xl items-center gap-2 px-3.5 py-2 sm:h-[4.25rem] sm:gap-3 sm:px-6 lg:px-8">
            <Link href="/home" class="shrink-0 touch-manipulation py-1">
                <img
                    src="/assets/logo.png"
                    alt="عالم المغامرة"
                    class="h-9 object-contain sm:h-11"
                    onerror="this.style.display='none'"
                />
            </Link>

            <Link
                href="/store"
                class="mx-auto hidden min-h-10 min-w-0 max-w-xl flex-1 items-center gap-2 rounded-lg border border-neutral-200 bg-[#f4f6f8] px-4 py-2.5 text-sm text-neutral-500 transition hover:border-neutral-300 hover:bg-neutral-100 md:flex"
            >
                <Search class="h-4 w-4 shrink-0 text-neutral-400" />
                <span class="truncate">ابحث عن لعبة أو تصنيف...</span>
            </Link>

            <nav class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                <Link
                    href="/store"
                    class="hidden min-h-10 items-center rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900 sm:inline-flex"
                >
                    المتجر
                </Link>
                <Link
                    href="/home"
                    class="hidden min-h-10 items-center rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900 sm:inline-flex"
                >
                    الرئيسية
                </Link>

                <Link
                    href="/store"
                    class="inline-flex min-h-11 min-w-11 touch-manipulation items-center justify-center rounded-lg border border-neutral-200 bg-white text-neutral-600 transition active:bg-neutral-100 md:hidden"
                    aria-label="بحث في المتجر"
                >
                    <Search class="h-5 w-5" />
                </Link>

                <Link
                    :href="route('store.cart')"
                    class="relative inline-flex min-h-11 touch-manipulation items-center justify-center gap-1.5 rounded-lg bg-[#00a854] px-3.5 py-2 text-sm font-bold text-white shadow-sm transition active:scale-[0.98] hover:bg-[#009648] hover:shadow sm:gap-2 sm:px-4 sm:py-2.5"
                >
                    <ShoppingCart class="h-5 w-5 shrink-0 sm:h-4 sm:w-4" />
                    <span class="hidden sm:inline">السلة</span>
                    <span
                        v-if="count > 0"
                        class="absolute -top-1.5 -left-1.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-neutral-900 px-1 text-[11px] font-bold text-white ring-2 ring-white"
                    >
                        {{ count > 99 ? '99+' : count }}
                    </span>
                </Link>
            </nav>
        </div>
    </header>
</template>
