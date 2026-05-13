<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppFooter from '@/components/AppFooter.vue';
import StoreHeader from '@/components/StoreHeader.vue';
import { useStoreCart } from '@/composables/useStoreCart';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { FreeMode } from 'swiper/modules';
import 'swiper/css';
import { BadgePercent, LayoutGrid, ShoppingCart, Star } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';

interface Category {
    id: number;
    category_name: string;
}

interface Product {
    id: number;
    product_name: string;
    description: string | null;
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

onMounted(() => {
    syncFromStorage();
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    (entry.target as HTMLElement).style.opacity = '1';
                    (entry.target as HTMLElement).style.transform = 'translateY(0)';
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.12 },
    );
    document.querySelectorAll('.aw-reveal').forEach((el) => {
        (el as HTMLElement).style.opacity = '0';
        (el as HTMLElement).style.transform = 'translateY(28px)';
        (el as HTMLElement).style.transition = 'opacity 0.65s ease, transform 0.65s ease';
        observer.observe(el);
    });
    document.querySelectorAll('.aw-reveal-left').forEach((el) => {
        (el as HTMLElement).style.opacity = '0';
        (el as HTMLElement).style.transform = 'translateX(-36px)';
        (el as HTMLElement).style.transition = 'opacity 0.7s ease, transform 0.7s ease';
        observer.observe(el);
    });
    document.querySelectorAll('.aw-reveal-right').forEach((el) => {
        (el as HTMLElement).style.opacity = '0';
        (el as HTMLElement).style.transform = 'translateX(36px)';
        (el as HTMLElement).style.transition = 'opacity 0.7s ease, transform 0.7s ease';
        observer.observe(el);
    });
});

const imageUrl = (product: Product): string | null => {
    if (product.image) return `/storage/${product.image.replace(/^\//, '')}`;
    if (product.image_url) return product.image_url;
    return null;
};

const selectedCategoryHome = ref<number | null>(null);

/** معاينة التصنيفات في الصفحة؛ الباقي يُعرض عبر الحوار / الورقة السفلية */
const SIDEBAR_PREVIEW = 6;

const swiperCategoryModules = [FreeMode];

const categoriesDialogOpen = ref(false);
const categorySheetOpen = ref(false);

const categoriesWithCounts = computed(() =>
    props.categories
        .map((c) => ({
            ...c,
            count: props.products.filter((p) => p.category_id === c.id).length,
        }))
        .sort((a, b) => b.count - a.count),
);

/** إن كان التصنيف المختار خارج المعاينة يُدرَج تلقائياً ليبقى التمييز واضحاً */
const sidebarCategoriesShown = computed(() => {
    const sorted = categoriesWithCounts.value;
    const preview = sorted.slice(0, SIDEBAR_PREVIEW);
    const sid = selectedCategoryHome.value;
    if (sid === null) {
        return preview;
    }
    const selectedRow = sorted.find((c) => c.id === sid);
    if (!selectedRow) {
        return preview;
    }
    if (preview.some((c) => c.id === sid)) {
        return preview;
    }
    return [selectedRow, ...preview.filter((c) => c.id !== sid).slice(0, SIDEBAR_PREVIEW - 1)];
});

function pickCategory(id: number | null) {
    selectedCategoryHome.value = id;
}

function pickCategoryCloseSheet(id: number | null) {
    selectedCategoryHome.value = id;
    categorySheetOpen.value = false;
}

function pickCategoryCloseDialog(id: number | null) {
    selectedCategoryHome.value = id;
    categoriesDialogOpen.value = false;
}

const displayProducts = computed(() => {
    const list = selectedCategoryHome.value === null
        ? props.products
        : props.products.filter((p) => p.category_id === selectedCategoryHome.value);
    return list.slice(0, 9);
});

/** عدد تكرارات عنصر الشريط لملء العرض دون فراغات */
const tickerSegmentCount = 16;

const addedIds = ref<Set<number>>(new Set());
function addToCartHome(product: Product) {
    addItem(product.id, product.product_name, Number(product.price), 1, imageUrl(product));
    addedIds.value = new Set([...addedIds.value, product.id]);
    setTimeout(() => {
        addedIds.value.delete(product.id);
        addedIds.value = new Set(addedIds.value);
    }, 1800);
}

const features = [
    { icon: '🏷️', title: 'أفضل العروض والأسعار', desc: 'أسعار تنافسية وعروض موسمية لكل الميزانيات' },
    { icon: '🛡️', title: 'أمان ونظافة', desc: 'ألعاب آمنة ومعقّمة تماشياً مع أعلى معايير الجودة' },
    { icon: '⏰', title: 'الالتزام بالمواعيد', desc: 'نصل قبل موعد الحفلة ونجمع كل شيء بعد انتهائها' },
    { icon: '💳', title: 'كل وسائل الدفع', desc: 'ادفع بطاقة أو نقداً أو عبر التطبيق بكل سهولة' },
];
</script>

<template>
    <Head>
        <title>عالم المغامرة — تأجير ألعاب ترفيهية للأطفال</title>
        <meta name="description" content="اجعل كل حفلة لا تُنسى مع عالم المغامرة — أفضل خدمة تأجير ألعاب ترفيهية في المملكة" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div dir="rtl" class="min-h-screen bg-[#f4f6f8] pb-[env(safe-area-inset-bottom,0px)]" style="font-family: 'Noto Kufi Arabic', sans-serif">

        <StoreHeader />

        <!-- Hero -->
        <section class="border-b border-neutral-200/80 bg-white py-8 sm:py-12 lg:py-20">
            <div class="mx-auto max-w-7xl px-3.5 sm:px-6 lg:px-8">
                <div class="grid items-center gap-8 lg:grid-cols-2 lg:gap-16" dir="ltr">
                    <div class="aw-reveal-left relative order-2 lg:order-1">
                        <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-[#f4f6f8] shadow-sm sm:rounded-2xl">
                            <img
                                src="/assets/pic01.png"
                                alt="طفلة تلعب في نطاطة ملونة"
                                class="h-full max-h-[38vh] min-h-[200px] w-full object-cover sm:max-h-none sm:min-h-[320px] lg:min-h-[400px]"
                                onerror="this.parentElement.style.background='#eef1f4';this.style.display='none'"
                            />
                        </div>
                        <div
                            class="absolute bottom-3 left-3 flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-2 shadow-sm sm:bottom-5 sm:left-5 sm:gap-2.5 sm:rounded-xl sm:px-4 sm:py-3"
                        >
                            <span class="text-base sm:text-lg">⭐</span>
                            <div>
                                <p class="text-xs font-bold text-neutral-900 sm:text-sm">4.9</p>
                                <p class="text-[10px] text-neutral-500 sm:text-xs">+١٬٥٠٠ تقييم</p>
                            </div>
                        </div>
                    </div>

                    <div class="aw-reveal-right order-1 flex flex-col gap-5 sm:gap-6 lg:order-2" dir="rtl">
                        <div>
                            <p class="mb-1.5 text-xs font-semibold text-[#00a854] sm:mb-2 sm:text-sm">عالم المغامرة</p>
                            <h1 class="text-2xl font-extrabold leading-snug tracking-tight text-neutral-900 sm:text-4xl lg:text-5xl xl:text-[3.25rem]">
                                اجعل كل حفلة
                                <br />
                                <span class="text-[#00a854]">لا تُنسى</span>
                            </h1>
                            <p class="mt-2 text-sm font-medium text-neutral-600 sm:mt-3 sm:text-lg">
                                تأجير ألعاب ترفيهية للأطفال بسهولة كمتجرك المفضل
                            </p>
                        </div>
                        <p class="text-sm leading-relaxed text-neutral-600 sm:text-base lg:text-lg">
                            نوفّر لك أفضل ألعاب الترفيه للأطفال بأعلى معايير الأمان — من نطاطات هوائية وألعاب مائية إلى ملاعب ترفيهية متكاملة.
                        </p>
                        <div class="flex flex-col gap-2.5 sm:flex-row sm:flex-wrap sm:gap-3">
                            <Link
                                href="/store"
                                class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-[#00a854] px-6 py-3 text-sm font-bold text-white shadow-sm transition active:scale-[0.99] hover:bg-[#009648] hover:shadow sm:w-auto sm:px-7 sm:text-base"
                            >
                                <ShoppingCart class="h-5 w-5 shrink-0" />
                                تصفح الألعاب
                            </Link>
                            <a
                                href="#exceptional-features"
                                class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-neutral-300 bg-white px-6 py-3 text-sm font-bold text-neutral-800 transition active:scale-[0.99] hover:border-neutral-400 hover:bg-neutral-50 sm:w-auto sm:px-7 sm:text-base"
                            >
                                الباقات والمزايا
                            </a>
                        </div>
                        <div class="flex flex-wrap gap-2 sm:gap-3">
                            <span class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-neutral-200 bg-[#f4f6f8] px-3 py-2 text-xs font-medium text-neutral-700 sm:px-4 sm:text-sm">
                                <span>🛡️</span> معايير أمان معتمدة
                            </span>
                            <span class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-neutral-200 bg-[#f4f6f8] px-3 py-2 text-xs font-medium text-neutral-700 sm:px-4 sm:text-sm">
                                <span>🚚</span> توصيل مجاني
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- شريط إعلانات (مثل شريط الأخبار) -->
        <div class="overflow-hidden border-y border-neutral-900 bg-black" role="marquee" aria-label="عروض وتنبيهات">
            <div
                class="aw-news-ticker-track flex w-max"
                :style="{ '--aw-ticker-segments': tickerSegmentCount }"
            >
                <div
                    v-for="n in tickerSegmentCount"
                    :key="n"
                    class="flex shrink-0 items-center gap-3 px-5 py-2.5 text-sm font-medium text-white sm:gap-4 sm:px-8 sm:py-3 sm:text-base"
                >
                    <span class="whitespace-nowrap">استأجر الان واستمتع بأحلي الالعاب</span>
                    <BadgePercent class="h-5 w-5 shrink-0 text-red-500 sm:h-6 sm:w-6" stroke-width="2.25" aria-hidden="true" />
                    <span class="whitespace-nowrap">مع باقاتنا اللعب ولا زحلي</span>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════
             3 + 4. CATEGORIES & PRODUCTS (مثل صفحة /products)
        ═══════════════════════════════════════════ -->
        <section class="border-t border-neutral-200/80 bg-[#f4f6f8] py-10 sm:py-14 lg:py-20">
            <div class="mx-auto max-w-7xl px-3.5 sm:px-6 lg:px-8">

                <div class="aw-reveal mb-6 text-center sm:mb-8">
                    <h2 class="text-xl font-bold text-neutral-900 sm:text-3xl lg:text-4xl">الألعاب والتصنيفات</h2>
                    <p class="mt-1.5 text-sm text-neutral-500 sm:mt-2 sm:text-base">اختر التصنيف واستعرض الألعاب</p>
                </div>

                <div class="flex flex-col gap-6 sm:gap-8 lg:flex-row lg:gap-8">

                    <aside class="hidden w-[min(100%,22rem)] min-w-[17rem] max-w-[24rem] shrink-0 lg:block lg:me-10 xl:me-12">
                        <div class="sticky top-24 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
                            <h3 class="mb-1 border-b border-neutral-100 pb-2 ps-1 text-sm font-bold text-neutral-800">
                                التصنيفات
                            </h3>
                            <p class="mb-3 ps-1 text-[11px] leading-relaxed text-neutral-500">
                                الأكثر شيوعاً — لعرض الكل اضغط الزر بالأسفل.
                            </p>
                            <nav class="flex flex-col gap-1">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 rounded-lg px-3.5 py-3 text-sm font-medium transition"
                                    :class="selectedCategoryHome === null
                                        ? 'bg-[#00a854] text-white shadow-sm'
                                        : 'text-neutral-600 hover:bg-[#f4f6f8]'"
                                    @click="pickCategory(null)"
                                >
                                    <span class="min-w-0 truncate whitespace-nowrap text-start">الكل</span>
                                    <span class="shrink-0 tabular-nums text-xs opacity-90">{{ products.length }}</span>
                                </button>
                                <button
                                    v-for="cat in sidebarCategoriesShown"
                                    :key="cat.id"
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 rounded-lg px-3.5 py-3 text-sm font-medium transition"
                                    :class="selectedCategoryHome === cat.id
                                        ? 'bg-[#00a854] text-white shadow-sm'
                                        : 'text-neutral-600 hover:bg-[#f4f6f8]'"
                                    :title="cat.category_name"
                                    @click="pickCategory(cat.id)"
                                >
                                    <span class="min-w-0 flex-1 truncate text-start leading-none">{{ cat.category_name }}</span>
                                    <span class="shrink-0 tabular-nums text-xs opacity-90">{{ cat.count }}</span>
                                </button>
                                <button
                                    v-if="categories.length > SIDEBAR_PREVIEW"
                                    type="button"
                                    class="mt-1 flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-[#00a854]/40 bg-[#00a854]/5 px-3 py-2.5 text-sm font-semibold text-[#00a854] transition hover:bg-[#00a854]/10"
                                    @click="categoriesDialogOpen = true"
                                >
                                    <LayoutGrid class="h-4 w-4 shrink-0 opacity-90" />
                                    كل التصنيفات
                                    <span class="tabular-nums text-xs opacity-80">({{ categories.length }})</span>
                                </button>
                            </nav>
                        </div>
                    </aside>

                    <!-- ── Main area ── -->
                    <div class="flex-1 min-w-0">

                        <!-- التصنيفات — موبايل: شريط أفقي سريع + ورقة سفلية لكل التصنيفات -->
                        <div class="mb-5 lg:hidden">
                            <div class="w-full rounded-xl border border-neutral-200 bg-white p-3 shadow-sm sm:rounded-2xl sm:p-4">
                                <div class="mb-2 flex items-center justify-between gap-2 border-b border-neutral-100 pb-2">
                                    <h3 class="text-sm font-bold text-neutral-800">التصنيفات</h3>
                                    <span class="text-[11px] text-neutral-400">{{ categories.length }} تصنيف</span>
                                </div>
                                <div class="relative min-w-0 w-full">
                                    <!-- سلايدر أفقي (Swiper) — سحب يمين/يسار بكل التصنيفات -->
                                    <Swiper
                                        :modules="swiperCategoryModules"
                                        :slides-per-view="'auto'"
                                        :space-between="8"
                                        :free-mode="true"
                                        :dir="'rtl'"
                                        class="home-category-swiper -mx-1 px-2 pb-2 pt-0.5"
                                        aria-label="سلايدر التصنيفات"
                                    >
                                        <SwiperSlide class="!w-auto">
                                            <button
                                                type="button"
                                                class="inline-flex min-h-11 max-w-[min(100vw-4rem,14rem)] touch-manipulation items-center rounded-full border px-4 py-2.5 text-xs font-bold transition active:scale-[0.98]"
                                                :class="selectedCategoryHome === null
                                                    ? 'border-[#00a854] bg-[#00a854] text-white shadow-sm'
                                                    : 'border-neutral-200 bg-[#f4f6f8] text-neutral-800'"
                                                @click="pickCategory(null)"
                                            >
                                                الكل
                                                <span class="mr-1 tabular-nums opacity-80">({{ products.length }})</span>
                                            </button>
                                        </SwiperSlide>
                                        <SwiperSlide
                                            v-for="cat in categoriesWithCounts"
                                            :key="cat.id"
                                            class="!w-auto"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex min-h-11 max-w-[min(100vw-4rem,14rem)] touch-manipulation items-center truncate rounded-full border px-4 py-2.5 text-xs font-semibold transition active:scale-[0.98]"
                                                :class="selectedCategoryHome === cat.id
                                                    ? 'border-[#00a854] bg-[#00a854] text-white shadow-sm'
                                                    : 'border-neutral-200 bg-[#f4f6f8] text-neutral-800'"
                                                :title="cat.category_name"
                                                @click="pickCategory(cat.id)"
                                            >
                                                <span class="truncate">{{ cat.category_name }}</span>
                                                <span class="mr-1 shrink-0 tabular-nums opacity-80">({{ cat.count }})</span>
                                            </button>
                                        </SwiperSlide>
                                        <SwiperSlide v-if="categories.length > 0" class="!w-auto">
                                            <button
                                                type="button"
                                                class="inline-flex min-h-11 touch-manipulation items-center gap-1.5 rounded-full border border-[#00a854]/35 bg-white px-4 py-2.5 text-xs font-bold text-[#00a854] shadow-sm transition active:scale-[0.98] hover:bg-[#00a854]/5"
                                                @click="categorySheetOpen = true"
                                            >
                                                <LayoutGrid class="h-3.5 w-3.5 shrink-0" />
                                                القائمة
                                            </button>
                                        </SwiperSlide>
                                    </Swiper>
                                </div>
                                <p
                                    v-if="selectedCategoryHome !== null"
                                    class="mt-3 flex flex-wrap items-center gap-2 border-t border-neutral-100 pt-3 text-xs text-neutral-600"
                                >
                                    <span>
                                        التصفية:
                                        <strong class="text-[#00a854]">{{
                                            categories.find((c) => c.id === selectedCategoryHome)?.category_name
                                        }}</strong>
                                    </span>
                                    <button
                                        type="button"
                                        class="rounded-lg px-2 py-1 text-[11px] font-semibold text-neutral-500 underline-offset-2 hover:text-neutral-800"
                                        @click="pickCategory(null)"
                                    >
                                        إظهار الكل
                                    </button>
                                </p>
                            </div>
                        </div>

                        <!-- Count -->
                        <p class="mb-4 text-sm text-neutral-500 sm:mb-5">
                            <span class="font-bold text-neutral-800">{{ displayProducts.length }}</span>
                            منتج
                            <span v-if="selectedCategoryHome !== null" class="text-neutral-400">
                                — {{ categories.find(c => c.id === selectedCategoryHome)?.category_name }}
                            </span>
                        </p>

                        <!-- Products grid -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 xl:grid-cols-3">
                            <article
                                v-for="(product, idx) in displayProducts"
                                :key="product.id"
                                class="aw-reveal group flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm transition-all duration-200 active:scale-[0.99] hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md sm:active:scale-100"
                                :style="`transition-delay: ${Math.min(idx, 6) * 50}ms`"
                            >
                                <Link
                                    :href="route('store.product.show', product.id)"
                                    class="flex min-h-0 flex-1 flex-col outline-none transition hover:bg-neutral-50/50 focus-visible:ring-2 focus-visible:ring-[#00a854] focus-visible:ring-offset-2"
                                >
                                    <div class="relative h-44 overflow-hidden border-b border-neutral-100 bg-[#fafbfc] sm:h-52 lg:h-[220px]">
                                        <img
                                            v-if="imageUrl(product)"
                                            :src="imageUrl(product)"
                                            :alt="product.product_name"
                                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center bg-neutral-100"
                                        >
                                            <span class="text-5xl text-neutral-300">🎮</span>
                                        </div>
                                        <div
                                            v-if="product.category"
                                            class="pointer-events-none absolute top-3 right-3 rounded-md border border-neutral-200/80 bg-white/95 px-2.5 py-1 text-xs font-semibold text-neutral-700 shadow-sm backdrop-blur-sm"
                                        >
                                            {{ product.category.category_name }}
                                        </div>
                                    </div>

                                    <div class="flex flex-1 flex-col gap-2 p-3.5 sm:gap-2.5 sm:p-4">
                                        <span class="line-clamp-2 text-start text-sm font-bold text-neutral-900 sm:text-base">
                                            {{ product.product_name }}
                                        </span>
                                        <p v-if="product.description" class="line-clamp-2 text-start text-xs text-neutral-500 sm:text-sm">
                                            {{ product.description }}
                                        </p>
                                        <div class="flex items-center gap-1">
                                            <Star v-for="s in 5" :key="s" class="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                                            <span class="mr-1 text-xs text-neutral-400">(5.0)</span>
                                        </div>
                                        <p class="text-start text-base font-extrabold text-neutral-900 sm:text-lg">
                                            {{ Number(product.price).toLocaleString('ar-SA') }}
                                            <span class="text-xs font-semibold text-neutral-500 sm:text-sm">ريال</span>
                                        </p>
                                        <p class="text-start text-xs font-medium text-[#00a854] sm:hidden">
                                            اضغط للتفاصيل والحجز ←
                                        </p>
                                    </div>
                                </Link>
                                <div class="flex flex-col gap-2 border-t border-neutral-100 bg-white p-3.5 sm:flex-row sm:gap-2 sm:p-4 sm:pt-0">
                                    <Link
                                        :href="route('store.product.show', product.id)"
                                        class="flex min-h-11 flex-1 items-center justify-center rounded-lg border border-neutral-300 bg-white py-3 text-sm font-bold text-neutral-800 transition hover:border-neutral-400 hover:bg-neutral-50"
                                    >
                                        التفاصيل
                                    </Link>
                                    <button
                                        type="button"
                                        class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-lg py-3 text-sm font-bold text-white transition"
                                        :class="addedIds.has(product.id)
                                            ? 'bg-emerald-600 hover:bg-emerald-700'
                                            : 'bg-[#00a854] hover:bg-[#009648]'"
                                        @click.stop="addToCartHome(product)"
                                    >
                                        <ShoppingCart class="h-4 w-4 shrink-0" />
                                        {{ addedIds.has(product.id) ? '✓ أُضيف' : 'أضف للسلة' }}
                                    </button>
                                </div>
                            </article>
                        </div>

                        <div class="aw-reveal mt-8 text-center sm:mt-10">
                            <Link
                                href="/products"
                                class="inline-flex min-h-11 w-full max-w-sm items-center justify-center gap-2 rounded-lg border border-neutral-300 bg-white px-6 py-3 text-sm font-bold text-neutral-800 shadow-sm transition hover:border-[#00a854] hover:text-[#00a854] sm:w-auto sm:px-8 sm:text-base"
                            >
                                عرض كل الألعاب
                            </Link>
                        </div>

                    </div><!-- end main -->
                </div><!-- end flex -->

                <!-- كل التصنيفات — حوار (سطح المكتب والتابلت) -->
                <Dialog :open="categoriesDialogOpen" @update:open="categoriesDialogOpen = $event">
                    <DialogContent
                        class="max-h-[min(90vh,560px)] max-w-[calc(100%-2rem)] gap-0 overflow-hidden border-neutral-200 p-0 sm:max-w-md"
                        dir="rtl"
                    >
                        <DialogHeader class="border-b border-neutral-100 px-5 py-4 text-start">
                            <DialogTitle class="text-lg font-extrabold text-neutral-900">كل التصنيفات</DialogTitle>
                            <p class="mt-1 text-xs leading-relaxed text-neutral-500">
                                مرتبة حسب عدد الألعاب — اختر تصفية أو «الكل» لعرض المنتجات.
                            </p>
                        </DialogHeader>
                        <div class="max-h-[min(60vh,440px)] overflow-y-auto overscroll-contain px-2 py-2 pb-5">
                            <div class="flex flex-col gap-1">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 rounded-xl px-3.5 py-3.5 text-start text-sm font-medium transition"
                                    :class="selectedCategoryHome === null
                                        ? 'bg-[#00a854] text-white shadow-sm'
                                        : 'text-neutral-700 hover:bg-[#f4f6f8]'"
                                    @click="pickCategoryCloseDialog(null)"
                                >
                                    <span>الكل</span>
                                    <span class="shrink-0 tabular-nums text-xs opacity-90">{{ products.length }}</span>
                                </button>
                                <button
                                    v-for="row in categoriesWithCounts"
                                    :key="row.id"
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 rounded-xl px-3.5 py-3.5 text-start text-sm font-medium transition"
                                    :class="selectedCategoryHome === row.id
                                        ? 'bg-[#00a854] text-white shadow-sm'
                                        : 'text-neutral-700 hover:bg-[#f4f6f8]'"
                                    @click="pickCategoryCloseDialog(row.id)"
                                >
                                    <span class="min-w-0 flex-1 truncate">{{ row.category_name }}</span>
                                    <span class="shrink-0 tabular-nums text-xs opacity-90">{{ row.count }}</span>
                                </button>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>

                <!-- كل التصنيفات — ورقة سفلية (جوال) -->
                <Sheet :open="categorySheetOpen" @update:open="categorySheetOpen = $event">
                    <SheetContent
                        side="bottom"
                        class="max-h-[88dvh] gap-0 rounded-t-2xl border-0 border-neutral-200 bg-white p-0 shadow-xl"
                        dir="rtl"
                    >
                        <div class="mx-auto mt-2 h-1 w-10 shrink-0 rounded-full bg-neutral-200 lg:hidden" aria-hidden="true" />
                        <SheetHeader class="border-b border-neutral-100 px-4 pb-3 pt-2 text-start sm:px-5">
                            <SheetTitle class="text-base font-extrabold text-neutral-900">التصنيفات</SheetTitle>
                            <p class="mt-1 text-xs text-neutral-500">مرّر القائمة واختر التصنيف المناسب</p>
                        </SheetHeader>
                        <div
                            class="max-h-[min(65dvh,480px)] overflow-y-auto overscroll-contain px-3 py-2"
                            style="padding-bottom: max(1rem, env(safe-area-inset-bottom, 0px))"
                        >
                            <div class="flex flex-col gap-1 pb-4">
                                <button
                                    type="button"
                                    class="flex min-h-12 w-full items-center justify-between gap-3 rounded-xl px-3.5 py-3.5 text-start text-sm font-medium transition active:scale-[0.99]"
                                    :class="selectedCategoryHome === null
                                        ? 'bg-[#00a854] text-white shadow-sm'
                                        : 'text-neutral-700 hover:bg-[#f4f6f8]'"
                                    @click="pickCategoryCloseSheet(null)"
                                >
                                    <span>الكل</span>
                                    <span class="shrink-0 tabular-nums text-xs opacity-90">{{ products.length }}</span>
                                </button>
                                <button
                                    v-for="row in categoriesWithCounts"
                                    :key="'s-' + row.id"
                                    type="button"
                                    class="flex min-h-12 w-full items-center justify-between gap-3 rounded-xl px-3.5 py-3.5 text-start text-sm font-medium transition active:scale-[0.99]"
                                    :class="selectedCategoryHome === row.id
                                        ? 'bg-[#00a854] text-white shadow-sm'
                                        : 'text-neutral-700 hover:bg-[#f4f6f8]'"
                                    @click="pickCategoryCloseSheet(row.id)"
                                >
                                    <span class="min-w-0 flex-1 truncate">{{ row.category_name }}</span>
                                    <span class="shrink-0 tabular-nums text-xs opacity-90">{{ row.count }}</span>
                                </button>
                            </div>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════
             5. PIC03 SHOWCASE
        ═══════════════════════════════════════════ -->
        <section class="border-t border-neutral-200/80 bg-white py-10 sm:py-14 lg:py-20">
            <div class="mx-auto max-w-7xl px-3.5 sm:px-6 lg:px-8">
                <figure class="aw-reveal overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm sm:rounded-2xl">
                    <img
                        src="/assets/pic03.png"
                        alt="ركن الأطفال — ألعاب وأدوات ترفيهية"
                        class="w-full object-contain p-3 sm:p-4"
                        style="max-height: 800px"
                        onerror="this.parentElement.style.display='none'"
                    />
                </figure>
            </div>
        </section>

        <section id="exceptional-features" class="scroll-mt-24 border-t border-neutral-200/80 bg-[#f4f6f8] py-10 sm:py-14 lg:py-20">
            <div class="mx-auto max-w-7xl px-3.5 sm:px-6 lg:px-8">
                <div class="aw-reveal mb-8 text-center sm:mb-12">
                    <h2 class="text-xl font-bold text-neutral-900 sm:text-3xl lg:text-4xl">استمتع بميزاتنا الاستثنائية</h2>
                    <p class="mt-2 text-sm text-neutral-500 sm:mt-3 sm:text-base">نقدّم لك أكثر من مجرد ألعاب</p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-5 xl:grid-cols-4">
                    <div
                        v-for="(feat, idx) in features"
                        :key="feat.title"
                        class="aw-reveal rounded-xl border border-neutral-200 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md sm:p-6"
                        :style="`transition-delay: ${idx * 60}ms`"
                    >
                        <div
                            class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg border border-neutral-100 bg-[#f4f6f8] text-2xl"
                        >
                            {{ feat.icon }}
                        </div>
                        <h3 class="mb-1.5 text-sm font-bold text-neutral-900 sm:mb-2 sm:text-base">{{ feat.title }}</h3>
                        <p class="text-xs leading-relaxed text-neutral-500 sm:text-sm">{{ feat.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section
            id="partners"
            class="border-t border-neutral-200/80 bg-white py-10 sm:py-14 lg:py-20"
        >
            <div class="mx-auto max-w-5xl px-3.5 sm:px-6 lg:px-8">
                <div class="aw-reveal mb-8 flex flex-col items-center gap-2 text-center sm:mb-10 sm:gap-3">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl border border-neutral-200 bg-[#f4f6f8] text-xl sm:h-14 sm:w-14 sm:text-2xl"
                    >
                        🤝
                    </div>
                    <h2 class="text-xl font-bold text-neutral-900 sm:text-3xl lg:text-4xl">شركاء النجاح</h2>
                    <p class="max-w-md text-sm text-neutral-500 sm:text-base">نفتخر بثقة المؤسسات والجهات التي نخدمها يوماً بعد يوم</p>
                </div>

                <figure class="aw-reveal overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm sm:rounded-2xl sm:p-6">
                    <img
                        src="/assets/partners.png"
                        alt="شركاء النجاح"
                        class="mx-auto w-full object-contain"
                        style="max-height: 300px"
                        onerror="this.style.display='none'"
                    />
                </figure>
            </div>
        </section>

        <section class="border-t border-[#009648] bg-[#00a854] py-10 sm:py-14 lg:py-24">
            <div class="aw-reveal mx-auto max-w-3xl px-3.5 text-center sm:px-4">
                <h2 class="text-2xl font-extrabold text-white sm:text-3xl lg:text-4xl">جاهز لذكريات لا تُنسى؟</h2>
                <p class="mt-3 text-sm text-white/95 sm:mt-4 sm:text-lg">
                    استأجر ألعابك المفضلة الآن وادفع بأمان تام — التوصيل حتى بابك
                </p>
                <Link
                    href="/products"
                    class="mt-6 inline-flex min-h-11 w-full max-w-xs items-center justify-center gap-2 rounded-lg bg-white px-8 py-3 text-sm font-extrabold text-[#00a854] shadow-md transition hover:bg-neutral-50 sm:mt-8 sm:w-auto sm:px-10 sm:py-4 sm:text-base"
                >
                    <ShoppingCart class="h-5 w-5" />
                    ابدأ التصفح
                </Link>
            </div>
        </section>

        <section class="relative overflow-hidden border-t border-neutral-800 bg-[#18181b] py-10 sm:py-16 lg:py-24">
            <div
                class="pointer-events-none absolute inset-0 opacity-30"
                style="background: radial-gradient(ellipse 80% 50% at 50% 0%, rgba(0,168,84,0.15), transparent 55%)"
            ></div>

            <div class="relative mx-auto max-w-7xl px-3.5 sm:px-6 lg:px-8">
                <div class="grid items-center gap-8 lg:grid-cols-2 lg:gap-12" dir="ltr">
                    <div class="aw-reveal-left order-2 flex justify-center max-lg:scale-[0.88] max-lg:origin-top lg:order-1">
                        <div class="relative">
                            <div
                                class="relative h-[min(72vh,480px)] w-[min(72vw,240px)] rounded-[2.75rem] border border-neutral-700/80 bg-neutral-900 shadow-xl sm:h-[520px] sm:w-[260px] sm:rounded-[3rem]"
                            >
                                <div
                                    class="absolute top-5 left-1/2 h-5 w-24 -translate-x-1/2 rounded-full bg-[#18181b]"
                                ></div>
                                <div
                                    class="absolute inset-3 top-8 flex items-center justify-center overflow-hidden rounded-[2.25rem] bg-white"
                                >
                                    <img
                                        src="/assets/logo.png"
                                        alt="عالم المغامرة"
                                        class="w-36 object-contain"
                                        onerror="this.style.display='none'"
                                    />
                                </div>
                                <div
                                    class="absolute bottom-3 left-1/2 h-1 w-24 -translate-x-1/2 rounded-full bg-neutral-600"
                                ></div>
                            </div>

                            <div
                                class="absolute -top-2 -right-4 flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-2 shadow-md sm:-top-3 sm:-right-6 sm:rounded-xl sm:px-4 sm:py-2.5 max-[380px]:hidden"
                            >
                                <span class="text-lg">⭐</span>
                                <div>
                                    <p class="text-xs font-bold text-neutral-900">4.9</p>
                                    <p class="text-[10px] text-neutral-500">+١٠٠٠ تقييم</p>
                                </div>
                            </div>

                            <div
                                class="absolute -bottom-2 -left-4 flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-2 shadow-md sm:-bottom-3 sm:-left-6 sm:rounded-xl sm:px-4 sm:py-2.5 max-[380px]:hidden"
                            >
                                <span class="text-lg">📦</span>
                                <div>
                                    <p class="text-xs font-bold text-neutral-900">توصيل سريع</p>
                                    <p class="text-[10px] text-neutral-500">حتى بابك</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="aw-reveal-right order-1 flex flex-col gap-4 sm:gap-6 lg:order-2" dir="rtl">
                        <div
                            class="inline-flex w-fit items-center gap-2 rounded-lg border border-[#00a854]/40 bg-[#00a854]/10 px-3 py-1.5 text-xs font-medium text-[#4ade80] sm:text-sm"
                        >
                            <span>📱</span> متاح الآن
                        </div>
                        <div>
                            <h2 class="text-2xl font-extrabold text-white sm:text-4xl lg:text-5xl">حمّل تطبيق</h2>
                            <h2 class="mt-1 text-2xl font-extrabold text-[#4ade80] sm:text-4xl lg:text-5xl">
                                عالم المغامرة
                            </h2>
                        </div>
                        <p class="text-sm leading-relaxed text-neutral-400 sm:text-base">
                            احجز ألعابك وتابع طلباتك وادفع بأمان من هاتفك في أي وقت وأي مكان
                        </p>

                        <div class="flex flex-col gap-2.5 sm:flex-row sm:flex-wrap sm:gap-3">
                            <a
                                href="#"
                                class="flex min-h-11 items-center justify-center gap-3 rounded-lg border border-neutral-600 bg-neutral-800/80 px-4 py-3 transition hover:border-neutral-500 hover:bg-neutral-800 sm:min-h-0 sm:flex-1 sm:justify-start sm:px-5"
                            >
                                <svg class="h-7 w-7 text-white" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z" />
                                </svg>
                                <div>
                                    <p class="text-[10px] text-neutral-400">Download on the</p>
                                    <p class="text-sm font-bold text-white">App Store</p>
                                </div>
                            </a>

                            <a
                                href="#"
                                class="flex min-h-11 items-center justify-center gap-3 rounded-lg border border-neutral-600 bg-neutral-800/80 px-4 py-3 transition hover:border-neutral-500 hover:bg-neutral-800 sm:min-h-0 sm:flex-1 sm:justify-start sm:px-5"
                            >
                                <svg class="h-7 w-7" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3.18 23.76a2 2 0 0 0 2.07-.22l11.2-6.48L13 13.6z" fill="#EA4335" />
                                    <path d="M20.54 10.27L17.28 8.4 13.65 12l3.63 3.6 3.27-1.89a1.93 1.93 0 0 0 0-3.44z" fill="#FBBC05" />
                                    <path d="M3.18.24A1.93 1.93 0 0 0 2 2v20a1.93 1.93 0 0 0 1.18 1.76L13.65 12z" fill="#4285F4" />
                                    <path d="M3.18.24L13 12l3.43-3.4-11.18-6.14A2 2 0 0 0 3.18.24z" fill="#34A853" />
                                </svg>
                                <div>
                                    <p class="text-[10px] text-neutral-400">Get it on</p>
                                    <p class="text-sm font-bold text-white">Google Play</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════
             10. FOOTER
        ═══════════════════════════════════════════ -->
        <AppFooter />
    </div>
</template>

<style scoped>
@keyframes aw-news-ticker {
    from {
        transform: translate3d(0, 0, 0);
    }
    to {
        transform: translate3d(calc(-100% / var(--aw-ticker-segments, 16)), 0, 0);
    }
}

.aw-news-ticker-track {
    animation: aw-news-ticker 14s linear infinite;
}

@media (prefers-reduced-motion: reduce) {
    .aw-news-ticker-track {
        animation: none;
        justify-content: center;
        width: 100%;
    }

    .aw-news-ticker-track > div:not(:first-of-type) {
        display: none;
    }
}

/* سلايدر التصنيفات — عرض شرائح حسب المحتوى */
.home-category-swiper :deep(.swiper-slide) {
    width: auto;
}

.home-category-swiper :deep(.swiper-wrapper) {
    align-items: center;
}
</style>
