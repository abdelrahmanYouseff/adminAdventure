<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ShoppingCart, Star, Shield, Check, ChevronRight, ArrowRight, Package, Clock, Truck } from 'lucide-vue-next';
import StoreHeader from '@/components/StoreHeader.vue';
import StoreLoginModal from '@/components/StoreLoginModal.vue';
import { useStoreCart } from '@/composables/useStoreCart';
import { useStoreAuth } from '@/composables/useStoreAuth';
import { formatAmount, formatPrice } from '@/lib/formatNumber';

interface Category {
    id: number;
    category_name: string;
}

interface Product {
    id: number;
    product_name: string;
    description: string | null;
    price: number;
    status: string;
    image: string | null;
    image_url?: string | null;
    category_id: number | null;
    category?: Category | null;
}

const props = defineProps<{
    product: Product;
    related: Product[];
}>();

const { addItem, syncFromStorage } = useStoreCart();
const { requireLogin } = useStoreAuth();
const loginModalOpen = ref(false);

function openLoginModal() {
    loginModalOpen.value = true;
}

function guardAction(action: () => void) {
    requireLogin(action, openLoginModal);
}

onMounted(() => syncFromStorage());

const duration  = ref(1);
const added     = ref(false);

const imageUrl = (p: Product): string | null => {
    if (p.image) return `/storage/${p.image.replace(/^\//, '')}`;
    if (p.image_url) return p.image_url;
    return null;
};

const mainImage = computed(() => imageUrl(props.product));

const total = computed(() => formatPrice(Number(props.product.price) * duration.value));

const durations = [1, 2, 3, 4, 5, 7, 10, 14];

function addToCart() {
    guardAction(() => {
        addItem(
            props.product.id,
            props.product.product_name,
            Number(props.product.price),
            duration.value,
            mainImage.value,
        );
        added.value = true;
        setTimeout(() => (added.value = false), 2000);
    });
}

function onDurationSelect(e: Event) {
    duration.value = Number((e.target as HTMLSelectElement).value);
}

const trustBadges = [
    { icon: Shield, label: 'معايير أمان معتمدة', color: '#7ab8e8' },
    { icon: Check,  label: 'معقّم ونظيف',         color: '#4A90E2' },
    { icon: Check,  label: 'مؤمَّن بالكامل',       color: '#9B6EFF' },
];
</script>

<template>
    <Head :title="`${product.product_name} — عالم المغامرة`" />

    <div dir="rtl" class="min-h-screen bg-white" style="font-family: 'Noto Kufi Arabic', sans-serif">

        <!-- ── Header ── -->
        <StoreHeader :show-login-button="true" @open-login="openLoginModal" />
        <StoreLoginModal v-model:open="loginModalOpen" />

        <!-- ── Breadcrumb ── -->
        <div class="border-b border-neutral-100 bg-neutral-50">
            <div class="mx-auto flex max-w-7xl items-center gap-2 px-4 py-3 text-sm text-neutral-500 sm:px-6 lg:px-8">
                <Link href="/home" class="transition hover:text-[#3b89d2]">الرئيسية</Link>
                <ChevronRight class="h-3.5 w-3.5 rotate-180" />
                <Link href="/store" class="transition hover:text-[#3b89d2]">المتجر</Link>
                <ChevronRight class="h-3.5 w-3.5 rotate-180" />
                <span class="font-medium text-neutral-800 line-clamp-1">{{ product.product_name }}</span>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">

            <!-- ══════════════════════════════════
                 MAIN GRID
            ══════════════════════════════════ -->
            <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-16" dir="ltr">

                <!-- ── Left: Image ── -->
                <div>
                    <!-- Gradient border frame -->
                    <div
                        class="rounded-3xl p-[3px] shadow-2xl"
                        style="background: linear-gradient(135deg, #3b89d2, #6baee3, #4A90E2)"
                    >
                        <div
                            class="group overflow-hidden rounded-[calc(1.5rem-3px)] bg-white"
                            style="aspect-ratio: 1/1"
                        >
                            <img
                                v-if="mainImage"
                                :src="mainImage"
                                :alt="product.product_name"
                                class="h-full w-full object-contain p-4 transition-transform duration-500 group-hover:scale-105"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center"
                                style="background: linear-gradient(135deg, #6baee311, #3b89d211)"
                            >
                                <span class="text-8xl">🎮</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Right: Info ── -->
                <div dir="rtl" class="flex flex-col gap-6">

                    <!-- Category badge -->
                    <div v-if="product.category" class="flex">
                        <span
                            class="rounded-full px-4 py-1.5 text-xs font-semibold"
                            style="background: #3b89d215; color: #3b89d2"
                        >
                            {{ product.category.category_name }}
                        </span>
                    </div>

                    <!-- Title -->
                    <h1 class="text-3xl font-extrabold leading-snug text-neutral-900 lg:text-4xl">
                        {{ product.product_name }}
                    </h1>

                    <!-- Stars -->
                    <div class="flex items-center gap-3">
                        <div class="flex">
                            <Star
                                v-for="s in 5"
                                :key="s"
                                class="h-5 w-5 fill-amber-400 text-amber-400"
                            />
                        </div>
                        <span class="text-sm font-semibold text-neutral-700">5.0</span>
                        <span class="text-sm text-neutral-400">(تقييمات العملاء)</span>
                    </div>

                    <!-- Price -->
                    <div class="flex items-baseline gap-2 font-bold">
                        <span class="text-4xl font-extrabold" style="color: #3b89d2">
                            {{ formatAmount(product.price) }}
                        </span>
                        <span class="text-lg font-bold text-neutral-600">ريال / يوم</span>
                    </div>

                    <!-- Description -->
                    <p v-if="product.description" class="text-base leading-relaxed text-neutral-600">
                        {{ product.description }}
                    </p>

                    <!-- ── Booking card ── -->
                    <div
                        class="rounded-2xl border-2 p-6"
                        style="background: linear-gradient(135deg, #3b89d208, #4A90E208); border-color: #3b89d220"
                    >
                        <!-- Duration selector -->
                        <label
                            class="mb-3 block text-sm font-bold text-neutral-700"
                            for="rental-duration"
                        >
                            مدة الإيجار
                        </label>
                        <select
                            id="rental-duration"
                            :value="duration"
                            class="mb-5 w-full cursor-pointer appearance-none rounded-xl border-2 border-neutral-200 bg-white bg-[length:1.25rem] bg-[position:left_0.75rem_center] bg-no-repeat py-3 ps-4 pe-10 text-base font-bold text-neutral-900 shadow-sm transition focus:border-[#3b89d2] focus:outline-none focus:ring-2 focus:ring-[#3b89d2]/25"
                            style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2720%27 height=%2720%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%2352555b%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3E%3Cpath d=%27m6 9 6 6 6-6%27/%3E%3C/svg%3E')"
                            dir="rtl"
                            @change="onDurationSelect"
                        >
                            <option
                                v-for="d in durations"
                                :key="d"
                                :value="d"
                                class="font-sans"
                            >
                                {{ d }} {{ d === 1 ? 'يوم' : 'أيام' }}
                            </option>
                        </select>

                        <!-- Total -->
                        <div class="mb-5 flex items-center justify-between rounded-xl bg-white px-4 py-3 shadow-sm">
                            <span class="text-sm text-neutral-500">الإجمالي</span>
                            <span class="text-xl font-extrabold" style="color: #3b89d2">
                                {{ total }} ريال
                            </span>
                        </div>

                        <!-- Add to cart button -->
                        <button
                            type="button"
                            class="flex w-full items-center justify-center gap-3 rounded-xl py-4 text-base font-bold text-white shadow-lg transition-all hover:-translate-y-0.5 hover:shadow-xl active:scale-95"
                            :style="added
                                ? 'background: linear-gradient(135deg,#7ab8e8,#6baee3)'
                                : 'background: linear-gradient(135deg,#3b89d2,#6baee3)'"
                            @click="addToCart"
                        >
                            <component :is="added ? Check : ShoppingCart" class="h-5 w-5" />
                            {{ added ? 'تمت الإضافة ✓' : `أضف إلى السلة — ${total} ريال` }}
                        </button>
                    </div>

                    <!-- Trust badges -->
                    <div class="grid grid-cols-3 gap-3">
                        <div
                            v-for="badge in trustBadges"
                            :key="badge.label"
                            class="flex flex-col items-center gap-2 rounded-2xl bg-neutral-50 p-3 text-center"
                        >
                            <component
                                :is="badge.icon"
                                class="h-5 w-5"
                                :style="`color: ${badge.color}`"
                            />
                            <span class="text-xs font-medium text-neutral-600">{{ badge.label }}</span>
                        </div>
                    </div>

                    <!-- Delivery info pills -->
                    <div class="flex flex-wrap gap-3">
                        <div class="flex items-center gap-2 rounded-full bg-neutral-50 px-4 py-2 text-sm text-neutral-600">
                            <Truck class="h-4 w-4" style="color:#4A90E2" />
                            توصيل مجاني
                        </div>
                        <div class="flex items-center gap-2 rounded-full bg-neutral-50 px-4 py-2 text-sm text-neutral-600">
                            <Clock class="h-4 w-4" style="color:#6baee3" />
                            إعداد وتركيب شامل
                        </div>
                        <div class="flex items-center gap-2 rounded-full bg-neutral-50 px-4 py-2 text-sm text-neutral-600">
                            <Package class="h-4 w-4" style="color:#7ab8e8" />
                            استلام بعد الفعالية
                        </div>
                    </div>

                </div><!-- end right -->
            </div>

            <!-- ══════════════════════════════════
                 INFO CARDS
            ══════════════════════════════════ -->
            <div class="mt-14 grid gap-6 md:grid-cols-2">

                <!-- How it works -->
                <div class="rounded-2xl bg-neutral-50 p-7">
                    <h2 class="mb-5 text-xl font-bold text-neutral-900">كيف يعمل الإيجار؟</h2>
                    <ol class="flex flex-col gap-4">
                        <li
                            v-for="(step, idx) in [
                                'اختر المنتج ومدة الإيجار وأضفه للسلة',
                                'أكمل بياناتك وتاريخ الفعالية عند الدفع',
                                'نحضر ونركّب المعدات قبل موعد الحفلة',
                                'بعد انتهاء الفعالية نعود لاستلام كل شيء',
                            ]"
                            :key="step"
                            class="flex items-start gap-4"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                                style="background: linear-gradient(135deg,#3b89d2,#6baee3)"
                            >
                                {{ idx + 1 }}
                            </span>
                            <span class="pt-1 text-sm text-neutral-600">{{ step }}</span>
                        </li>
                    </ol>
                </div>

                <!-- Safety info -->
                <div class="rounded-2xl bg-neutral-50 p-7">
                    <h2 class="mb-5 text-xl font-bold text-neutral-900">معلومات السلامة</h2>
                    <ul class="flex flex-col gap-3">
                        <li
                            v-for="info in [
                                'مصنوعة من مواد آمنة ومعتمدة لألعاب الأطفال',
                                'يتم تعقيم وتنظيف كل المعدات قبل كل استخدام',
                                'الإشراف الكامل يقع على مسؤولية أهل الحفل',
                                'الحد الأقصى للطاقة الاستيعابية مذكور في التفاصيل',
                                'لا تُستخدم في الأماكن المغلقة بدون تهوية كافية',
                            ]"
                            :key="info"
                            class="flex items-start gap-3"
                        >
                            <Check class="mt-0.5 h-5 w-5 shrink-0" style="color:#7ab8e8" />
                            <span class="text-sm text-neutral-600">{{ info }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- ══════════════════════════════════
                 RELATED PRODUCTS
            ══════════════════════════════════ -->
            <div v-if="related.length > 0" class="mt-16">
                <div class="mb-8 flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-neutral-900">منتجات من نفس التصنيف</h2>
                    <Link
                        href="/store"
                        class="flex items-center gap-1 text-sm font-semibold transition hover:opacity-80"
                        style="color:#3b89d2"
                    >
                        عرض الكل
                        <ArrowRight class="h-4 w-4 rotate-180" />
                    </Link>
                </div>

                <div class="grid grid-cols-2 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="rel in related"
                        :key="rel.id"
                        :href="route('store.product.show', rel.id)"
                        class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-md transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
                    >
                        <div class="overflow-hidden" style="aspect-ratio:4/3">
                            <img
                                v-if="imageUrl(rel)"
                                :src="imageUrl(rel)"
                                :alt="rel.product_name"
                                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-110"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center"
                                style="background: linear-gradient(135deg,#6baee322,#3b89d222)"
                            >
                                <span class="text-4xl">🎮</span>
                            </div>
                        </div>
                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <p class="text-sm font-bold text-neutral-900 line-clamp-2">{{ rel.product_name }}</p>
                            <p class="text-sm font-semibold" style="color:#3b89d2">
                                {{ formatAmount(rel.price) }} ريال/يوم
                            </p>
                        </div>
                    </Link>
                </div>
            </div>

        </div><!-- end container -->

        <!-- ── Footer ── -->
        <footer class="mt-16 border-t border-neutral-200 bg-white py-8">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link href="/home">
                    <img src="/assets/logo.png" alt="" class="h-10 object-contain" onerror="this.style.display='none'" />
                </Link>
                <div class="flex gap-6 text-sm text-neutral-500">
                    <Link href="/store" class="hover:text-[#3b89d2]">المتجر</Link>
                    <Link href="/store/cart" class="hover:text-[#3b89d2]">السلة</Link>
                    <Link href="/privacy" class="hover:text-[#3b89d2]">سياسة الخصوصية</Link>
                </div>
                <p class="hidden text-xs text-neutral-400 sm:block">
                    © {{ new Date().getFullYear() }} عالم المغامرة
                </p>
            </div>
        </footer>

    </div>
</template>
