<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ShoppingCart, Star, Shield, Check, ChevronRight, ArrowRight, Package, Clock, Truck } from 'lucide-vue-next';
import StoreHeader from '@/components/StoreHeader.vue';
import { useStoreCart } from '@/composables/useStoreCart';

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

onMounted(() => syncFromStorage());

const duration  = ref(1);
const added     = ref(false);

const imageUrl = (p: Product): string | null => {
    if (p.image) return `/storage/${p.image.replace(/^\//, '')}`;
    if (p.image_url) return p.image_url;
    return null;
};

const mainImage = computed(() => imageUrl(props.product));

const total = computed(() => (Number(props.product.price) * duration.value).toLocaleString('ar-SA'));

const durations = [1, 2, 3, 4, 5, 7, 10, 14];

function addToCart() {
    addItem(
        props.product.id,
        props.product.product_name,
        Number(props.product.price),
        duration.value,
        mainImage.value,
    );
    added.value = true;
    setTimeout(() => (added.value = false), 2000);
}

const trustBadges = [
    { icon: Shield, label: 'معايير أمان معتمدة', color: '#6BCF7F' },
    { icon: Check,  label: 'معقّم ونظيف',         color: '#4A90E2' },
    { icon: Check,  label: 'مؤمَّن بالكامل',       color: '#9B6EFF' },
];
</script>

<template>
    <Head :title="`${product.product_name} — عالم المغامرة`" />

    <div dir="rtl" class="min-h-screen bg-white" style="font-family: 'Noto Kufi Arabic', sans-serif">

        <!-- ── Header ── -->
        <StoreHeader />

        <!-- ── Breadcrumb ── -->
        <div class="border-b border-neutral-100 bg-neutral-50">
            <div class="mx-auto flex max-w-7xl items-center gap-2 px-4 py-3 text-sm text-neutral-500 sm:px-6 lg:px-8">
                <Link href="/home" class="transition hover:text-[#FF6B35]">الرئيسية</Link>
                <ChevronRight class="h-3.5 w-3.5 rotate-180" />
                <Link href="/store" class="transition hover:text-[#FF6B35]">المتجر</Link>
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
                        style="background: linear-gradient(135deg, #FF6B35, #FFD93D, #4A90E2)"
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
                                style="background: linear-gradient(135deg, #FFD93D11, #FF6B3511)"
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
                            style="background: #FF6B3515; color: #FF6B35"
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
                                class="h-5 w-5"
                                style="fill: #FFD93D; color: #FFD93D"
                            />
                        </div>
                        <span class="text-sm font-semibold text-neutral-700">5.0</span>
                        <span class="text-sm text-neutral-400">(تقييمات العملاء)</span>
                    </div>

                    <!-- Price -->
                    <div class="flex items-baseline gap-2">
                        <span class="text-4xl font-extrabold" style="color: #FF6B35">
                            {{ Number(product.price).toLocaleString('ar-SA') }}
                        </span>
                        <span class="text-lg text-neutral-500">ريال / يوم</span>
                    </div>

                    <!-- Description -->
                    <p v-if="product.description" class="text-base leading-relaxed text-neutral-600">
                        {{ product.description }}
                    </p>

                    <!-- ── Booking card ── -->
                    <div
                        class="rounded-2xl border-2 p-6"
                        style="background: linear-gradient(135deg, #FF6B3508, #4A90E208); border-color: #FF6B3520"
                    >
                        <!-- Duration selector -->
                        <label class="mb-3 block text-sm font-bold text-neutral-700">
                            مدة الإيجار
                        </label>
                        <div class="mb-5 flex flex-wrap gap-2">
                            <button
                                v-for="d in durations"
                                :key="d"
                                type="button"
                                class="rounded-xl border-2 px-4 py-2 text-sm font-semibold transition-all"
                                :style="duration === d
                                    ? 'border-color:#FF6B35; background:#FF6B35; color:#fff'
                                    : 'border-color:#e5e7eb; color:#4b5563'"
                                @click="duration = d"
                            >
                                {{ d }} {{ d === 1 ? 'يوم' : 'أيام' }}
                            </button>
                        </div>

                        <!-- Total -->
                        <div class="mb-5 flex items-center justify-between rounded-xl bg-white px-4 py-3 shadow-sm">
                            <span class="text-sm text-neutral-500">الإجمالي</span>
                            <span class="text-xl font-extrabold" style="color: #FF6B35">
                                {{ total }} ريال
                            </span>
                        </div>

                        <!-- Add to cart button -->
                        <button
                            type="button"
                            class="flex w-full items-center justify-center gap-3 rounded-xl py-4 text-base font-bold text-white shadow-lg transition-all hover:-translate-y-0.5 hover:shadow-xl active:scale-95"
                            :style="added
                                ? 'background: linear-gradient(135deg,#6BCF7F,#4ade80)'
                                : 'background: linear-gradient(135deg,#FF6B35,#FFD93D)'"
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
                            <Clock class="h-4 w-4" style="color:#FFD93D" />
                            إعداد وتركيب شامل
                        </div>
                        <div class="flex items-center gap-2 rounded-full bg-neutral-50 px-4 py-2 text-sm text-neutral-600">
                            <Package class="h-4 w-4" style="color:#6BCF7F" />
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
                                style="background: linear-gradient(135deg,#FF6B35,#FFD93D)"
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
                            <Check class="mt-0.5 h-5 w-5 shrink-0" style="color:#6BCF7F" />
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
                        style="color:#FF6B35"
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
                                style="background: linear-gradient(135deg,#FFD93D22,#FF6B3522)"
                            >
                                <span class="text-4xl">🎮</span>
                            </div>
                        </div>
                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <p class="text-sm font-bold text-neutral-900 line-clamp-2">{{ rel.product_name }}</p>
                            <p class="text-sm font-semibold" style="color:#FF6B35">
                                {{ Number(rel.price).toLocaleString('ar-SA') }} ريال/يوم
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
                    <Link href="/store" class="hover:text-[#FF6B35]">المتجر</Link>
                    <Link href="/store/cart" class="hover:text-[#FF6B35]">السلة</Link>
                    <Link href="/privacy" class="hover:text-[#FF6B35]">سياسة الخصوصية</Link>
                </div>
                <p class="hidden text-xs text-neutral-400 sm:block">
                    © {{ new Date().getFullYear() }} عالم المغامرة
                </p>
            </div>
        </footer>

    </div>
</template>
