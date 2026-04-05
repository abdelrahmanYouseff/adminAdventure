<script setup lang="ts">
import { onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ShoppingCart } from 'lucide-vue-next';
import { useStoreCart } from '@/composables/useStoreCart';

const { count, syncFromStorage } = useStoreCart();
onMounted(() => syncFromStorage());
</script>

<template>
    <header
        class="sticky top-0 z-50 border-b border-white/20 bg-white/85 backdrop-blur-md"
        style="font-family: 'Noto Kufi Arabic', sans-serif"
        dir="rtl"
    >
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <!-- Logo -->
            <Link href="/home" class="shrink-0">
                <img
                    src="/assets/logo.png"
                    alt="عالم المغامرة"
                    class="h-12 object-contain"
                    onerror="this.style.display='none'"
                />
            </Link>

            <!-- Nav -->
            <nav class="flex items-center gap-3">
                <Link
                    href="/store"
                    class="hidden rounded-full px-4 py-2 text-sm font-medium text-neutral-600 transition hover:text-[#FF6B35] sm:block"
                >
                    المتجر
                </Link>
                <Link
                    href="/home"
                    class="hidden rounded-full px-4 py-2 text-sm font-medium text-neutral-600 transition hover:text-[#FF6B35] sm:block"
                >
                    الرئيسية
                </Link>

                <!-- Cart button with badge -->
                <Link
                    :href="route('store.cart')"
                    class="relative flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-90 hover:shadow-md"
                    style="background: linear-gradient(135deg, #FF6B35, #FFD93D)"
                >
                    <ShoppingCart class="h-4 w-4" />
                    <span class="hidden sm:inline">السلة</span>
                    <!-- Badge -->
                    <span
                        v-if="count > 0"
                        class="absolute -top-1.5 -left-1.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-neutral-900 px-1 text-[11px] font-bold text-white"
                    >
                        {{ count > 99 ? '99+' : count }}
                    </span>
                </Link>
            </nav>
        </div>
    </header>
</template>
