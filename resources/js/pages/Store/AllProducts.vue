<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ShoppingCart, SlidersHorizontal, X, Heart } from 'lucide-vue-next';
import StoreHeader from '@/components/StoreHeader.vue';
import AppFooter from '@/components/AppFooter.vue';
import { useStoreCart } from '@/composables/useStoreCart';
import { useStoreWishlist } from '@/composables/useStoreWishlist';
import { formatAmount } from '@/lib/formatNumber';

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

const props = withDefaults(
    defineProps<{
        products: Product[];
        categories: Category[];
        activeCategoryId?: number | null;
        pageTitle?: string | null;
    }>(),
    {
        activeCategoryId: null,
        pageTitle: null,
    },
);

const { addItem, syncFromStorage } = useStoreCart();
const { isInWishlist, toggle: toggleWishlist, syncFromStorage: syncWishlist } = useStoreWishlist();
onMounted(() => {
    syncFromStorage();
    syncWishlist();
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
const selectedCategory = ref<number | null>(props.activeCategoryId ?? null);

watch(
    () => props.activeCategoryId,
    (id) => {
        selectedCategory.value = id ?? null;
    },
);
const sortBy           = ref<'default' | 'price_asc' | 'price_desc'>('default');
const showFilters      = ref(false);

const filteredProducts = computed(() => {
    let list = [...props.products];

    if (selectedCategory.value !== null)
        list = list.filter((p) => p.category_id === selectedCategory.value);

    if (sortBy.value === 'price_asc')  list.sort((a, b) => Number(a.price) - Number(b.price));
    if (sortBy.value === 'price_desc') list.sort((a, b) => Number(b.price) - Number(a.price));

    return list;
});

const activeFiltersCount = computed(() =>
    (selectedCategory.value !== null ? 1 : 0) +
    (sortBy.value !== 'default' ? 1 : 0),
);

function clearFilters() {
    selectedCategory.value = null;
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
        <title>{{ pageTitle ? `${pageTitle} — عالم المغامرة` : 'جميع الألعاب — عالم المغامرة' }}</title>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div dir="rtl" class="min-h-screen bg-[#f4f6f8] pb-[env(safe-area-inset-bottom,0px)]" style="font-family: 'Noto Kufi Arabic', sans-serif">

        <StoreHeader />

        <!-- ── Hero banner ── -->
        <div class="border-b border-neutral-200/80 bg-white py-8 sm:py-12 lg:py-16">
            <div class="mx-auto max-w-7xl px-3.5 sm:px-6 lg:px-8">
                <h1 class="text-2xl font-extrabold text-neutral-900 sm:text-3xl lg:text-4xl">
                    <template v-if="pageTitle">{{ pageTitle }}</template>
                    <template v-else>
                        جميع
                        <span class="text-[#3b89d2]">الألعاب</span>
                    </template>
                </h1>
                <p class="mt-1.5 text-sm text-neutral-500 sm:mt-2 sm:text-base">
                    {{ filteredProducts.length }} منتج
                    <span v-if="pageTitle">في هذا القسم</span>
                    <span v-else>متاح للإيجار</span>
                </p>
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
                                <Link
                                    :href="route('store.all-products')"
                                    class="flex w-full items-center justify-between gap-3 rounded-lg px-3.5 py-3 text-sm font-medium transition"
                                    :class="selectedCategory === null
                                        ? 'bg-[#3b89d2] text-white shadow-sm'
                                        : 'text-neutral-600 hover:bg-[#f4f6f8]'"
                                >
                                    <span class="min-w-0 truncate whitespace-nowrap text-start">الكل</span>
                                    <span class="shrink-0 tabular-nums text-xs opacity-90">{{ products.length }}</span>
                                </Link>
                                <Link
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :href="route('store.category.show', cat.id)"
                                    class="flex w-full items-center justify-between gap-3 rounded-lg px-3.5 py-3 text-sm font-medium transition"
                                    :class="selectedCategory === cat.id
                                        ? 'bg-[#3b89d2] text-white shadow-sm'
                                        : 'text-neutral-600 hover:bg-[#f4f6f8]'"
                                    :title="cat.category_name"
                                >
                                    <span class="min-w-0 flex-1 truncate text-start leading-none">{{ cat.category_name }}</span>
                                    <span class="shrink-0 tabular-nums text-xs opacity-90">
                                        {{ products.filter(p => p.category_id === cat.id).length }}
                                    </span>
                                </Link>
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
                                    :class="sortBy === opt.value
                                        ? 'bg-[#3b89d2]/10 text-[#3b89d2]'
                                        : 'text-neutral-600 hover:bg-[#f4f6f8]'"
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
                            :style="showFilters ? 'background:#3b89d2; color:#fff; border-color:#3b89d2' : 'color:#4b5563; border-color:#e5e7eb; background:#fff'"
                            @click="showFilters = !showFilters"
                        >
                            <SlidersHorizontal class="h-4 w-4 shrink-0" />
                            ترتيب وفلترة
                            <span
                                v-if="activeFiltersCount > 0"
                                class="flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-white px-1 text-xs font-bold"
                                :style="showFilters ? 'color:#3b89d2' : 'background:#3b89d2; color:#fff'"
                            >
                                {{ activeFiltersCount }}
                            </span>
                        </button>

                        <div class="w-full rounded-xl border border-neutral-200 bg-white p-3.5 shadow-sm sm:rounded-2xl sm:p-4">
                            <h3 class="mb-3 border-b border-neutral-100 pb-2 text-sm font-bold text-neutral-800">التصنيفات</h3>
                            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:gap-2">
                                <Link
                                    v-for="cat in [{ id: null, category_name: 'الكل', href: route('store.all-products') }, ...categories.map(c => ({ ...c, href: route('store.category.show', c.id) }))]"
                                    :key="cat.id ?? 'all'"
                                    :href="cat.href"
                                    class="flex min-h-[3rem] w-full items-center justify-center rounded-xl border px-2 py-2 text-center text-xs font-semibold leading-tight transition active:scale-[0.98] sm:inline-flex sm:min-h-[2.75rem] sm:max-w-full sm:px-4 sm:py-2.5 sm:text-sm"
                                    :style="selectedCategory === (cat.id ?? null)
                                        ? 'background:#3b89d2; color:#fff; border-color:#3b89d2'
                                        : 'background:#f8fafc; color:#374151; border-color:#e5e7eb'"
                                    :title="cat.category_name"
                                >
                                    <span class="line-clamp-2 sm:line-clamp-1 sm:max-w-[min(100%,20rem)] sm:truncate">{{ cat.category_name }}</span>
                                </Link>
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
                                    ? 'background:#3b89d2; color:#fff'
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
                        <p class="mt-1 text-sm text-neutral-400">جرّب تغيير الفلاتر</p>
                        <button
                            type="button"
                            class="mt-5 rounded-xl px-6 py-2.5 text-sm font-bold text-white"
                            style="background: #3b89d2"
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
                                class="flex min-h-0 flex-1 flex-col outline-none transition hover:bg-neutral-50/50 focus-visible:ring-2 focus-visible:ring-[#3b89d2] focus-visible:ring-offset-2"
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
                                        style="background: linear-gradient(135deg, rgba(59,137,210,0.12), rgba(47,110,176,0.10))"
                                    >
                                        <span class="text-5xl">🎮</span>
                                    </div>
                                    <div
                                        class="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                        style="background: linear-gradient(to top, rgba(0,0,0,0.25) 0%, transparent 60%)"
                                    ></div>
                                </div>

                                <div class="flex flex-1 flex-col gap-2 p-3.5 sm:gap-3 sm:p-5">
                                    <span class="line-clamp-2 text-start text-sm font-bold text-neutral-900 sm:text-base">
                                        {{ product.product_name }}
                                    </span>
                                    <p v-if="product.description" class="line-clamp-2 text-start text-xs text-neutral-500 sm:text-sm">
                                        {{ product.description }}
                                    </p>
                                    <p class="text-start text-base font-bold text-[#3b89d2] sm:text-lg">
                                        {{ formatAmount(product.price) }} ريال
                                    </p>
                                </div>
                            </Link>
                            <div class="flex items-center gap-2 border-t border-neutral-100 bg-white p-3.5 sm:gap-3 sm:p-5 sm:pt-0">
                                <button
                                    type="button"
                                    class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl py-3 text-sm font-bold text-white transition active:scale-[0.99]"
                                    :style="addedIds.has(product.id)
                                        ? 'background: linear-gradient(135deg,#7ab8e8,#6baee3)'
                                        : 'background: linear-gradient(135deg,#3b89d2,#2f6eb0)'"
                                    @click.stop="addToCart(product)"
                                >
                                    <ShoppingCart class="h-4 w-4 shrink-0" />
                                    {{ addedIds.has(product.id) ? '✓ أُضيف' : 'أضف إلى السلة' }}
                                </button>
                                <button
                                    type="button"
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border-2 transition hover:opacity-80 active:scale-[0.99]"
                                    :class="isInWishlist(product.id)
                                        ? 'border-red-300 bg-red-50'
                                        : 'border-neutral-200 bg-white'"
                                    :aria-label="isInWishlist(product.id) ? 'إزالة من المفضلة' : 'أضف للمفضلة'"
                                    @click.stop="toggleWishlist(product.id)"
                                >
                                    <Heart
                                        class="h-5 w-5 transition-colors"
                                        :class="isInWishlist(product.id)
                                            ? 'fill-red-500 text-red-500'
                                            : 'text-neutral-400'"
                                    />
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
