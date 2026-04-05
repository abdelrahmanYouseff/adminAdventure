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

    <div dir="rtl" class="min-h-screen bg-white" style="font-family: 'Noto Kufi Arabic', sans-serif">

        <StoreHeader />

        <!-- ── Hero banner ── -->
        <div
            class="relative overflow-hidden py-12 lg:py-16"
            style="background: linear-gradient(135deg, rgba(255,217,61,0.15) 0%, rgba(255,107,53,0.08) 50%, rgba(74,144,226,0.15) 100%)"
        >
            <div class="pointer-events-none absolute -top-20 -right-20 h-72 w-72 rounded-full opacity-20 blur-3xl" style="background:#FFD93D"></div>
            <div class="pointer-events-none absolute -bottom-16 -left-16 h-64 w-64 rounded-full opacity-20 blur-3xl" style="background:#4A90E2"></div>

            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-extrabold text-neutral-900 lg:text-4xl">
                    جميع
                    <span
                        class="bg-clip-text text-transparent"
                        style="background-image: linear-gradient(135deg,#FF6B35,#FFD93D); -webkit-background-clip:text"
                    >الألعاب</span>
                </h1>
                <p class="mt-2 text-neutral-500">{{ products.length }} منتج متاح للإيجار</p>

                <!-- Search bar -->
                <div class="relative mt-6 max-w-xl">
                    <Search class="absolute top-1/2 right-4 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="ابحث عن لعبة..."
                        class="w-full rounded-2xl border border-neutral-200 bg-white py-3.5 pr-11 pl-4 text-sm shadow-sm outline-none transition focus:border-[#FF6B35] focus:ring-2"
                        style="focus:ring-color: #FF6B3530"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400 hover:text-neutral-700"
                        @click="searchQuery = ''"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-8 lg:flex-row lg:gap-10">

                <!-- ══ SIDEBAR (desktop) ══ -->
                <aside class="hidden w-56 shrink-0 lg:block">
                    <div class="sticky top-24">
                        <!-- Categories -->
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-neutral-500">التصنيفات</h3>
                        <nav class="flex flex-col gap-1">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                :style="selectedCategory === null
                                    ? 'background:#FF6B35; color:#fff'
                                    : 'color:#4b5563; hover:background:#f5f5f5'"
                                :class="selectedCategory === null ? '' : 'hover:bg-neutral-100'"
                                @click="selectedCategory = null"
                            >
                                <span>الكل</span>
                                <span class="text-xs opacity-70">{{ products.length }}</span>
                            </button>
                            <button
                                v-for="cat in categories"
                                :key="cat.id"
                                type="button"
                                class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                :style="selectedCategory === cat.id
                                    ? 'background:#FF6B35; color:#fff'
                                    : 'color:#4b5563'"
                                :class="selectedCategory === cat.id ? '' : 'hover:bg-neutral-100'"
                                @click="selectedCategory = cat.id"
                            >
                                <span>{{ cat.category_name }}</span>
                                <span class="text-xs opacity-70">
                                    {{ products.filter(p => p.category_id === cat.id).length }}
                                </span>
                            </button>
                        </nav>

                        <!-- Sort -->
                        <h3 class="mb-3 mt-8 text-xs font-bold uppercase tracking-wider text-neutral-500">الترتيب</h3>
                        <div class="flex flex-col gap-1">
                            <button
                                v-for="opt in [
                                    { value: 'default',    label: 'الأحدث' },
                                    { value: 'price_asc',  label: 'السعر: الأقل أولاً' },
                                    { value: 'price_desc', label: 'السعر: الأعلى أولاً' },
                                ]"
                                :key="opt.value"
                                type="button"
                                class="flex w-full items-center rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                :style="sortBy === opt.value
                                    ? 'background:#FF6B3515; color:#FF6B35'
                                    : 'color:#4b5563'"
                                :class="sortBy === opt.value ? '' : 'hover:bg-neutral-100'"
                                @click="sortBy = opt.value as typeof sortBy"
                            >
                                {{ opt.label }}
                            </button>
                        </div>

                        <!-- Clear -->
                        <button
                            v-if="activeFiltersCount > 0"
                            type="button"
                            class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50"
                            @click="clearFilters"
                        >
                            <X class="h-4 w-4" />
                            مسح الفلاتر
                        </button>
                    </div>
                </aside>

                <!-- ══ MAIN CONTENT ══ -->
                <div class="flex-1 min-w-0">

                    <!-- Mobile filter bar -->
                    <div class="mb-5 flex items-center gap-3 lg:hidden">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-medium transition"
                            :style="showFilters ? 'background:#FF6B35; color:#fff; border-color:#FF6B35' : 'color:#4b5563; border-color:#e5e7eb'"
                            @click="showFilters = !showFilters"
                        >
                            <SlidersHorizontal class="h-4 w-4" />
                            فلترة
                            <span
                                v-if="activeFiltersCount > 0"
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-white text-xs font-bold"
                                :style="showFilters ? 'color:#FF6B35' : 'background:#FF6B35; color:#fff'"
                            >
                                {{ activeFiltersCount }}
                            </span>
                        </button>

                        <!-- Mobile category pills -->
                        <div class="flex flex-1 gap-2 overflow-x-auto pb-1" style="scrollbar-width:none">
                            <button
                                v-for="cat in [{ id: null, category_name: 'الكل' }, ...categories]"
                                :key="cat.id ?? 'all'"
                                type="button"
                                class="shrink-0 rounded-full px-4 py-2 text-xs font-semibold transition"
                                :style="selectedCategory === (cat.id ?? null)
                                    ? 'background:#FF6B35; color:#fff'
                                    : 'background:#f5f5f5; color:#4b5563'"
                                @click="selectedCategory = cat.id ?? null"
                            >
                                {{ cat.category_name }}
                            </button>
                        </div>
                    </div>

                    <!-- Mobile expanded filters -->
                    <div v-if="showFilters" class="mb-5 rounded-2xl border border-neutral-100 bg-neutral-50 p-4 lg:hidden">
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
                    <div class="mb-6 flex items-center justify-between">
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
                        class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <article
                            v-for="(product, idx) in filteredProducts"
                            :key="product.id"
                            class="aw-reveal group flex flex-col overflow-hidden rounded-2xl bg-white shadow-md transition-all duration-300 hover:-translate-y-2 hover:shadow-xl"
                            :style="`transition-delay: ${Math.min(idx, 8) * 60}ms`"
                        >
                            <!-- Image -->
                            <div class="relative overflow-hidden" style="height: 256px">
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
                                <!-- Overlay -->
                                <div
                                    class="absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                    style="background: linear-gradient(to top, rgba(0,0,0,0.25) 0%, transparent 60%)"
                                ></div>
                                <!-- Price badge -->
                                <div
                                    class="absolute top-3 left-3 rounded-full px-3 py-1.5 text-sm font-bold text-white shadow"
                                    style="background: linear-gradient(135deg, #FFD93D, #FF6B35)"
                                >
                                    {{ Number(product.price).toLocaleString('ar-SA') }} ريال
                                </div>
                                <!-- Category badge -->
                                <div
                                    v-if="product.category"
                                    class="absolute top-3 right-3 rounded-full px-3 py-1 text-xs font-semibold"
                                    style="background: rgba(255,255,255,0.9); color: #FF6B35"
                                >
                                    {{ product.category.category_name }}
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex flex-1 flex-col gap-3 p-5">
                                <Link
                                    :href="route('store.product.show', product.id)"
                                    class="text-base font-bold text-neutral-900 line-clamp-2 transition hover:text-[#FF6B35]"
                                >
                                    {{ product.product_name }}
                                </Link>
                                <p v-if="product.description" class="text-sm text-neutral-500 line-clamp-2">
                                    {{ product.description }}
                                </p>
                                <!-- Stars -->
                                <div class="flex items-center gap-1">
                                    <Star v-for="s in 5" :key="s" class="h-4 w-4" style="fill:#FFD93D; color:#FFD93D" />
                                    <span class="mr-1 text-xs text-neutral-400">(5.0)</span>
                                </div>
                                <!-- Buttons -->
                                <div class="mt-auto flex gap-2">
                                    <Link
                                        :href="route('store.product.show', product.id)"
                                        class="flex flex-1 items-center justify-center rounded-xl border-2 py-3 text-sm font-bold transition hover:opacity-80"
                                        style="border-color: #FF6B35; color: #FF6B35"
                                    >
                                        التفاصيل
                                    </Link>
                                    <button
                                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl py-3 text-sm font-bold text-white transition"
                                        :style="addedIds.has(product.id)
                                            ? 'background: linear-gradient(135deg,#6BCF7F,#4ade80)'
                                            : 'background: linear-gradient(135deg,#FF6B35,#FFD93D)'"
                                        @click="addToCart(product)"
                                    >
                                        <ShoppingCart class="h-4 w-4" />
                                        {{ addedIds.has(product.id) ? '✓ أُضيف' : 'أضف' }}
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>

                </div><!-- end main -->
            </div><!-- end flex -->
        </div><!-- end container -->

        <AppFooter />
    </div>
</template>
