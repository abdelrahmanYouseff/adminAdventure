<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ShoppingBag, Plus, Minus, Trash2, ArrowLeft, ShoppingCart } from 'lucide-vue-next';
import StoreHeader from '@/components/StoreHeader.vue';
import { useStoreCart } from '@/composables/useStoreCart';

const { cartItems, count, total, setQuantity, setDuration, removeItem, clearCart, syncFromStorage } =
    useStoreCart();

onMounted(() => syncFromStorage());

const VAT_RATE   = 0.15;
const DELIVERY   = 0;   // free delivery

const subtotal  = total;
const vat       = computed(() => subtotal.value * VAT_RATE);
const grandTotal = computed(() => subtotal.value + vat.value + DELIVERY);

const fmt = (n: number) => n.toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const lineTotal = (price: number, qty: number, dur: number) => fmt(price * qty * dur);

const increment = (id: number, qty: number) => setQuantity(id, qty + 1);
const decrement = (id: number, qty: number) => setQuantity(id, Math.max(1, qty - 1));
const incDur    = (id: number, dur: number) => setDuration(id, dur + 1);
const decDur    = (id: number, dur: number) => setDuration(id, Math.max(1, dur - 1));
</script>

<template>
    <Head title="السلة — عالم المغامرة" />

    <div dir="rtl" class="min-h-screen bg-white" style="font-family: 'Noto Kufi Arabic', sans-serif">

        <StoreHeader />

        <!-- ══ EMPTY STATE ══ -->
        <div
            v-if="cartItems.length === 0"
            class="flex min-h-[70vh] items-center justify-center px-4"
        >
            <div class="flex flex-col items-center gap-5 text-center">
                <div
                    class="flex h-32 w-32 items-center justify-center rounded-full"
                    style="background: linear-gradient(135deg, rgba(255,107,53,0.15), rgba(74,144,226,0.15))"
                >
                    <ShoppingBag class="h-16 w-16 text-neutral-400" />
                </div>
                <h1 class="text-3xl font-extrabold text-neutral-900">سلة التسوق فارغة</h1>
                <p class="max-w-xs text-neutral-500">
                    لم تضف أي ألعاب بعد. تصفح مجموعتنا واختر ما يناسب حفلتك!
                </p>
                <Link
                    href="/store"
                    class="mt-2 inline-flex items-center gap-2 rounded-2xl px-8 py-4 text-base font-bold text-white shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl"
                    style="background: linear-gradient(135deg, #FF6B35, #FFD93D)"
                >
                    <ShoppingCart class="h-5 w-5" />
                    تصفح الألعاب
                </Link>
            </div>
        </div>

        <!-- ══ CART WITH ITEMS ══ -->
        <template v-else>
            <!-- Hero banner -->
            <div
                class="py-10"
                style="background: linear-gradient(135deg, #FF6B35 0%, #4A90E2 100%)"
            >
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <h1 class="text-3xl font-extrabold text-white lg:text-4xl">سلة التسوق</h1>
                    <p class="mt-1 text-white/80">
                        {{ count }} {{ count === 1 ? 'منتج' : 'منتجات' }} في سلتك
                    </p>
                </div>
            </div>

            <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="grid gap-8 lg:grid-cols-3">

                    <!-- ── Cart items (2/3) ── -->
                    <div class="flex flex-col gap-4 lg:col-span-2">

                        <div
                            v-for="item in cartItems"
                            :key="item.product_id"
                            class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-neutral-100 transition hover:shadow-md"
                        >
                            <div class="flex gap-4 p-4 sm:gap-5 sm:p-5">
                                <!-- Thumbnail placeholder -->
                                <div
                                    class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-xl text-2xl font-bold text-white"
                                    style="background: linear-gradient(135deg, #FF6B35, #FFD93D)"
                                >
                                    {{ item.product_name.charAt(0) }}
                                </div>

                                <!-- Details -->
                                <div class="flex flex-1 flex-col gap-3 min-w-0">
                                    <!-- Title + delete -->
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="truncate text-base font-bold text-neutral-900">
                                                {{ item.product_name }}
                                            </h3>
                                            <p class="text-sm text-neutral-500">
                                                {{ fmt(item.price) }} ريال / يوم
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-red-500 transition hover:bg-red-50"
                                            aria-label="حذف"
                                            @click="removeItem(item.product_id)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <!-- Controls grid -->
                                    <div class="grid grid-cols-2 gap-3">
                                        <!-- Quantity -->
                                        <div>
                                            <p class="mb-1.5 text-xs text-neutral-500">الكمية</p>
                                            <div class="inline-flex items-center rounded-xl border border-neutral-200 bg-neutral-50">
                                                <button
                                                    type="button"
                                                    class="flex h-9 w-9 items-center justify-center rounded-r-xl transition hover:bg-neutral-100"
                                                    @click="decrement(item.product_id, item.quantity)"
                                                >
                                                    <Minus class="h-3.5 w-3.5 text-neutral-600" />
                                                </button>
                                                <span class="flex h-9 min-w-[2.5rem] items-center justify-center border-x border-neutral-200 px-2 text-sm font-semibold tabular-nums">
                                                    {{ item.quantity }}
                                                </span>
                                                <button
                                                    type="button"
                                                    class="flex h-9 w-9 items-center justify-center rounded-l-xl transition hover:bg-neutral-100"
                                                    @click="increment(item.product_id, item.quantity)"
                                                >
                                                    <Plus class="h-3.5 w-3.5 text-neutral-600" />
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Duration -->
                                        <div>
                                            <p class="mb-1.5 text-xs text-neutral-500">مدة الإيجار (أيام)</p>
                                            <div class="inline-flex items-center rounded-xl border border-neutral-200 bg-neutral-50">
                                                <button
                                                    type="button"
                                                    class="flex h-9 w-9 items-center justify-center rounded-r-xl transition hover:bg-neutral-100"
                                                    @click="decDur(item.product_id, item.duration)"
                                                >
                                                    <Minus class="h-3.5 w-3.5 text-neutral-600" />
                                                </button>
                                                <span class="flex h-9 min-w-[2.5rem] items-center justify-center border-x border-neutral-200 px-2 text-sm font-semibold tabular-nums">
                                                    {{ item.duration }}
                                                </span>
                                                <button
                                                    type="button"
                                                    class="flex h-9 w-9 items-center justify-center rounded-l-xl transition hover:bg-neutral-100"
                                                    @click="incDur(item.product_id, item.duration)"
                                                >
                                                    <Plus class="h-3.5 w-3.5 text-neutral-600" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Line total -->
                                    <div class="text-left">
                                        <span class="text-lg font-extrabold" style="color: #FF6B35">
                                            {{ lineTotal(item.price, item.quantity, item.duration) }} ريال
                                        </span>
                                        <span class="mr-1 text-xs text-neutral-400">
                                            ({{ item.quantity }} × {{ item.duration }} يوم)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Clear cart -->
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 rounded-xl border border-red-200 py-3 text-sm font-medium text-red-600 transition hover:bg-red-50"
                            @click="clearCart"
                        >
                            <Trash2 class="h-4 w-4" />
                            إفراغ السلة
                        </button>
                    </div>

                    <!-- ── Order summary (1/3) ── -->
                    <div>
                        <div class="sticky top-24 rounded-2xl bg-white p-6 shadow-md ring-1 ring-neutral-100">
                            <h2 class="mb-6 text-xl font-extrabold text-neutral-900">ملخص الطلب</h2>

                            <div class="flex flex-col gap-3 border-b border-neutral-100 pb-5">
                                <div class="flex justify-between text-sm">
                                    <span class="text-neutral-500">المجموع الفرعي</span>
                                    <span class="font-semibold">{{ fmt(subtotal) }} ريال</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-neutral-500">التوصيل والتركيب</span>
                                    <span class="font-semibold text-green-600">مجاني</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-neutral-500">ضريبة القيمة المضافة (15%)</span>
                                    <span class="font-semibold">{{ fmt(vat) }} ريال</span>
                                </div>
                            </div>

                            <div class="mt-5 flex justify-between items-center">
                                <span class="text-lg font-extrabold text-neutral-900">الإجمالي</span>
                                <span class="text-2xl font-extrabold" style="color: #FF6B35">
                                    {{ fmt(grandTotal) }} ريال
                                </span>
                            </div>

                            <!-- Checkout CTA -->
                            <Link
                                :href="route('store.checkout')"
                                class="mt-5 flex w-full items-center justify-center gap-2 rounded-2xl py-4 text-base font-extrabold text-white shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl"
                                style="background: linear-gradient(135deg, #FF6B35, #FFD93D)"
                            >
                                إتمام الطلب
                                <ArrowLeft class="h-5 w-5" />
                            </Link>

                            <Link
                                href="/store"
                                class="mt-3 flex w-full items-center justify-center rounded-2xl border border-neutral-200 py-3 text-sm font-medium text-neutral-600 transition hover:border-[#FF6B35] hover:text-[#FF6B35]"
                            >
                                متابعة التسوق
                            </Link>

                            <!-- Benefits -->
                            <div class="mt-6 flex flex-col gap-4 border-t border-neutral-100 pt-5">
                                <div
                                    v-for="b in [
                                        { color: '#6BCF7F', bg: '#6BCF7F15', title: 'توصيل مجاني', sub: 'حتى بابك في الموعد المحدد' },
                                        { color: '#4A90E2', bg: '#4A90E215', title: 'تركيب احترافي شامل', sub: 'نحن نقوم بكل شيء' },
                                        { color: '#FFD93D', bg: '#FFD93D20', title: 'معقّم ومفحوص', sub: 'معايير أمان معتمدة' },
                                    ]"
                                    :key="b.title"
                                    class="flex items-start gap-3"
                                >
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                        :style="`background: ${b.bg}; color: ${b.color}`"
                                    >
                                        ✓
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-neutral-800">{{ b.title }}</p>
                                        <p class="text-xs text-neutral-500">{{ b.sub }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- end grid -->
            </div><!-- end container -->
        </template>

    </div>
</template>
