<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import StoreHeader from '@/components/StoreHeader.vue';
import AppFooter from '@/components/AppFooter.vue';
import AddressMapPicker from '@/components/AddressMapPicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useStoreCart } from '@/composables/useStoreCart';
import { formatPrice } from '@/lib/formatNumber';
import {
    Lock,
    ShoppingBag,
    ChevronRight,
    User,
    Mail,
    Phone,
    Calendar,
    MapPin,
    ShieldCheck,
    CreditCard,
} from 'lucide-vue-next';

const props = defineProps<{
    customer?: {
        customer_name: string;
        customer_phone: string;
        customer_email: string;
    } | null;
}>();

const page = usePage();
const { cartItems, count, total, insuranceTotal, syncFromStorage } = useStoreCart();

const form = ref({
    customer_name: props.customer?.customer_name ?? '',
    customer_phone: props.customer?.customer_phone ?? '',
    customer_email: props.customer?.customer_email ?? '',
    address: '',
    activity_date: '',
});
const errors = ref<Record<string, string>>({});
const loading = ref(false);

const VAT_RATE = 0.15;
const DELIVERY = 0;

const subtotal = total;
const vat = computed(() => subtotal.value * VAT_RATE);
const grandTotal = computed(() => subtotal.value + vat.value + insuranceTotal.value + DELIVERY);

const fmt = formatPrice;
const lineTotal = (price: number, qty: number, dur: number) => price * qty * dur;

const minDate = computed(() => new Date().toISOString().split('T')[0]);

onMounted(() => syncFromStorage());

const payload = computed(() => {
    const token = (page.props as { csrf_token?: string })?.csrf_token ?? '';
    return {
        ...form.value,
        _token: token,
        items: cartItems.value.map((item) => ({
            product_id: item.product_id,
            product_name: item.product_name,
            quantity: item.quantity,
            duration: item.duration,
            price: item.price,
        })),
    };
});

function getCsrfHeaders(): Record<string, string> {
    const shared = (page.props as { csrf_token?: string })?.csrf_token;
    if (shared) {
        return { 'X-CSRF-TOKEN': shared };
    }
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    if (match) {
        return { 'X-XSRF-TOKEN': decodeURIComponent(match[1].replace(/\+/g, ' ')) };
    }
    return {};
}

const submit = async () => {
    errors.value = {};
    if (cartItems.value.length === 0) {
        errors.value.items = 'السلة فارغة. أضف منتجات قبل إتمام الطلب.';
        return;
    }
    loading.value = true;
    try {
        const res = await fetch(route('store.checkout.submit'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...getCsrfHeaders(),
            },
            body: JSON.stringify(payload.value),
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => ({}));
        if (data.success && data.redirect_url) {
            window.location.href = data.redirect_url;
            return;
        }
        if (data.errors) {
            const out: Record<string, string> = {};
            for (const [k, v] of Object.entries(data.errors)) {
                out[k] = Array.isArray(v) ? (v as string[])[0] : String(v);
            }
            errors.value = out;
        } else {
            errors.value.form = data.message || 'فشل في إنشاء جلسة الدفع';
        }
    } catch {
        errors.value.form = 'حدث خطأ. حاول مرة أخرى.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Head title="إتمام الطلب — عالم المغامرة">
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

        <!-- Empty cart -->
        <div v-if="cartItems.length === 0" class="mx-auto max-w-lg px-3.5 py-10 sm:px-6 sm:py-16 lg:px-8">
            <div class="flex flex-col items-center gap-6 rounded-2xl border border-neutral-200 bg-white px-5 py-10 text-center shadow-sm sm:px-10 sm:py-14">
                <div class="flex h-24 w-24 items-center justify-center rounded-full bg-[#3b89d2]/10 sm:h-28 sm:w-28">
                    <ShoppingBag class="h-12 w-12 text-[#3b89d2]/70 sm:h-14 sm:w-14" />
                </div>
                <div class="space-y-2">
                    <p class="text-xs font-semibold text-[#3b89d2]">عالم المغامرة</p>
                    <h1 class="text-xl font-extrabold tracking-tight text-neutral-900 sm:text-3xl">السلة فارغة</h1>
                    <p class="mx-auto max-w-sm text-sm leading-relaxed text-neutral-500 sm:text-base">
                        أضف منتجات من المتجر ثم عد لإتمام الطلب.
                    </p>
                </div>
                <Link
                    href="/store"
                    class="inline-flex min-h-12 w-full max-w-xs items-center justify-center rounded-xl bg-[#3b89d2] px-8 py-3.5 text-base font-bold text-white shadow-sm transition hover:bg-[#2f6eb0] sm:w-auto"
                >
                    تصفح الألعاب
                </Link>
            </div>
        </div>

        <template v-else>
            <!-- Hero / breadcrumb -->
            <div class="border-b border-neutral-200/80 bg-white">
                <div class="mx-auto max-w-7xl px-3.5 py-3 sm:px-6 sm:py-5 lg:px-8">
                    <nav
                        class="-mx-1 flex flex-nowrap items-center gap-1.5 overflow-x-auto px-1 pb-1 text-[13px] text-neutral-500 sm:text-sm [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                        aria-label="مسار الصفحة"
                    >
                        <Link href="/home" class="shrink-0 whitespace-nowrap py-1 transition hover:text-[#3b89d2]">الرئيسية</Link>
                        <ChevronRight class="h-3 w-3 shrink-0 rotate-180 text-neutral-300" />
                        <Link href="/store" class="shrink-0 whitespace-nowrap py-1 transition hover:text-[#3b89d2]">المتجر</Link>
                        <ChevronRight class="h-3 w-3 shrink-0 rotate-180 text-neutral-300" />
                        <Link :href="route('store.cart')" class="shrink-0 whitespace-nowrap py-1 transition hover:text-[#3b89d2]">السلة</Link>
                        <ChevronRight class="h-3 w-3 shrink-0 rotate-180 text-neutral-300" />
                        <span class="shrink-0 whitespace-nowrap py-1 font-medium text-neutral-800">إتمام الطلب</span>
                    </nav>
                    <div class="mt-3 flex flex-col gap-1 sm:mt-4">
                        <p class="text-xs font-semibold text-[#3b89d2] sm:text-sm">خطوة أخيرة</p>
                        <h1 class="text-xl font-extrabold tracking-tight text-neutral-900 sm:text-3xl lg:text-4xl">إتمام الطلب</h1>
                        <p class="text-sm leading-relaxed text-neutral-500 sm:text-base">
                            {{ count }} {{ count === 1 ? 'منتج' : 'منتجات' }} — أدخل بياناتك ثم ادفع بأمان.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mx-auto max-w-7xl px-3.5 py-5 sm:px-6 sm:py-10 lg:px-8 lg:py-12">
                <div class="grid gap-5 lg:grid-cols-3 lg:gap-8 xl:gap-10">
                    <!-- Form -->
                    <form class="flex min-w-0 flex-col gap-4 lg:col-span-2 lg:gap-5" @submit.prevent="submit">
                        <div
                            v-if="errors.form"
                            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                        >
                            {{ errors.form }}
                        </div>

                        <!-- Contact -->
                        <section class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                            <div class="flex items-center gap-3 border-b border-neutral-100 bg-[#fafbfc] px-4 py-4 sm:px-6">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#3b89d2]/10 text-[#3b89d2]">
                                    <User class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-base font-extrabold text-neutral-900 sm:text-lg">معلومات الاتصال</h2>
                                    <p class="text-xs text-neutral-500">للتواصل وتأكيد الحجز</p>
                                </div>
                            </div>
                            <div class="space-y-4 p-4 sm:p-6">
                                <div>
                                    <Label for="customer_name" class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-neutral-700">
                                        <User class="h-3.5 w-3.5 text-neutral-400" />
                                        الاسم الكامل
                                    </Label>
                                    <Input
                                        id="customer_name"
                                        v-model="form.customer_name"
                                        type="text"
                                        class="h-12 rounded-xl border-neutral-200 bg-[#f4f6f8] focus:bg-white"
                                        required
                                        placeholder="أدخل اسمك الكامل"
                                    />
                                    <p v-if="errors.customer_name" class="mt-1.5 text-sm text-red-600">{{ errors.customer_name }}</p>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label for="customer_email" class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-neutral-700">
                                            <Mail class="h-3.5 w-3.5 text-neutral-400" />
                                            البريد الإلكتروني
                                        </Label>
                                        <Input
                                            id="customer_email"
                                            v-model="form.customer_email"
                                            type="email"
                                            class="h-12 rounded-xl border-neutral-200 bg-[#f4f6f8] focus:bg-white"
                                            required
                                            placeholder="example@email.com"
                                            dir="ltr"
                                        />
                                        <p v-if="errors.customer_email" class="mt-1.5 text-sm text-red-600">{{ errors.customer_email }}</p>
                                    </div>
                                    <div>
                                        <Label for="customer_phone" class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-neutral-700">
                                            <Phone class="h-3.5 w-3.5 text-neutral-400" />
                                            رقم الهاتف
                                        </Label>
                                        <Input
                                            id="customer_phone"
                                            v-model="form.customer_phone"
                                            type="tel"
                                            class="h-12 rounded-xl border-neutral-200 bg-[#f4f6f8] focus:bg-white"
                                            required
                                            placeholder="05xxxxxxxx"
                                            dir="ltr"
                                        />
                                        <p v-if="errors.customer_phone" class="mt-1.5 text-sm text-red-600">{{ errors.customer_phone }}</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Address & date -->
                        <section class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                            <div class="flex items-center gap-3 border-b border-neutral-100 bg-[#fafbfc] px-4 py-4 sm:px-6">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#3b89d2]/10 text-[#3b89d2]">
                                    <MapPin class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-base font-extrabold text-neutral-900 sm:text-lg">العنوان وتاريخ الفعالية</h2>
                                    <p class="text-xs text-neutral-500">حدّد موقع التوصيل أو الفعالية</p>
                                </div>
                            </div>
                            <div class="space-y-5 p-4 sm:p-6">
                                <div>
                                    <Label class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-neutral-700">
                                        <MapPin class="h-3.5 w-3.5 text-neutral-400" />
                                        العنوان
                                    </Label>
                                    <AddressMapPicker v-model="form.address" />
                                    <p v-if="errors.address" class="mt-1.5 text-sm text-red-600">{{ errors.address }}</p>
                                </div>
                                <div>
                                    <Label for="activity_date" class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-neutral-700">
                                        <Calendar class="h-3.5 w-3.5 text-neutral-400" />
                                        تاريخ الفعالية
                                    </Label>
                                    <Input
                                        id="activity_date"
                                        v-model="form.activity_date"
                                        type="date"
                                        :min="minDate"
                                        class="h-12 rounded-xl border-neutral-200 bg-[#f4f6f8] focus:bg-white"
                                        required
                                    />
                                    <p v-if="errors.activity_date" class="mt-1.5 text-sm text-red-600">{{ errors.activity_date }}</p>
                                </div>
                            </div>
                        </section>

                        <!-- Desktop pay button -->
                        <button
                            type="submit"
                            class="hidden min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#3b89d2] px-4 py-3.5 text-base font-bold text-white shadow-sm transition hover:bg-[#2f6eb0] disabled:opacity-60 lg:flex"
                            :disabled="loading"
                        >
                            <CreditCard class="h-5 w-5 shrink-0" />
                            {{ loading ? 'جاري التوجيه للدفع...' : 'ادفع' }}
                            <Lock class="h-4 w-4 shrink-0 opacity-80" />
                        </button>
                    </form>

                    <!-- Order summary -->
                    <div class="lg:col-span-1">
                        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6 lg:sticky lg:top-24">
                            <h2 class="border-b border-neutral-100 pb-3 text-base font-extrabold text-neutral-900 sm:text-lg">
                                ملخص الطلب
                            </h2>

                            <ul class="max-h-52 divide-y divide-neutral-100 overflow-y-auto pt-2">
                                <li
                                    v-for="item in cartItems"
                                    :key="item.product_id"
                                    class="flex gap-3 py-3.5 first:pt-2"
                                >
                                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-neutral-100 bg-[#f4f6f8]">
                                        <img
                                            v-if="item.image"
                                            :src="item.image"
                                            :alt="item.product_name"
                                            class="h-full w-full object-cover"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center bg-gradient-to-br from-[#3b89d2] to-[#2f6eb0] text-sm font-bold text-white"
                                        >
                                            {{ item.product_name.charAt(0) }}
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="line-clamp-2 text-sm font-bold leading-snug text-neutral-900">
                                            {{ item.product_name }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-neutral-500">
                                            {{ fmt(item.price) }} ريال/يوم · {{ item.quantity }} × {{ item.duration }} يوم
                                        </p>
                                        <p class="mt-1 text-sm font-extrabold tabular-nums text-[#3b89d2]">
                                            {{ fmt(lineTotal(item.price, item.quantity, item.duration)) }} ريال
                                        </p>
                                    </div>
                                </li>
                            </ul>

                            <div class="mt-4 flex flex-col gap-2.5 border-t border-neutral-100 pt-4 text-sm sm:gap-3 sm:pt-5">
                                <div class="flex justify-between gap-3">
                                    <span class="text-neutral-500">المجموع الفرعي</span>
                                    <span class="shrink-0 font-semibold tabular-nums text-neutral-900">{{ fmt(subtotal) }} ريال</span>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <span class="text-neutral-500">التوصيل والتركيب</span>
                                    <span class="shrink-0 font-semibold text-[#3b89d2]">مجاني</span>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <span class="leading-snug text-neutral-500">ضريبة القيمة المضافة (15%)</span>
                                    <span class="shrink-0 font-semibold tabular-nums text-neutral-900">{{ fmt(vat) }} ريال</span>
                                </div>
                                <div
                                    v-if="insuranceTotal > 0"
                                    class="flex justify-between gap-3"
                                >
                                    <span class="leading-snug text-neutral-500">
                                        مبلغ التأمين
                                        <span class="block text-[11px] text-neutral-400">مسترد بعد انتهاء الفعالية · بدون ضريبة</span>
                                    </span>
                                    <span class="shrink-0 font-semibold tabular-nums text-amber-700">{{ fmt(insuranceTotal) }} ريال</span>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-3 border-t border-neutral-100 pt-4 sm:mt-5 sm:pt-5">
                                <span class="text-base font-extrabold text-neutral-900">الإجمالي</span>
                                <div class="text-end">
                                    <p class="text-xl font-extrabold tabular-nums text-[#3b89d2] sm:text-2xl">
                                        {{ fmt(grandTotal) }}
                                    </p>
                                    <p class="text-[11px] font-medium text-neutral-400">ريال سعودي شامل الضريبة</p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="mt-5 hidden min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#3b89d2] px-4 py-3.5 text-base font-bold text-white shadow-sm transition hover:bg-[#2f6eb0] disabled:opacity-60 lg:flex"
                                :disabled="loading"
                                @click="submit"
                            >
                                <CreditCard class="h-5 w-5 shrink-0" />
                                {{ loading ? 'جاري التوجيه...' : 'ادفع' }}
                            </button>

                            <p class="mt-4 flex items-center justify-center gap-1.5 text-xs text-neutral-500">
                                <ShieldCheck class="h-4 w-4 text-[#3b89d2]" />
                                معاملاتك آمنة ومشفّرة
                            </p>

                            <Link
                                :href="route('store.cart')"
                                class="mt-4 flex min-h-11 w-full items-center justify-center rounded-xl border border-neutral-300 bg-white py-3 text-sm font-semibold text-neutral-700 transition hover:bg-[#f4f6f8]"
                            >
                                العودة إلى السلة
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile sticky bar -->
            <div
                class="fixed inset-x-0 bottom-0 z-40 border-t border-neutral-200/90 bg-white/95 px-3 pt-3 shadow-[0_-8px_30px_rgba(0,0,0,0.08)] backdrop-blur-md lg:hidden"
                style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom, 0px))"
            >
                <div class="mx-auto flex max-w-lg items-center gap-3 pb-1">
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-medium text-neutral-500">الإجمالي شامل الضريبة</p>
                        <p class="text-lg font-extrabold tabular-nums leading-tight text-[#3b89d2]">
                            {{ fmt(grandTotal) }} ريال
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex min-h-12 min-w-[10.5rem] shrink-0 items-center justify-center gap-2 rounded-xl bg-[#3b89d2] px-5 text-sm font-bold text-white shadow-sm transition active:scale-[0.98] hover:bg-[#2f6eb0] disabled:opacity-60"
                        :disabled="loading"
                        @click="submit"
                    >
                        <Lock class="h-4 w-4 shrink-0" />
                        {{ loading ? 'جاري التوجيه...' : 'ادفع' }}
                    </button>
                </div>
            </div>
        </template>

        <AppFooter />
    </div>
</template>
