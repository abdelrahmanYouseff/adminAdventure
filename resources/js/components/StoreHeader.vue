<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ShoppingCart, User } from 'lucide-vue-next';
import { useStoreCart } from '@/composables/useStoreCart';
import StoreUserMenu from '@/components/StoreUserMenu.vue';

withDefaults(
    defineProps<{
        showLoginButton?: boolean;
    }>(),
    { showLoginButton: false },
);

const emit = defineEmits<{
    openLogin: [];
}>();

const page = usePage();
const isLoggedIn = computed(() => Boolean(page.props.auth?.user));

const { count, syncFromStorage } = useStoreCart();
onMounted(() => syncFromStorage());
</script>

<template>
    <header
        class="sticky top-0 z-50 border-b border-neutral-200/90 bg-white pt-[env(safe-area-inset-top,0px)] shadow-[0_1px_0_rgba(0,0,0,0.04)]"
        style="font-family: 'Noto Kufi Arabic', sans-serif"
        dir="rtl"
    >
        <div class="mx-auto flex h-12 max-w-7xl items-center gap-2 px-3 py-1 sm:h-14 sm:gap-2.5 sm:px-6 lg:px-8">
            <Link href="/home" class="shrink-0 touch-manipulation">
                <img
                    src="/assets/logo.png"
                    alt="عالم المغامرة"
                    class="h-7 object-contain sm:h-8"
                    onerror="this.style.display='none'"
                />
            </Link>

            <nav class="ms-auto flex shrink-0 items-center gap-1.5 sm:gap-2">
                <Link
                    href="/home"
                    class="hidden min-h-8 items-center rounded-lg px-2.5 py-1.5 text-xs font-medium text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900 sm:inline-flex sm:text-sm"
                >
                    الرئيسية
                </Link>
                <StoreUserMenu v-if="showLoginButton && isLoggedIn" />

                <button
                    v-if="showLoginButton && !isLoggedIn"
                    type="button"
                    class="inline-flex min-h-9 touch-manipulation items-center justify-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition hover:bg-neutral-50 active:scale-[0.98] sm:px-3 sm:text-sm"
                    @click="emit('openLogin')"
                >
                    <User class="h-4 w-4 shrink-0" />
                    <span>تسجيل الدخول</span>
                </button>

                <Link
                    :href="route('store.cart')"
                    class="relative inline-flex min-h-9 touch-manipulation items-center justify-center gap-1.5 rounded-lg bg-[#3b89d2] px-3 py-1.5 text-xs font-bold text-white shadow-sm transition active:scale-[0.98] hover:bg-[#2f6eb0] sm:gap-2 sm:px-3.5 sm:py-2 sm:text-sm"
                >
                    <ShoppingCart class="h-4 w-4 shrink-0" />
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
