<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ShoppingCart, Star, Search, SlidersHorizontal, X } from 'lucide-vue-next';
import StoreHeader from '@/components/StoreHeader.vue';
import AppFooter from '@/components/AppFooter.vue';
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
    image: string | null;
    image_url?: string | null;
    category_id: number | null;
    category?: Category | null;
}

const props = defineProps<{
    products: Product[];
    categories: Category[];
}>();

const { addItem, syncFromStorage } = useStoreCart();
onMounted(() => {
    syncFromStorage();
    initReveal();
});

function initReveal() {
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
        { threshold: 0.08 },
    );
    document.querySelectorAll('.aw-reveal').forEach((el) => {
        (el as HTMLElement).style.opacity = '0';
        (el as HTMLElement).style.transform = 'translateY(24px)';
        (el as HTMLElement).style.transition = 'opacity 0.55s ease, transform 0.55s ease';
        observer.observe(el);
    });
}

const imageUrl = (p: Product): string | null => {
    if (p.image) return `/storage/${p.image.replace(/^\//, '')}`;
    if (p.image_url) return p.image_url;
    return null;
};

// ── Filters ──────────────────────────────────
const selectedCategory = ref<number | null>(null);
const searchQuery      = ref('');
const sortBy           = ref<'default' | 'price_asc' | 'price_desc'>('default');
const showFilters      = ref(false);

const filteredProducts = computed(() => {
    let list = [...props.products];

    if (selectedCategory.value !== null)
        list = list.filter((p) => p.category_id === selectedCategory.value);

    if (searchQuery.value.trim())
        list = list.filter((p) =>
            p.product_name.toLowerCase().includes(searchQuery.value.toLowerCase()),
        );

    if (sortBy.value === 'price_asc')  list.sort((a, b) => Number(a.price) - Number(b.price));
    if (sortBy.value === 'price_desc') list.sort((a, b) => Number(b.price) - Number(a.price));

    return list;
});

const activeFiltersCount = computed(() =>
    (selectedCategory.value !== null ? 1 : 0) +
    (searchQuery.value.trim() ? 1 : 0) +
    (sortBy.value !== 'default' ? 1 : 0),
);

function clearFilters() {
    selectedCategory.value = null;
    searchQuery.value = '';
    sortBy.value = 'default';
}

// ── Added feedback ──────────────────────────
const addedIds = ref<Set<number>>(new Set());
function addToCart(product: Product) {
    addItem(product.id, product.product_name, Number(product.price), 1, imageUrl(product));
    addedIds.value = new Set([...addedIds.value, product.id]);
    setTimeout(() => {
        addedIds.value.delete(product.id);
        addedIds.value = new Set(addedIds.value);
    }, 1800);
}
</script>

<template>
    <Head>
        <title>جميع الألعاب — عالم المغامرة</title>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div dir="rtl" class="min-h-screen bg-white pb-[env(safe-area-inset-bottom,0px)]" style="font-family: 'Noto Kufi Arabic', sans-serif">

        <StoreHeader />

        <!-- ── Hero banner ── -->
        <div
            class="relative overflow-hidden py-8 sm:py-12 lg:py-16"
            style="background: linear-gradient(135deg, rgba(255,217,61,0.15) 0%, rgba(255,107,53,0.08) 50%, rgba(74,144,226,0.15) 100%)"
        >
            <div class="pointer-events-none absolute -top-20 -right-20 h-72 w-72 rounded-full opacity-20 blur-3xl" style="background:#FFD93D"></div>
            <div class="pointer-events-none absolute -bottom-16 -left-16 h-64 w-64 rounded-full opacity-20 blur-3xl" style="background:#4A90E2"></div>

            <div class="relative mx-auto max-w-7xl px-3.5 sm:px-6 lg:px-8">
                <h1 class="text-2xl font-extrabold text-neutral-900 sm:text-3xl lg:text-4xl">
                    جميع
                    <span
                        class="bg-clip-text text-transparent"
                        style="background-image: linear-gradient(135deg,#FF6B35,#FFD93D); -webkit-background-clip:text"
                    >الألعاب</span>
                </h1>
                <p class="mt-1.5 text-sm text-neutral-500 sm:mt-2 sm:text-base">{{ products.length }} منتج متاح للإيجار</p>

                <!-- Search bar -->
                <div class="relative mt-4 max-w-xl sm:mt-6">
                    <Search class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-neutral-400 sm:right-4" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        enterkeyhint="search"
                        placeholder="ابحث عن لعبة..."
                        class="w-full rounded-xl border border-neutral-200 bg-white py-3.5 pr-10 pl-11 text-base shadow-sm outline-none transition focus:border-[#FF6B35] focus:ring-2 sm:rounded-2xl sm:pr-11 sm:text-sm"
                        style="focus:ring-color: #FF6B3530"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute top-1/2 left-3 -translate-y-1/2 touch-manipulation rounded-md p-1 text-neutral-400 hover:text-neutral-700 sm:left-4"
                        @click="searchQuery = ''"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-3.5 py-6 sm:px-6 sm:py-8 lg:px-8">
            <div class="flex flex-col gap-8 lg:flex-row lg:gap-10">

                <!-- ══ SIDEBAR (desktop) ══ -->
                <aside class="hidden w-[min(100%,22rem)] min-w-[17rem] max-w-[24rem] shrink-0 lg:block lg:me-10 xl:me-12" aria-label="تصفية حسب التصنيف">
                    <div class="sticky top-24 space-y-4">
                        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
                            <h3 class="mb-3 border-b border-neutral-100 pb-2 ps-1 text-sm font-bold text-neutral-800">التصنيفات</h3>
                            <nav class="flex flex-col gap-1">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 rounded-lg px-3.5 py-3 text-sm font-medium transition"
                                    :style="selectedCategory === null
                                        ? 'background:#FF6B35; color:#fff'
                                        : 'color:#4b5563'"
                                    :class="selectedCategory === null ? '' : 'hover:bg-neutral-100'"
                                    @click="selectedCategory = null"
                                >
                                    <span class="min-w-0 truncate whitespace-nowrap text-start">الكل</span>
                                    <span class="shrink-0 tabular-nums text-xs opacity-90">{{ products.length }}</span>
                                </button>
                                <button
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 rounded-lg px-3.5 py-3 text-sm font-medium transition"
                                    :style="selectedCategory === cat.id
                                        ? 'background:#FF6B35; color:#fff'
                                        : 'color:#4b5563'"
                                    :class="selectedCategory === cat.id ? '' : 'hover:bg-neutral-100'"
                                    :title="cat.category_name"
                                    @click="selectedCategory = cat.id"
                                >
                                    <span class="min-w-0 flex-1 truncate text-start leading-none">{{ cat.category_name }}</span>
                                    <span class="shrink-0 tabular-nums text-xs opacity-90">
                                        {{ products.filter(p => p.category_id === cat.id).length }}
                                    </span>
                                </button>
                            </nav>
                        </div>

                        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
                            <h3 class="mb-3 border-b border-neutral-100 pb-2 text-sm font-bold text-neutral-800">الترتيب</h3>
                            <div class="flex flex-col gap-1">
                                <button
                                    v-for="opt in [
                                        { value: 'default',    label: 'الأحدث' },
                                        { value: 'price_asc',  label: 'السعر: الأقل أولاً' },
                                        { value: 'price_desc', label: 'السعر: الأعلى أولاً' },
                                    ]"
                                    :key="opt.value"
                                    type="button"
                                    class="flex w-full items-center rounded-lg px-3.5 py-3 text-start text-sm font-medium transition"
                                    :style="sortBy === opt.value
                                        ? 'background:#FF6B3515; color:#FF6B35'
                                        : 'color:#4b5563'"
                                    :class="sortBy === opt.value ? '' : 'hover:bg-neutral-100'"
                                    @click="sortBy = opt.value as typeof sortBy"
                                >
                                    <span class="min-w-0 leading-snug">{{ opt.label }}</span>
                                </button>
                            </div>
                        </div>

                        <button
                            v-if="activeFiltersCount > 0"
                            type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 py-3 text-sm font-medium text-red-600 transition hover:bg-red-50"
                            @click="clearFilters"
                        >
                            <X class="h-4 w-4" />
                            مسح الفلاتر
                        </button>
                    </div>
                </aside>

                <!-- ══ MAIN CONTENT ══ -->
                <div class="flex-1 min-w-0">

                    <!-- Mobile: فلترة + بطاقة تصنيفات منظمة -->
                    <div class="mb-5 flex flex-col gap-3 lg:hidden">
                        <button
                            type="button"
                            class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border px-4 py-3 text-sm font-semibold transition touch-manipulation active:scale-[0.99] sm:w-auto sm:justify-start"
                            :style="showFilters ? 'background:#FF6B35; color:#fff; border-color:#FF6B35' : 'color:#4b5563; border-color:#e5e7eb; background:#fff'"
                            @click="showFilters = !showFilters"
                        >
                            <SlidersHorizontal class="h-4 w-4 shrink-0" />
                            ترتيب وفلترة
                            <span
                                v-if="activeFiltersCount > 0"
                                class="flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-white px-1 text-xs font-bold"
                                :style="showFilters ? 'color:#FF6B35' : 'background:#FF6B35; color:#fff'"
                            >
                                {{ activeFiltersCount }}
                            </span>
                        </button>

                        <div class="w-full rounded-xl border border-neutral-200 bg-white p-3.5 shadow-sm sm:rounded-2xl sm:p-4">
                            <h3 class="mb-3 border-b border-neutral-100 pb-2 text-sm font-bold text-neutral-800">التصنيفات</h3>
                            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:gap-2">
                                <button
                                    v-for="cat in [{ id: null, category_name: 'الكل' }, ...categories]"
                                    :key="cat.id ?? 'all'"
                                    type="button"
                                    class="flex min-h-[3rem] w-full items-center justify-center rounded-xl border px-2 py-2 text-center text-xs font-semibold leading-tight transition active:scale-[0.98] sm:inline-flex sm:min-h-[2.75rem] sm:max-w-full sm:px-4 sm:py-2.5 sm:text-sm"
                                    :style="selectedCategory === (cat.id ?? null)
                                        ? 'background:#FF6B35; color:#fff; border-color:#FF6B35'
                                        : 'background:#f8fafc; color:#374151; border-color:#e5e7eb'"
                                    :title="cat.category_name"
                                    @click="selectedCategory = cat.id ?? null"
                                >
                                    <span class="line-clamp-2 sm:line-clamp-1 sm:max-w-[min(100%,20rem)] sm:truncate">{{ cat.category_name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile expanded filters -->
                    <div v-if="showFilters" class="mb-5 rounded-xl border border-neutral-100 bg-neutral-50 p-3.5 sm:rounded-2xl sm:p-4 lg:hidden">
                        <p class="mb-2 text-xs font-bold text-neutral-500">الترتيب</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="opt in [
                                    { value: 'default',    label: 'الأحدث' },
                                    { value: 'price_asc',  label: 'الأقل سعراً' },
                                    { value: 'price_desc', label: 'الأعلى سعراً' },
                                ]"
                                :key="opt.value"
                                type="button"
                                class="rounded-xl px-4 py-2 text-xs font-semibold transition"
                                :style="sortBy === opt.value
                                    ? 'background:#FF6B35; color:#fff'
                                    : 'background:#fff; color:#4b5563; border:1px solid #e5e7eb'"
                                @click="sortBy = opt.value as typeof sortBy"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Results count + clear -->
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-2 sm:mb-6">
                        <p class="text-sm text-neutral-500">
                            <span class="font-bold text-neutral-800">{{ filteredProducts.length }}</span> منتج
                        </p>
                        <button
                            v-if="activeFiltersCount > 0"
                            type="button"
                            class="flex items-center gap-1.5 text-sm text-red-500 transition hover:text-red-700"
                            @click="clearFilters"
                        >
                            <X class="h-3.5 w-3.5" />
                            مسح الفلاتر
                        </button>
                    </div>

                    <!-- Empty state -->
                    <div
                        v-if="filteredProducts.length === 0"
                        class="flex flex-col items-center justify-center rounded-3xl border-2 border-dashed border-neutral-200 py-20 text-center"
                    >
                        <span class="text-6xl">🔍</span>
                        <p class="mt-4 text-lg font-bold text-neutral-700">لا توجد نتائج</p>
                        <p class="mt-1 text-sm text-neutral-400">جرّب تغيير الفلاتر أو البحث بكلمة مختلفة</p>
                        <button
                            type="button"
                            class="mt-5 rounded-xl px-6 py-2.5 text-sm font-bold text-white"
                            style="background: linear-gradient(135deg,#FF6B35,#FFD93D)"
                            @click="clearFilters"
                        >
                            عرض الكل
                        </button>
                    </div>

                    <!-- Product grid -->
                    <div
                        v-else
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 xl:grid-cols-3"
                    >
                        <article
                            v-for="(product, idx) in filteredProducts"
                            :key="product.id"
                            class="aw-reveal group flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm transition-all duration-200 active:scale-[0.99] hover:-translate-y-1 hover:shadow-md sm:rounded-2xl sm:shadow-md sm:hover:-translate-y-2 sm:hover:shadow-xl"
                            :style="`transition-delay: ${Math.min(idx, 8) * 60}ms`"
                        >
                            <Link
                                :href="route('store.product.show', product.id)"
                                class="flex min-h-0 flex-1 flex-col outline-none transition hover:bg-neutral-50/50 focus-visible:ring-2 focus-visible:ring-[#FF6B35] focus-visible:ring-offset-2"
                            >
                                <div class="relative h-48 overflow-hidden sm:h-56 lg:h-64">
                                    <img
                                        v-if="imageUrl(product)"
                                        :src="imageUrl(product)"
                                        :alt="product.product_name"
                                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-110"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center"
                                        style="background: linear-gradient(135deg, #FFD93D22, #FF6B3522)"
                                    >
                                        <span class="text-5xl">🎮</span>
                                    </div>
                                    <div
                                        class="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                        style="background: linear-gradient(to top, rgba(0,0,0,0.25) 0%, transparent 60%)"
                                    ></div>
                                    <div
                                        class="pointer-events-none absolute top-3 left-3 rounded-full px-3 py-1.5 text-sm font-bold text-white shadow"
                                        style="background: linear-gradient(135deg, #FFD93D, #FF6B35)"
                                    >
                                        {{ Number(product.price).toLocaleString('ar-SA') }} ريال
                                    </div>
                                    <div
                                        v-if="product.category"
                                        class="pointer-events-none absolute top-3 right-3 rounded-full px-3 py-1 text-xs font-semibold"
                                        style="background: rgba(255,255,255,0.9); color: #FF6B35"
                                    >
                                        {{ product.category.category_name }}
                                    </div>
                                </div>

                                <div class="flex flex-1 flex-col gap-2 p-3.5 sm:gap-3 sm:p-5">
                                    <span class="line-clamp-2 text-start text-sm font-bold text-neutral-900 sm:text-base">
                                        {{ product.product_name }}
                                    </span>
                                    <p v-if="product.description" class="line-clamp-2 text-start text-xs text-neutral-500 sm:text-sm">
                                        {{ product.description }}
                                    </p>
                                    <div class="flex items-center gap-1">
                                        <Star v-for="s in 5" :key="s" class="h-3.5 w-3.5 sm:h-4 sm:w-4" style="fill:#FFD93D; color:#FFD93D" />
                                        <span class="mr-1 text-xs text-neutral-400">(5.0)</span>
                                    </div>
                                    <p class="text-start text-xs font-medium text-[#FF6B35] sm:hidden">
                                        اضغط للتفاصيل الكاملة ←
                                    </p>
                                </div>
                            </Link>
                            <div class="flex flex-col gap-2 border-t border-neutral-100 bg-white p-3.5 sm:flex-row sm:gap-3 sm:p-5 sm:pt-0">
                                <Link
                                    :href="route('store.product.show', product.id)"
                                    class="flex min-h-11 flex-1 items-center justify-center rounded-xl border-2 py-3 text-sm font-bold transition hover:opacity-80"
                                    style="border-color: #FF6B35; color: #FF6B35"
                                >
                                    التفاصيل
                                </Link>
                                <button
                                    type="button"
                                    class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl py-3 text-sm font-bold text-white transition active:scale-[0.99]"
                                    :style="addedIds.has(product.id)
                                        ? 'background: linear-gradient(135deg,#6BCF7F,#4ade80)'
                                        : 'background: linear-gradient(135deg,#FF6B35,#FFD93D)'"
                                    @click.stop="addToCart(product)"
                                >
                                    <ShoppingCart class="h-4 w-4 shrink-0" />
                                    {{ addedIds.has(product.id) ? '✓ أُضيف' : 'أضف' }}
                                </button>
                            </div>
                        </article>
                    </div>

                </div><!-- end main -->
            </div><!-- end flex -->
        </div><!-- end container -->

        <AppFooter />
    </div>
</template>
