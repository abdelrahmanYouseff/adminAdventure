<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppFooter from '@/components/AppFooter.vue';
import StoreHeader from '@/components/StoreHeader.vue';
import StoreLoginModal from '@/components/StoreLoginModal.vue';
import PaymentSuccessModal, { type PaymentSuccessData } from '@/components/PaymentSuccessModal.vue';
import { useStoreCart } from '@/composables/useStoreCart';
import { useStoreAuth } from '@/composables/useStoreAuth';
import { formatAmount } from '@/lib/formatNumber';
import { BadgePercent, ImageIcon, ShoppingCart, Star } from 'lucide-vue-next';

interface Category {
    id: number;
    category_name: string;
    image?: string | null;
    image_url?: string | null;
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
    payment_success?: PaymentSuccessData | null;
}>();

const { count, addItem, syncFromStorage, clearCart } = useStoreCart();
const { requireLogin } = useStoreAuth();
const page = usePage();
const loginModalOpen = ref(false);
const paymentSuccessOpen = ref(false);
const paymentSuccessData = ref<PaymentSuccessData | null>(null);

function openLoginModal() {
    loginModalOpen.value = true;
}

function guardAction(action: () => void) {
    requireLogin(action, openLoginModal);
}

function openCategory(categoryId: number) {
    router.visit(route('store.category.show', categoryId));
}

function openProduct(productId: number) {
    guardAction(() => {
        router.visit(route('store.product.show', productId));
    });
}

function goToStore(path: string) {
    guardAction(() => {
        router.visit(path);
    });
}

onMounted(() => {
    syncFromStorage();

    const paymentSuccess = props.payment_success
        ?? (page.props.flash as { payment_success?: PaymentSuccessData })?.payment_success;
    if (paymentSuccess?.order_number) {
        paymentSuccessData.value = paymentSuccess;
        paymentSuccessOpen.value = true;
        clearCart();
    }

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

const categoriesWithCounts = computed(() =>
    props.categories
        .map((c) => ({
            ...c,
            count: props.products.filter((p) => p.category_id === c.id).length,
        }))
        .sort((a, b) => b.count - a.count),
);

/** أبرز التصنيفات لقسم «تصفح تصنيفات الألعاب» — الأصناف التي لها صورة تظهر أولاً */
const browseCategories = computed(() => {
    const withCounts = categoriesWithCounts.value.filter((c) => c.count > 0);
    const withImage = withCounts.filter((c) => c.image);
    const withoutImage = withCounts.filter((c) => !c.image);
    const merged = [...withImage];
    for (const cat of withoutImage) {
        if (merged.length >= 12) break;
        merged.push(cat);
    }
    return merged;
});

const latestProducts = computed(() => props.products.slice(0, 9));

const tickerSegmentCount = 16;

const addedIds = ref<Set<number>>(new Set());

function addToCartHome(product: Product) {
    guardAction(() => {
        addItem(product.id, product.product_name, Number(product.price), 1, imageUrl(product));
        addedIds.value = new Set([...addedIds.value, product.id]);
        setTimeout(() => {
            addedIds.value.delete(product.id);
            addedIds.value = new Set(addedIds.value);
        }, 1800);
    });
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
        <title>عالم المغامرة للترفيه — تأجير ألعاب ترفيهية للأطفال</title>
        <meta name="description" content="اجعل كل حفلة لا تُنسى مع عالم المغامرة للترفيه — أفضل خدمة تأجير ألعاب ترفيهية في المملكة" />
        <meta property="og:title" content="عالم المغامرة للترفيه" head-key="og-title" />
        <meta property="og:description" content="اجعل كل حفلة لا تُنسى مع عالم المغامرة للترفيه — أفضل خدمة تأجير ألعاب ترفيهية في المملكة" head-key="og-description" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div dir="rtl" class="min-h-screen bg-[#f4f6f8] pb-[env(safe-area-inset-bottom,0px)]" style="font-family: 'Noto Kufi Arabic', sans-serif">

        <StoreHeader :show-login-button="true" @open-login="openLoginModal" />
        <StoreLoginModal v-model:open="loginModalOpen" />
        <PaymentSuccessModal v-model:open="paymentSuccessOpen" :data="paymentSuccessData" />

        <!-- Hero -->
        <section class="relative isolate overflow-hidden border-b border-neutral-200/80">
            <div
                class="absolute inset-0 min-h-[min(88vh,640px)] bg-cover bg-center bg-no-repeat sm:min-h-[520px] lg:min-h-[580px]"
                style="background-image: url('/assets/pic01.png')"
                role="img"
                aria-label="طفلة تلعب في نطاطة ملونة"
            />
            <div class="absolute inset-0 bg-gradient-to-l from-black/85 via-black/55 to-black/25" />

            <div class="relative mx-auto flex min-h-[min(88vh,640px)] max-w-7xl items-center justify-start px-3.5 py-12 sm:min-h-[520px] sm:px-6 sm:py-16 lg:min-h-[580px] lg:px-8 lg:py-24">
                <div class="aw-reveal-right w-full max-w-xl text-start" dir="rtl">
                    <div>
                        <p class="mb-1.5 text-xs font-semibold text-[#6baee3] sm:mb-2 sm:text-sm">عالم المغامرة</p>
                        <h1 class="text-2xl font-extrabold leading-snug tracking-tight text-white sm:text-4xl lg:text-5xl xl:text-[3.25rem]">
                            اجعل كل حفلة
                            <br />
                            <span class="text-[#6baee3]">لا تُنسى</span>
                        </h1>
                        <p class="mt-2 text-sm font-medium text-white/90 sm:mt-3 sm:text-lg">
                            تأجير ألعاب ترفيهية للأطفال بسهولة كمتجرك المفضل
                        </p>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-white/80 sm:mt-5 sm:text-base lg:text-lg">
                        نوفّر لك أفضل ألعاب الترفيه للأطفال بأعلى معايير الأمان — من نطاطات هوائية وألعاب مائية إلى ملاعب ترفيهية متكاملة.
                    </p>
                    <div class="mt-5 flex flex-col gap-2.5 sm:mt-6 sm:flex-row sm:flex-wrap sm:justify-start sm:gap-3">
                        <button
                            type="button"
                            class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-[#3b89d2] px-6 py-3 text-sm font-bold text-white shadow-sm transition active:scale-[0.99] hover:bg-[#2f6eb0] hover:shadow sm:w-auto sm:px-7 sm:text-base"
                            @click="goToStore('/store')"
                        >
                            <ShoppingCart class="h-5 w-5 shrink-0" />
                            تصفح الألعاب
                        </button>
                        <a
                            href="#exceptional-features"
                            class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-white/30 bg-white/10 px-6 py-3 text-sm font-bold text-white backdrop-blur-sm transition active:scale-[0.99] hover:bg-white/20 sm:w-auto sm:px-7 sm:text-base"
                        >
                            الباقات والمزايا
                        </a>
                    </div>
                    <div class="mt-4 flex flex-wrap justify-start gap-2 sm:mt-5 sm:gap-3">
                        <span class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-xs font-medium text-white/95 backdrop-blur-sm sm:px-4 sm:text-sm">
                            <span>🛡️</span> معايير أمان معتمدة
                        </span>
                        <span class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-xs font-medium text-white/95 backdrop-blur-sm sm:px-4 sm:text-sm">
                            <span>🚚</span> توصيل مجاني
                        </span>
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

        <!-- شركاء النجاح -->
        <section
            id="partners"
            class="border-b border-neutral-200/80 bg-white py-10 sm:py-14 lg:py-16"
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

        <!-- تصفح تصنيفات الألعاب -->
        <section
            id="browse-categories"
            class="border-b border-neutral-200/80 bg-white py-10 sm:py-14 lg:py-16"
        >
            <div class="mx-auto max-w-7xl px-3.5 sm:px-6 lg:px-8">
                <div class="aw-reveal mb-8 text-center sm:mb-10">
                    <h2 class="text-xl font-extrabold tracking-tight text-neutral-900 sm:text-3xl lg:text-4xl">
                        تصفح تصنيفات الألعاب
                    </h2>
                    <p class="mt-2 text-sm text-neutral-500 sm:mt-3 sm:text-base">
                        ترفيه على كل شكل ولون
                    </p>
                </div>

                <div
                    v-if="browseCategories.length > 0"
                    class="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 md:gap-6"
                >
                    <button
                        v-for="(cat, idx) in browseCategories"
                        :key="cat.id"
                        type="button"
                        class="aw-reveal group flex w-full flex-col items-center border-0 bg-transparent p-0 text-center outline-none transition active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-[#3b89d2] focus-visible:ring-offset-2"
                        :style="`transition-delay: ${idx * 40}ms`"
                        @click="openCategory(cat.id)"
                    >
                        <div
                            class="relative flex aspect-[5/4] w-full items-center justify-center overflow-hidden rounded-xl bg-[#f3f3f5] shadow-[0_6px_18px_rgba(15,23,42,0.06)] transition duration-300 group-hover:shadow-[0_10px_24px_rgba(15,23,42,0.1)] sm:rounded-2xl"
                        >
                            <div
                                v-if="cat.image"
                                class="pointer-events-none absolute bottom-[6%] left-1/2 z-0 h-[20%] w-[54%] -translate-x-1/2 rounded-[50%] bg-gradient-to-b from-[#b39ddb] via-[#9575cd] to-[#7e57c2] shadow-[0_4px_12px_rgba(126,87,194,0.3)]"
                                aria-hidden="true"
                            />
                            <img
                                v-if="cat.image"
                                :src="`/storage/${cat.image.replace(/^\//, '')}`"
                                :alt="cat.category_name"
                                class="relative z-10 max-h-[88%] max-w-[94%] object-contain transition duration-300 group-hover:scale-[1.03]"
                                loading="lazy"
                            />
                            <div
                                v-else
                                class="flex h-full w-full flex-col items-center justify-center gap-2 text-neutral-300"
                            >
                                <ImageIcon class="h-8 w-8 shrink-0 sm:h-9 sm:w-9" />
                            </div>
                        </div>
                        <p class="mt-2 w-full truncate px-1 text-xs font-semibold text-neutral-900 sm:mt-2.5 sm:text-sm">
                            {{ cat.category_name }}
                        </p>
                    </button>
                </div>

                <p v-else class="aw-reveal text-center text-sm text-neutral-500">
                    لا توجد تصنيفات متاحة حالياً.
                </p>
            </div>
        </section>

        <!-- أحدث الإضافات -->
        <section id="home-products" class="scroll-mt-20 border-t border-neutral-200/80 bg-[#f4f6f8] py-10 sm:py-14 lg:py-20">
            <div class="mx-auto max-w-7xl px-3.5 sm:px-6 lg:px-8">
                <div class="aw-reveal mb-6 text-center sm:mb-8">
                    <h2 class="text-xl font-bold text-neutral-900 sm:text-3xl lg:text-4xl">أحدث الإضافات</h2>
                    <p class="mt-1.5 text-sm text-neutral-500 sm:mt-2 sm:text-base">اكتشف آخر ما أُضيف إلى مجموعتنا</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3">
                    <article
                        v-for="(product, idx) in latestProducts"
                        :key="product.id"
                        class="aw-reveal group flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm transition-all duration-200 active:scale-[0.99] hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md sm:active:scale-100"
                        :style="`transition-delay: ${Math.min(idx, 6) * 50}ms`"
                    >
                        <button
                            type="button"
                            class="flex min-h-0 flex-1 flex-col text-start outline-none transition hover:bg-neutral-50/50 focus-visible:ring-2 focus-visible:ring-[#3b89d2] focus-visible:ring-offset-2"
                            @click="openProduct(product.id)"
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
                                    {{ formatAmount(product.price) }}
                                    <span class="text-xs font-semibold text-neutral-500 sm:text-sm">ريال</span>
                                </p>
                            </div>
                        </button>
                        <div class="border-t border-neutral-100 bg-white p-3.5 sm:p-4 sm:pt-0">
                            <button
                                type="button"
                                class="flex min-h-11 w-full items-center justify-center gap-1.5 rounded-lg bg-[#3b89d2] py-3 text-sm font-bold text-white transition hover:bg-[#2f6eb0]"
                                @click.stop="addToCartHome(product)"
                            >
                                <ShoppingCart class="h-4 w-4 shrink-0" />
                                {{ addedIds.has(product.id) ? '✓ أُضيف' : 'إضافة للسلة' }}
                            </button>
                        </div>
                    </article>
                </div>

                <div class="aw-reveal mt-8 text-center sm:mt-10">
                    <button
                        type="button"
                        class="inline-flex min-h-11 w-full max-w-sm items-center justify-center gap-2 rounded-lg border border-neutral-300 bg-white px-6 py-3 text-sm font-bold text-neutral-800 shadow-sm transition hover:border-[#3b89d2] hover:text-[#3b89d2] sm:w-auto sm:px-8 sm:text-base"
                        @click="goToStore('/store/all-products')"
                    >
                        عرض كل الألعاب
                    </button>
                </div>
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

        <section class="border-t border-[#2f6eb0] bg-[#3b89d2] py-10 sm:py-14 lg:py-24">
            <div class="aw-reveal mx-auto max-w-3xl px-3.5 text-center sm:px-4">
                <h2 class="text-2xl font-extrabold text-white sm:text-3xl lg:text-4xl">جاهز لذكريات لا تُنسى؟</h2>
                <p class="mt-3 text-sm text-white/95 sm:mt-4 sm:text-lg">
                    استأجر ألعابك المفضلة الآن وادفع بأمان تام — التوصيل حتى بابك
                </p>
                <button
                    type="button"
                    class="mt-6 inline-flex min-h-11 w-full max-w-xs items-center justify-center gap-2 rounded-lg bg-white px-8 py-3 text-sm font-extrabold text-[#3b89d2] shadow-md transition hover:bg-neutral-50 sm:mt-8 sm:w-auto sm:px-10 sm:py-4 sm:text-base"
                    @click="goToStore('/store/all-products')"
                >
                    <ShoppingCart class="h-5 w-5" />
                    ابدأ التصفح
                </button>
            </div>
        </section>

        <section class="relative overflow-hidden border-t border-neutral-800 bg-[#18181b] py-10 sm:py-16 lg:py-24">
            <div
                class="pointer-events-none absolute inset-0 opacity-30"
                style="background: radial-gradient(ellipse 80% 50% at 50% 0%, rgba(59,137,210,0.15), transparent 55%)"
            ></div>

            <div class="relative mx-auto max-w-7xl px-3.5 sm:px-6 lg:px-8">
                <div class="grid items-center gap-8 lg:grid-cols-2 lg:gap-12" dir="ltr">
                    <div class="aw-reveal-left order-2 flex justify-center max-lg:scale-[0.92] max-lg:origin-top lg:order-1">
                        <img
                            src="/assets/app-phone-mockup-transparent.png"
                            alt="تطبيق عالم المغامرة على الهاتف"
                            class="h-auto w-[min(82vw,300px)] object-contain drop-shadow-[0_20px_50px_rgba(0,0,0,0.35)] sm:w-[320px] lg:w-[360px]"
                        />
                    </div>

                    <div class="aw-reveal-right order-1 flex flex-col gap-4 sm:gap-6 lg:order-2" dir="rtl">
                        <div
                            class="inline-flex w-fit items-center gap-2 rounded-lg border border-[#3b89d2]/40 bg-[#3b89d2]/10 px-3 py-1.5 text-xs font-medium text-[#6baee3] sm:text-sm"
                        >
                            <span>📱</span> متاح الآن
                        </div>
                        <div>
                            <h2 class="text-2xl font-extrabold text-white sm:text-4xl lg:text-5xl">حمّل تطبيق</h2>
                            <h2 class="mt-1 text-2xl font-extrabold text-[#6baee3] sm:text-4xl lg:text-5xl">
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
                                href="https://play.google.com/store/apps/details?id=com.adventure.adventureWorldApp"
                                target="_blank"
                                rel="noopener noreferrer"
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

</style>
