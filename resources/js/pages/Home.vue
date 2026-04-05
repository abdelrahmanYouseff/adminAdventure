<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppFooter from '@/components/AppFooter.vue';
import StoreHeader from '@/components/StoreHeader.vue';
import { useStoreCart } from '@/composables/useStoreCart';
import { ShoppingCart, Star } from 'lucide-vue-next';

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

const displayProducts = computed(() => {
    const list = selectedCategoryHome.value === null
        ? props.products
        : props.products.filter((p) => p.category_id === selectedCategoryHome.value);
    return list.slice(0, 9);
});

const categoryIcon = (name: string): string => {
    const n = name.toLowerCase();
    if (n.includes('نطاط') || n.includes('قفز') || n.includes('هوائي')) return '🏃';
    if (n.includes('ماء') || n.includes('رذاذ') || n.includes('مائي')) return '💧';
    if (n.includes('سيارة') || n.includes('سباق')) return '🚗';
    if (n.includes('طاولة')) return '🎯';
    if (n.includes('ملعب') || n.includes('حديقة')) return '🏞️';
    if (n.includes('صغير') || n.includes('رضع') || n.includes('اطفال')) return '🍼';
    if (n.includes('رياضة') || n.includes('رياض')) return '⚽';
    if (n.includes('كرنفال') || n.includes('مهرجان')) return '🎠';
    return '🎪';
};

// Categories + product filter on homepage
const selectedCategoryHome = ref<number | null>(null);

const addedIds = ref<Set<number>>(new Set());
function addToCartHome(product: Product) {
    addItem(product.id, product.product_name, Number(product.price), 1, imageUrl(product));
    addedIds.value = new Set([...addedIds.value, product.id]);
    setTimeout(() => {
        addedIds.value.delete(product.id);
        addedIds.value = new Set(addedIds.value);
    }, 1800);
}

const featuresBg = ['#FF6B35', '#6BCF7F', '#4A90E2', '#9B6EFF'];
const features = [
    { icon: '🏷️', title: 'أفضل العروض والأسعار', desc: 'أسعار تنافسية وعروض موسمية لكل الميزانيات', color: '#FF6B35' },
    { icon: '🛡️', title: 'أمان ونظافة', desc: 'ألعاب آمنة ومعقّمة تماشياً مع أعلى معايير الجودة', color: '#6BCF7F' },
    { icon: '⏰', title: 'الالتزام بالمواعيد', desc: 'نصل قبل موعد الحفلة ونجمع كل شيء بعد انتهائها', color: '#4A90E2' },
    { icon: '💳', title: 'كل وسائل الدفع', desc: 'ادفع بطاقة أو نقداً أو عبر التطبيق بكل سهولة', color: '#9B6EFF' },
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

    <div dir="rtl" class="min-h-screen bg-white" style="font-family: 'Noto Kufi Arabic', sans-serif">

        <!-- ═══════════════════════════════════════════
             1. STICKY HEADER
        ═══════════════════════════════════════════ -->
        <StoreHeader />

        <!-- ═══════════════════════════════════════════
             2. HERO
        ═══════════════════════════════════════════ -->
        <section
            class="relative overflow-hidden py-16 lg:py-24"
            style="background: linear-gradient(135deg, rgba(255,217,61,0.15) 0%, rgba(255,107,53,0.08) 50%, rgba(74,144,226,0.15) 100%)"
        >
            <!-- Decorative blobs -->
            <div
                class="pointer-events-none absolute -top-32 -left-32 h-96 w-96 rounded-full opacity-20 blur-3xl"
                style="background: #FFD93D"
            ></div>
            <div
                class="pointer-events-none absolute -bottom-24 -right-24 h-80 w-80 rounded-full opacity-20 blur-3xl"
                style="background: #FF6B35"
            ></div>

            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16" dir="ltr">
                    <!-- Left: Image -->
                    <div class="aw-reveal-left relative">
                        <div class="relative overflow-hidden rounded-3xl shadow-2xl">
                            <img
                                src="/assets/pic01.png"
                                alt="طفلة تلعب في نطاطة ملونة"
                                class="h-full w-full object-cover"
                                style="min-height: 400px"
                                onerror="this.parentElement.style.background='linear-gradient(135deg,#FFD93D33,#FF6B3533)';this.style.display='none'"
                            />
                        </div>
                        <!-- Floating rating badge -->
                        <div
                            class="absolute bottom-6 left-6 flex items-center gap-2 rounded-2xl bg-white px-4 py-3 shadow-xl"
                        >
                            <span class="text-xl">⭐</span>
                            <div>
                                <p class="text-sm font-bold text-neutral-900">4.9</p>
                                <p class="text-xs text-neutral-500">+١٬٥٠٠ تقييم</p>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Text (RTL) -->
                    <div class="aw-reveal-right flex flex-col gap-6" dir="rtl">
                        <div>
                            <h1
                                class="text-4xl font-extrabold leading-tight text-neutral-900 lg:text-5xl xl:text-6xl"
                            >
                                اجعل كل حفلة
                                <br />
                                <span
                                    class="bg-clip-text text-transparent"
                                    style="background-image: linear-gradient(135deg, #FF6B35, #FFD93D, #4A90E2); -webkit-background-clip: text"
                                >
                                    لا تُنسى
                                </span>
                            </h1>
                            <p
                                class="mt-2 text-xl font-semibold"
                                style="background-image: linear-gradient(90deg, #FF6B35, #FFD93D); -webkit-background-clip: text; background-clip: text; color: transparent"
                            >
                                مع عالم المغامرة
                            </p>
                        </div>
                        <p class="text-base leading-relaxed text-neutral-600 lg:text-lg">
                            نوفّر لك أفضل ألعاب الترفيه للأطفال بأعلى معايير الأمان — من نطاطات هوائية وألعاب مائية إلى ملاعب ترفيهية متكاملة. اجعل حفلة طفلك الأجمل على الإطلاق!
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <Link
                                href="/store"
                                class="inline-flex items-center gap-2 rounded-xl px-7 py-4 text-base font-bold text-white shadow-lg transition hover:-translate-y-0.5 hover:opacity-90"
                                style="background: linear-gradient(135deg, #FF6B35, #FFD93D)"
                            >
                                <ShoppingCart class="h-5 w-5" />
                                تصفح الألعاب
                            </Link>
                            <a
                                href="#exceptional-features"
                                class="inline-flex items-center gap-2 rounded-xl border-2 px-7 py-4 text-base font-bold transition hover:-translate-y-0.5"
                                style="border-color: #4A90E2; color: #4A90E2"
                            >
                                الباقات والمزايا
                            </a>
                        </div>
                        <div class="flex flex-wrap gap-5">
                            <div class="flex items-center gap-2 rounded-full bg-white/70 px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm backdrop-blur-sm">
                                <span>🛡️</span> معايير أمان معتمدة
                            </div>
                            <div class="flex items-center gap-2 rounded-full bg-white/70 px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm backdrop-blur-sm">
                                <span>🚚</span> توصيل مجاني
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════
             3 + 4. CATEGORIES & PRODUCTS (مثل صفحة /products)
        ═══════════════════════════════════════════ -->
        <section class="py-14 lg:py-20" style="background: linear-gradient(180deg, #fafafa 0%, #f0f4ff 100%)">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <!-- Title -->
                <div class="aw-reveal mb-8 text-center">
                    <h2 class="text-3xl font-bold text-neutral-900 lg:text-4xl">الألعاب والتصنيفات</h2>
                    <p class="mt-2 text-base text-neutral-500">اختر التصنيف واستعرض الألعاب</p>
                </div>

                <div class="flex flex-col gap-8 lg:flex-row lg:gap-10">

                    <!-- ── Sidebar categories (desktop) ── -->
                    <aside class="hidden w-52 shrink-0 lg:block">
                        <div class="sticky top-24">
                            <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-neutral-500">التصنيفات</h3>
                            <nav class="flex flex-col gap-1">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                    :class="selectedCategoryHome === null ? '' : 'hover:bg-neutral-100'"
                                    :style="selectedCategoryHome === null
                                        ? 'background:#FF6B35; color:#fff'
                                        : 'color:#4b5563'"
                                    @click="selectedCategoryHome = null"
                                >
                                    <span>الكل</span>
                                    <span class="text-xs opacity-70">{{ products.length }}</span>
                                </button>
                                <button
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                    :class="selectedCategoryHome === cat.id ? '' : 'hover:bg-neutral-100'"
                                    :style="selectedCategoryHome === cat.id
                                        ? 'background:#FF6B35; color:#fff'
                                        : 'color:#4b5563'"
                                    @click="selectedCategoryHome = cat.id"
                                >
                                    <span>{{ cat.category_name }}</span>
                                    <span class="text-xs opacity-70">
                                        {{ products.filter(p => p.category_id === cat.id).length }}
                                    </span>
                                </button>
                            </nav>
                        </div>
                    </aside>

                    <!-- ── Main area ── -->
                    <div class="flex-1 min-w-0">

                        <!-- Category pills (mobile) -->
                        <div class="mb-6 flex gap-2 overflow-x-auto pb-1 lg:hidden" style="scrollbar-width:none">
                            <button
                                v-for="cat in [{ id: null, category_name: 'الكل' }, ...categories]"
                                :key="cat.id ?? 'all'"
                                type="button"
                                class="shrink-0 rounded-full px-5 py-2 text-sm font-semibold transition"
                                :style="selectedCategoryHome === (cat.id ?? null)
                                    ? 'background:#FF6B35; color:#fff'
                                    : 'background:#fff; color:#4b5563; box-shadow:0 1px 4px #0000001a'"
                                @click="selectedCategoryHome = cat.id ?? null"
                            >
                                {{ cat.category_name }}
                            </button>
                        </div>

                        <!-- Count -->
                        <p class="mb-5 text-sm text-neutral-500">
                            <span class="font-bold text-neutral-800">{{ displayProducts.length }}</span>
                            منتج
                            <span v-if="selectedCategoryHome !== null" class="text-neutral-400">
                                — {{ categories.find(c => c.id === selectedCategoryHome)?.category_name }}
                            </span>
                        </p>

                        <!-- Products grid -->
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
                            <article
                                v-for="(product, idx) in displayProducts"
                                :key="product.id"
                                class="aw-reveal group flex flex-col overflow-hidden rounded-2xl bg-white shadow-md transition-all duration-300 hover:-translate-y-2 hover:shadow-xl"
                                :style="`transition-delay: ${Math.min(idx, 6) * 70}ms`"
                            >
                                <!-- Image -->
                                <div class="relative overflow-hidden" style="height: 240px">
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
                                        style="background: rgba(255,255,255,0.92); color:#FF6B35"
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
                                    <div class="flex items-center gap-1">
                                        <Star v-for="s in 5" :key="s" class="h-4 w-4" style="fill:#FFD93D; color:#FFD93D" />
                                        <span class="mr-1 text-xs text-neutral-400">(5.0)</span>
                                    </div>
                                    <div class="mt-auto flex gap-2">
                                        <Link
                                            :href="route('store.product.show', product.id)"
                                            class="flex flex-1 items-center justify-center rounded-xl border-2 py-3 text-sm font-bold transition hover:opacity-80"
                                            style="border-color:#FF6B35; color:#FF6B35"
                                        >
                                            التفاصيل
                                        </Link>
                                        <button
                                            class="flex flex-1 items-center justify-center gap-1.5 rounded-xl py-3 text-sm font-bold text-white transition"
                                            :style="addedIds.has(product.id)
                                                ? 'background:linear-gradient(135deg,#6BCF7F,#4ade80)'
                                                : 'background:linear-gradient(135deg,#FF6B35,#FFD93D)'"
                                            @click="addToCartHome(product)"
                                        >
                                            <ShoppingCart class="h-4 w-4" />
                                            {{ addedIds.has(product.id) ? '✓ أُضيف' : 'أضف' }}
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <!-- View all -->
                        <div class="aw-reveal mt-10 text-center">
                            <Link
                                href="/products"
                                class="inline-flex items-center gap-2 rounded-full border-2 px-8 py-4 text-base font-bold transition hover:-translate-y-0.5 hover:shadow-md"
                                style="border-color:#FF6B35; color:#FF6B35"
                            >
                                عرض كل الألعاب
                            </Link>
                        </div>

                    </div><!-- end main -->
                </div><!-- end flex -->
            </div>
        </section>

        <!-- ═══════════════════════════════════════════
             5. PIC03 SHOWCASE
        ═══════════════════════════════════════════ -->
        <section
            class="py-16 lg:py-20"
            style="background: linear-gradient(180deg, #fff9f0 0%, #fff 100%); border-top: 1px solid #FFD93D33; border-bottom: 1px solid #4A90E233"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <figure class="aw-reveal">
                    <div
                        class="rounded-3xl p-1 shadow-2xl"
                        style="background: linear-gradient(135deg, #FF6B35, #FFD93D, #4A90E2)"
                    >
                        <div class="rounded-[calc(1.5rem-4px)] bg-white p-3">
                            <img
                                src="/assets/pic03.png"
                                alt="ركن الأطفال — ألعاب وأدوات ترفيهية"
                                class="w-full rounded-2xl object-contain"
                                style="max-height: 800px"
                                onerror="this.parentElement.parentElement.style.display='none'"
                            />
                        </div>
                    </div>
                </figure>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════
             6. EXCEPTIONAL FEATURES
        ═══════════════════════════════════════════ -->
        <section id="exceptional-features" class="scroll-mt-24 bg-white py-16 lg:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="aw-reveal mb-12 text-center">
                    <h2 class="text-3xl font-bold text-neutral-900 lg:text-4xl">استمتع بميزاتنا الاستثنائية</h2>
                    <p class="mt-3 text-base text-neutral-500">نقدّم لك أكثر من مجرد ألعاب</p>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="(feat, idx) in features"
                        :key="feat.title"
                        class="aw-reveal group rounded-2xl bg-neutral-50 p-7 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                        :style="`transition-delay: ${idx * 80}ms`"
                    >
                        <div
                            class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl text-2xl shadow-md transition-transform duration-300 group-hover:scale-110"
                            :style="`background: ${feat.color}1A; border: 2px solid ${feat.color}33`"
                        >
                            {{ feat.icon }}
                        </div>
                        <h3 class="mb-2 text-base font-bold text-neutral-900">{{ feat.title }}</h3>
                        <p class="text-sm leading-relaxed text-neutral-500">{{ feat.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════
             7. PARTNERS
        ═══════════════════════════════════════════ -->
        <section
            class="py-16 lg:py-20"
            style="background: linear-gradient(180deg, #fffdf0 0%, #f8f6ff 100%)"
        >
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="aw-reveal mb-10 flex flex-col items-center gap-3 text-center">
                    <div
                        class="flex h-16 w-16 items-center justify-center rounded-2xl text-3xl shadow"
                        style="background: linear-gradient(135deg, #FFD93D22, #FF6B3522)"
                    >
                        🤝
                    </div>
                    <h2 class="text-3xl font-bold text-neutral-900 lg:text-4xl">شركاء النجاح</h2>
                    <p class="text-base text-neutral-500">نفتخر بثقة المؤسسات والجهات التي نخدمها يوماً بعد يوم</p>
                </div>

                <figure class="aw-reveal">
                    <div
                        class="rounded-3xl p-[3px] shadow-xl"
                        style="background: linear-gradient(135deg, #FF6B35, #FFD93D, #4A90E2)"
                    >
                        <div class="rounded-[calc(1.5rem-3px)] bg-white p-6">
                            <img
                                src="/assets/partners.png"
                                alt="شركاء النجاح"
                                class="mx-auto w-full object-contain"
                                style="max-height: 300px"
                                onerror="this.parentElement.parentElement.style.display='none'"
                            />
                        </div>
                    </div>
                </figure>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════
             8. CTA
        ═══════════════════════════════════════════ -->
        <section
            class="py-20 lg:py-28"
            style="background: linear-gradient(135deg, #FF6B35 0%, #FFD93D 50%, #4A90E2 100%)"
        >
            <div class="aw-reveal mx-auto max-w-3xl px-4 text-center">
                <h2 class="text-4xl font-extrabold text-white lg:text-5xl">جاهز لذكريات لا تُنسى؟</h2>
                <p class="mt-4 text-lg text-white/90">
                    استأجر ألعابك المفضلة الآن وادفع بأمان تام — التوصيل حتى بابك
                </p>
                <Link
                    href="/products"
                    class="mt-8 inline-flex items-center gap-2 rounded-2xl bg-white px-10 py-5 text-base font-extrabold shadow-xl transition hover:-translate-y-1 hover:shadow-2xl"
                    style="color: #FF6B35"
                >
                    <ShoppingCart class="h-5 w-5" />
                    ابدأ التصفح
                </Link>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════
             9. APP DOWNLOAD BANNER
        ═══════════════════════════════════════════ -->
        <section class="relative overflow-hidden py-16 lg:py-24" style="background: #0f172a">
            <!-- Decorative blobs -->
            <div
                class="pointer-events-none absolute top-0 left-1/4 h-80 w-80 rounded-full opacity-10 blur-3xl"
                style="background: #FF6B35"
            ></div>
            <div
                class="pointer-events-none absolute bottom-0 right-1/4 h-72 w-72 rounded-full opacity-10 blur-3xl"
                style="background: #4A90E2"
            ></div>

            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid items-center gap-12 lg:grid-cols-2" dir="ltr">
                    <!-- Left: Phone mockup -->
                    <div class="aw-reveal-left flex justify-center">
                        <div class="relative">
                            <!-- Phone frame -->
                            <div
                                class="relative h-[520px] w-[260px] rounded-[3rem] border-4 border-neutral-700 shadow-2xl"
                                style="background: #1e293b"
                            >
                                <!-- Notch -->
                                <div
                                    class="absolute top-5 left-1/2 h-5 w-24 -translate-x-1/2 rounded-full"
                                    style="background: #0f172a"
                                ></div>
                                <!-- Screen -->
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
                                <!-- Bottom home bar -->
                                <div
                                    class="absolute bottom-3 left-1/2 h-1 w-24 -translate-x-1/2 rounded-full bg-neutral-600"
                                ></div>
                            </div>

                            <!-- Floating badge top-right -->
                            <div
                                class="absolute -top-3 -right-6 flex items-center gap-2 rounded-2xl bg-white px-4 py-2.5 shadow-xl"
                            >
                                <span class="text-lg">⭐</span>
                                <div>
                                    <p class="text-xs font-bold text-neutral-900">4.9</p>
                                    <p class="text-[10px] text-neutral-500">+١٠٠٠ تقييم</p>
                                </div>
                            </div>

                            <!-- Floating badge bottom-left -->
                            <div
                                class="absolute -bottom-3 -left-6 flex items-center gap-2 rounded-2xl bg-white px-4 py-2.5 shadow-xl"
                            >
                                <span class="text-lg">📦</span>
                                <div>
                                    <p class="text-xs font-bold text-neutral-900">توصيل سريع</p>
                                    <p class="text-[10px] text-neutral-500">حتى بابك</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Text (RTL) -->
                    <div class="aw-reveal-right flex flex-col gap-6" dir="rtl">
                        <div
                            class="inline-flex w-fit items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium"
                            style="border-color: #FF6B3566; color: #FF6B35"
                        >
                            <span>📱</span> متاح الآن
                        </div>
                        <div>
                            <h2 class="text-4xl font-extrabold text-white lg:text-5xl">حمّل تطبيق</h2>
                            <h2
                                class="mt-1 text-4xl font-extrabold lg:text-5xl"
                                style="background: linear-gradient(90deg, #FF6B35, #FFD93D); -webkit-background-clip: text; background-clip: text; color: transparent"
                            >
                                عالم المغامرة
                            </h2>
                        </div>
                        <p class="text-base leading-relaxed text-neutral-400">
                            احجز ألعابك وتابع طلباتك وادفع بأمان من هاتفك في أي وقت وأي مكان
                        </p>

                        <!-- Store buttons -->
                        <div class="flex flex-wrap justify-start gap-4">
                            <!-- App Store -->
                            <a
                                href="#"
                                class="flex items-center gap-3 rounded-2xl border border-neutral-700 bg-neutral-800 px-5 py-3.5 transition hover:border-neutral-500"
                            >
                                <svg class="h-7 w-7 text-white" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z" />
                                </svg>
                                <div>
                                    <p class="text-[10px] text-neutral-400">Download on the</p>
                                    <p class="text-sm font-bold text-white">App Store</p>
                                </div>
                            </a>

                            <!-- Google Play -->
                            <a
                                href="#"
                                class="flex items-center gap-3 rounded-2xl border border-neutral-700 bg-neutral-800 px-5 py-3.5 transition hover:border-neutral-500"
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
