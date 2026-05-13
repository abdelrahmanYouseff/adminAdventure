<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, ShoppingBag, Plus, Minus, Trash2, ArrowLeft, ShoppingCart } from 'lucide-vue-next';
import StoreHeader from '@/components/StoreHeader.vue';
import AppFooter from '@/components/AppFooter.vue';
import { useStoreCart } from '@/composables/useStoreCart';

const { cartItems, count, total, setQuantity, setDuration, removeItem, clearCart, syncFromStorage } =
    useStoreCart();

onMounted(() => syncFromStorage());

const VAT_RATE = 0.15;
const DELIVERY = 0;

const subtotal = total;
const vat = computed(() => subtotal.value * VAT_RATE);
const grandTotal = computed(() => subtotal.value + vat.value + DELIVERY);

const fmt = (n: number) => n.toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const lineTotal = (price: number, qty: number, dur: number) => fmt(price * qty * dur);

const increment = (id: number, qty: number) => setQuantity(id, qty + 1);
const decrement = (id: number, qty: number) => setQuantity(id, Math.max(1, qty - 1));
const incDur = (id: number, dur: number) => setDuration(id, dur + 1);
const decDur = (id: number, dur: number) => setDuration(id, Math.max(1, dur - 1));

const summaryBenefits = [
    { icon: '🚚', title: 'توصيل مجاني', sub: 'حتى بابك في الموعد المحدد' },
    { icon: '🛡️', title: 'معايير أمان معتمدة', sub: 'معدات آمنة ومفحوصة' },
    { icon: '⚙️', title: 'تركيب احترافي', sub: 'فريقنا يتولى الإعداد' },
];
</script>

<template>
    <Head title="السلة — عالم المغامرة">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div
        dir="rtl"
        class="min-h-screen bg-[#f4f6f8] pb-[env(safe-area-inset-bottom,0px)]"
        :class="cartItems.length > 0 ? 'max-lg:pb-[calc(5.5rem+env(safe-area-inset-bottom,0px))]' : ''"
        style="font-family: 'Noto Kufi Arabic', sans-serif"
    >
        <StoreHeader />

        <!-- ── Empty cart ── -->
        <div
            v-if="cartItems.length === 0"
            class="mx-auto max-w-lg px-3.5 py-10 sm:px-6 sm:py-16 lg:px-8"
        >
            <div
                class="flex flex-col items-center gap-6 rounded-2xl border border-neutral-200 bg-white px-5 py-10 text-center shadow-sm sm:px-10 sm:py-14"
            >
                <div
                    class="flex h-24 w-24 items-center justify-center rounded-full bg-[#00a854]/10 sm:h-28 sm:w-28"
                >
                    <ShoppingBag class="h-12 w-12 text-[#00a854]/70 sm:h-14 sm:w-14" />
                </div>
                <div class="space-y-2">
                    <p class="text-xs font-semibold text-[#00a854]">عالم المغامرة</p>
                    <h1 class="text-xl font-extrabold tracking-tight text-neutral-900 sm:text-3xl">
                        سلة التسوق فارغة
                    </h1>
                    <p class="mx-auto max-w-sm text-sm leading-relaxed text-neutral-500 sm:text-base">
                        لم تضف أي ألعاب بعد. تصفح مجموعتنا واختر ما يناسب حفلتك.
                    </p>
                </div>
                <Link
                    href="/store"
                    class="inline-flex min-h-12 w-full max-w-xs touch-manipulation items-center justify-center gap-2 rounded-xl bg-[#00a854] px-8 py-3.5 text-base font-bold text-white shadow-sm transition active:scale-[0.99] hover:bg-[#009648] hover:shadow-md sm:w-auto sm:min-w-[14rem]"
                >
                    <ShoppingCart class="h-5 w-5 shrink-0" />
                    تصفح الألعاب
                </Link>
            </div>
        </div>

        <!-- ── Cart with items ── -->
        <template v-else>
            <div class="border-b border-neutral-200/80 bg-white">
                <div class="mx-auto max-w-7xl px-3.5 py-3 sm:px-6 sm:py-5 lg:px-8">
                    <!-- مسار تنقّل قابل للتمرير على الجوال -->
                    <nav
                        class="-mx-1 flex flex-nowrap items-center gap-1.5 overflow-x-auto px-1 pb-1 text-[13px] text-neutral-500 sm:text-sm [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                        aria-label="مسار الصفحة"
                    >
                        <Link href="/home" class="shrink-0 whitespace-nowrap py-1 transition hover:text-[#00a854]">
                            الرئيسية
                        </Link>
                        <ChevronRight class="h-3 w-3 shrink-0 rotate-180 text-neutral-300" aria-hidden="true" />
                        <Link href="/store" class="shrink-0 whitespace-nowrap py-1 transition hover:text-[#00a854]">
                            المتجر
                        </Link>
                        <ChevronRight class="h-3 w-3 shrink-0 rotate-180 text-neutral-300" aria-hidden="true" />
                        <span class="shrink-0 whitespace-nowrap py-1 font-medium text-neutral-800">سلة التسوق</span>
                    </nav>
                    <div class="mt-3 flex flex-col gap-1 sm:mt-4">
                        <p class="text-xs font-semibold text-[#00a854] sm:text-sm">عالم المغامرة</p>
                        <h1 class="text-xl font-extrabold tracking-tight text-neutral-900 sm:text-3xl lg:text-4xl">
                            سلة التسوق
                        </h1>
                        <p class="text-sm leading-relaxed text-neutral-500 sm:text-base">
                            {{ count }} {{ count === 1 ? 'منتج' : 'منتجات' }} — راجع التفاصيل ثم أكمل الطلب.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mx-auto max-w-7xl px-3.5 py-5 sm:px-6 sm:py-10 lg:px-8 lg:py-12">
                <div class="grid gap-5 lg:grid-cols-3 lg:gap-8 xl:gap-10">
                    <!-- أسطر السلة -->
                    <div class="flex min-w-0 flex-col gap-4 lg:col-span-2 lg:gap-5">
                        <div
                            v-for="item in cartItems"
                            :key="item.product_id"
                            class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm transition hover:shadow-md"
                        >
                            <!-- موبايل: صف أفقي (صورة ثابتة + تفاصيل) — سطح المكتب: نفس الفكرة بمسافات أكبر -->
                            <div class="flex flex-row items-start gap-3 p-3.5 sm:gap-5 sm:p-5">
                                <Link
                                    :href="route('store.product.show', item.product_id)"
                                    class="group shrink-0 touch-manipulation outline-none focus-visible:ring-2 focus-visible:ring-[#00a854] focus-visible:ring-offset-2 rounded-xl"
                                >
                                    <div
                                        class="h-[5.5rem] w-[5.5rem] overflow-hidden rounded-xl border border-neutral-100 bg-[#f4f6f8] sm:h-28 sm:w-28"
                                    >
                                        <img
                                            v-if="item.image"
                                            :src="item.image"
                                            :alt="item.product_name"
                                            class="h-full w-full object-cover transition group-hover:opacity-90"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center bg-gradient-to-br from-[#00a854] to-[#009648] text-xl font-bold text-white sm:text-2xl"
                                        >
                                            {{ item.product_name.charAt(0) }}
                                        </div>
                                    </div>
                                </Link>

                                <div class="flex min-w-0 flex-1 flex-col gap-3 sm:gap-4">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1 text-start">
                                            <Link
                                                :href="route('store.product.show', item.product_id)"
                                                class="line-clamp-2 text-[15px] font-bold leading-snug text-neutral-900 transition hover:text-[#00a854] sm:text-lg"
                                            >
                                                {{ item.product_name }}
                                            </Link>
                                            <p class="mt-1 text-xs text-neutral-500 sm:text-sm">
                                                {{ fmt(item.price) }} ريال / يوم
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="flex h-11 w-11 shrink-0 touch-manipulation items-center justify-center rounded-xl text-red-500 transition hover:bg-red-50 active:bg-red-50"
                                            aria-label="حذف من السلة"
                                            @click="removeItem(item.product_id)"
                                        >
                                            <Trash2 class="h-[18px] w-[18px]" />
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2.5 sm:gap-3">
                                        <div class="min-w-0">
                                            <p class="mb-1.5 text-[11px] font-semibold text-neutral-500 sm:text-xs">
                                                الكمية
                                            </p>
                                            <div
                                                class="flex w-full items-stretch overflow-hidden rounded-xl border border-neutral-200 bg-[#f4f6f8]"
                                            >
                                                <button
                                                    type="button"
                                                    class="flex min-h-11 min-w-11 touch-manipulation items-center justify-center rounded-r-xl transition active:bg-white/80 hover:bg-white"
                                                    aria-label="تقليل الكمية"
                                                    @click="decrement(item.product_id, item.quantity)"
                                                >
                                                    <Minus class="h-4 w-4 text-neutral-700" />
                                                </button>
                                                <span
                                                    class="flex min-h-11 min-w-0 flex-1 items-center justify-center border-x border-neutral-200 px-1 text-sm font-bold tabular-nums text-neutral-900"
                                                >
                                                    {{ item.quantity }}
                                                </span>
                                                <button
                                                    type="button"
                                                    class="flex min-h-11 min-w-11 touch-manipulation items-center justify-center rounded-l-xl transition active:bg-white/80 hover:bg-white"
                                                    aria-label="زيادة الكمية"
                                                    @click="increment(item.product_id, item.quantity)"
                                                >
                                                    <Plus class="h-4 w-4 text-neutral-700" />
                                                </button>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="mb-1.5 text-[11px] font-semibold text-neutral-500 sm:text-xs">
                                                المدة (أيام)
                                            </p>
                                            <div
                                                class="flex w-full items-stretch overflow-hidden rounded-xl border border-neutral-200 bg-[#f4f6f8]"
                                            >
                                                <button
                                                    type="button"
                                                    class="flex min-h-11 min-w-11 touch-manipulation items-center justify-center rounded-r-xl transition active:bg-white/80 hover:bg-white"
                                                    aria-label="تقليل المدة"
                                                    @click="decDur(item.product_id, item.duration)"
                                                >
                                                    <Minus class="h-4 w-4 text-neutral-700" />
                                                </button>
                                                <span
                                                    class="flex min-h-11 min-w-0 flex-1 items-center justify-center border-x border-neutral-200 px-1 text-sm font-bold tabular-nums text-neutral-900"
                                                >
                                                    {{ item.duration }}
                                                </span>
                                                <button
                                                    type="button"
                                                    class="flex min-h-11 min-w-11 touch-manipulation items-center justify-center rounded-l-xl transition active:bg-white/80 hover:bg-white"
                                                    aria-label="زيادة المدة"
                                                    @click="incDur(item.product_id, item.duration)"
                                                >
                                                    <Plus class="h-4 w-4 text-neutral-700" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2 border-t border-neutral-100 pt-3 sm:justify-end"
                                    >
                                        <span class="text-xs text-neutral-400 sm:order-2">
                                            {{ item.quantity }} × {{ item.duration }} يوم
                                        </span>
                                        <span class="text-base font-extrabold tabular-nums text-[#00a854] sm:text-xl">
                                            {{ lineTotal(item.price, item.quantity, item.duration) }} ريال
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="flex min-h-12 touch-manipulation items-center justify-center gap-2 rounded-xl border border-red-200 bg-white py-3 text-sm font-semibold text-red-600 shadow-sm transition hover:bg-red-50 active:scale-[0.99]"
                            @click="clearCart"
                        >
                            <Trash2 class="h-4 w-4 shrink-0" />
                            إفراغ السلة
                        </button>
                    </div>

                    <!-- ملخص الطلب -->
                    <div class="lg:col-span-1">
                        <div
                            class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6 lg:sticky lg:top-24"
                        >
                            <h2 class="border-b border-neutral-100 pb-3 text-base font-extrabold text-neutral-900 sm:text-lg">
                                ملخص الطلب
                            </h2>

                            <div class="flex flex-col gap-2.5 pt-4 text-sm sm:gap-3 sm:pt-5">
                                <div class="flex justify-between gap-3">
                                    <span class="text-neutral-500">المجموع الفرعي</span>
                                    <span class="shrink-0 font-semibold tabular-nums text-neutral-900">{{ fmt(subtotal) }} ريال</span>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <span class="text-neutral-500">التوصيل والتركيب</span>
                                    <span class="shrink-0 font-semibold text-[#00a854]">مجاني</span>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <span class="leading-snug text-neutral-500">ضريبة القيمة المضافة (١٥٪)</span>
                                    <span class="shrink-0 font-semibold tabular-nums text-neutral-900">{{ fmt(vat) }} ريال</span>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-3 border-t border-neutral-100 pt-4 sm:mt-5 sm:pt-5">
                                <span class="text-base font-extrabold text-neutral-900">الإجمالي</span>
                                <span class="text-xl font-extrabold tabular-nums text-[#00a854] sm:text-2xl">
                                    {{ fmt(grandTotal) }} ريال
                                </span>
                            </div>

                            <!-- أزرار الدفع: على المكتب فقط هنا؛ على الجوال الشريط السفلي -->
                            <div class="mt-5 hidden flex-col gap-3 lg:flex">
                                <Link
                                    :href="route('store.checkout')"
                                    class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#00a854] px-4 py-3.5 text-base font-bold text-white shadow-sm transition hover:bg-[#009648] active:scale-[0.99]"
                                >
                                    إتمام الطلب
                                    <ArrowLeft class="h-5 w-5 shrink-0" />
                                </Link>
                                <Link
                                    href="/store"
                                    class="flex min-h-11 w-full items-center justify-center rounded-xl border border-neutral-300 bg-white py-3 text-sm font-semibold text-neutral-700 transition hover:border-neutral-400 hover:bg-[#f4f6f8]"
                                >
                                    متابعة التسوق
                                </Link>
                            </div>

                            <div class="mt-5 flex flex-col gap-2 border-t border-neutral-100 pt-5 lg:mt-6">
                                <p class="text-[11px] font-bold text-neutral-400">لماذا عالم المغامرة؟</p>
                                <div
                                    v-for="b in summaryBenefits"
                                    :key="b.title"
                                    class="flex items-start gap-2.5 rounded-xl bg-[#f4f6f8] px-3 py-2.5 sm:gap-3 sm:px-4 sm:py-3"
                                >
                                    <span class="text-base leading-none sm:text-lg" aria-hidden="true">{{ b.icon }}</span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-neutral-800">{{ b.title }}</p>
                                        <p class="text-xs leading-snug text-neutral-500">{{ b.sub }}</p>
                                    </div>
                                </div>
                            </div>

                            <Link
                                href="/store"
                                class="mt-4 flex min-h-11 w-full touch-manipulation items-center justify-center rounded-xl border border-neutral-300 bg-white py-3 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50 lg:hidden"
                            >
                                متابعة التسوق
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- شريط إجراءات ثابت للجوال: إجمالي + إتمام الطلب -->
            <div
                class="fixed inset-x-0 bottom-0 z-40 border-t border-neutral-200/90 bg-white/95 px-3 pt-3 shadow-[0_-8px_30px_rgba(0,0,0,0.08)] backdrop-blur-md lg:hidden"
                style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom, 0px))"
                role="region"
                aria-label="إتمام الطلب"
            >
                <div class="mx-auto flex max-w-lg items-center gap-3 pb-1">
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-medium text-neutral-500">الإجمالي شامل الضريبة</p>
                        <p class="text-lg font-extrabold tabular-nums leading-tight text-[#00a854]">
                            {{ fmt(grandTotal) }} ريال
                        </p>
                    </div>
                    <Link
                        :href="route('store.checkout')"
                        class="inline-flex min-h-12 min-w-[10.5rem] shrink-0 touch-manipulation items-center justify-center gap-2 rounded-xl bg-[#00a854] px-5 text-sm font-bold text-white shadow-sm transition active:scale-[0.98] hover:bg-[#009648]"
                    >
                        إتمام الطلب
                        <ArrowLeft class="h-4 w-4 shrink-0" />
                    </Link>
                </div>
            </div>
        </template>

        <AppFooter />
    </div>
</template>
