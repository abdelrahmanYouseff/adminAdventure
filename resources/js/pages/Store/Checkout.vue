<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useStoreCart } from '@/composables/useStoreCart';
import { Lock, ShoppingBag, ArrowRight } from 'lucide-vue-next';

const { cartItems, total, syncFromStorage } = useStoreCart();

const form = ref({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    address: '',
    activity_date: '',
});
const errors = ref<Record<string, string>>({});
const loading = ref(false);

onMounted(() => syncFromStorage());

const payload = computed(() => {
    const page = usePage();
    const token = (page.props as { csrf_token?: string })?.csrf_token ?? '';
    return {
        ...form.value,
        _token: token,
        items: cartItems.value.map((item) => ({
            product_id: item.product_id,
            product_name: item.product_name,
            quantity: item.quantity,
            price: item.price,
        })),
    };
});

function getCsrfHeaders(): Record<string, string> {
    const page = usePage();
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
                'Accept': 'application/json',
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
    <Head title="إتمام الطلب - عالم المغامرة" />

    <div class="min-h-screen bg-neutral-50 dark:bg-neutral-950">
        <!-- Header -->
        <header class="sticky top-0 z-30 border-b border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link href="/store" class="flex items-center">
                    <AppLogo />
                </Link>
                <div class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                    <Link :href="route('store.cart')" class="hover:text-neutral-900 dark:hover:text-white">السلة</Link>
                    <span>/</span>
                    <span class="font-medium text-neutral-900 dark:text-white">إتمام الطلب</span>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
            <h1 class="mb-8 text-2xl font-semibold tracking-tight text-neutral-900 dark:text-white sm:text-3xl">
                إتمام الطلب
            </h1>

            <!-- Empty cart -->
            <div
                v-if="cartItems.length === 0"
                class="flex flex-col items-center justify-center rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 py-16 px-6 text-center"
            >
                <div class="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-neutral-100 dark:bg-neutral-800">
                    <ShoppingBag class="h-10 w-10 text-neutral-400 dark:text-neutral-500" />
                </div>
                <h2 class="mb-2 text-xl font-semibold text-neutral-900 dark:text-white">السلة فارغة</h2>
                <p class="mb-8 max-w-sm text-neutral-500 dark:text-neutral-400">
                    أضف منتجات من المتجر ثم عد إلى السلة واضغط إتمام الطلب.
                </p>
                <Button as-child size="lg" class="rounded-full px-8">
                    <Link :href="route('store.index')">تصفح المتجر</Link>
                </Button>
            </div>

            <!-- Checkout: two columns (Shopify-style) -->
            <div v-else class="lg:grid lg:grid-cols-12 lg:gap-x-12">
                <!-- Form column -->
                <div class="lg:col-span-7">
                    <form @submit.prevent="submit" class="space-y-8">
                        <div v-if="errors.form" class="rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                            {{ errors.form }}
                        </div>

                        <!-- Contact -->
                        <section class="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-6 sm:p-8">
                            <h2 class="mb-6 text-lg font-semibold text-neutral-900 dark:text-white">
                                معلومات الاتصال
                            </h2>
                            <div class="space-y-4">
                                <div>
                                    <Label for="customer_name" class="text-neutral-700 dark:text-neutral-300">الاسم الكامل</Label>
                                    <Input
                                        id="customer_name"
                                        v-model="form.customer_name"
                                        type="text"
                                        class="mt-2 h-12 rounded-xl border-neutral-300 dark:border-neutral-600"
                                        required
                                        placeholder="الاسم الكامل"
                                    />
                                    <p v-if="errors.customer_name" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ errors.customer_name }}</p>
                                </div>
                                <div>
                                    <Label for="customer_email" class="text-neutral-700 dark:text-neutral-300">البريد الإلكتروني</Label>
                                    <Input
                                        id="customer_email"
                                        v-model="form.customer_email"
                                        type="email"
                                        class="mt-2 h-12 rounded-xl border-neutral-300 dark:border-neutral-600"
                                        required
                                        placeholder="example@email.com"
                                    />
                                    <p v-if="errors.customer_email" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ errors.customer_email }}</p>
                                </div>
                                <div>
                                    <Label for="customer_phone" class="text-neutral-700 dark:text-neutral-300">رقم الهاتف</Label>
                                    <Input
                                        id="customer_phone"
                                        v-model="form.customer_phone"
                                        type="tel"
                                        class="mt-2 h-12 rounded-xl border-neutral-300 dark:border-neutral-600"
                                        required
                                        placeholder="05xxxxxxxx"
                                    />
                                    <p v-if="errors.customer_phone" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ errors.customer_phone }}</p>
                                </div>
                            </div>
                        </section>

                        <!-- Address & date -->
                        <section class="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-6 sm:p-8">
                            <h2 class="mb-6 text-lg font-semibold text-neutral-900 dark:text-white">
                                العنوان وتاريخ الفعالية
                            </h2>
                            <div class="space-y-4">
                                <div>
                                    <Label for="address" class="text-neutral-700 dark:text-neutral-300">العنوان</Label>
                                    <textarea
                                        id="address"
                                        v-model="form.address"
                                        rows="3"
                                        class="mt-2 w-full rounded-xl border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-4 py-3 text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
                                        required
                                        placeholder="العنوان الكامل للتوصيل أو مكان الفعالية"
                                    />
                                    <p v-if="errors.address" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ errors.address }}</p>
                                </div>
                                <div>
                                    <Label for="activity_date" class="text-neutral-700 dark:text-neutral-300">تاريخ الفعالية</Label>
                                    <Input
                                        id="activity_date"
                                        v-model="form.activity_date"
                                        type="date"
                                        class="mt-2 h-12 rounded-xl border-neutral-300 dark:border-neutral-600"
                                        required
                                    />
                                    <p v-if="errors.activity_date" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ errors.activity_date }}</p>
                                </div>
                            </div>
                        </section>
                    </form>
                </div>

                <!-- Order summary column (sticky) -->
                <div class="mt-8 lg:col-span-5 lg:mt-0">
                    <div class="sticky top-24 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-6 shadow-sm lg:p-8">
                        <h2 class="mb-6 text-lg font-semibold text-neutral-900 dark:text-white">
                            ملخص الطلب
                        </h2>
                        <ul class="divide-y divide-neutral-200 dark:divide-neutral-800 max-h-60 overflow-y-auto">
                            <li
                                v-for="item in cartItems"
                                :key="item.product_id"
                                class="flex justify-between gap-4 py-4 first:pt-0"
                            >
                                <span class="text-sm text-neutral-700 dark:text-neutral-300">
                                    {{ item.product_name }}
                                    <span class="text-neutral-500 dark:text-neutral-400">× {{ item.quantity }}</span>
                                </span>
                                <span class="shrink-0 text-sm font-medium text-neutral-900 dark:text-white tabular-nums">
                                    {{ (item.price * item.quantity).toLocaleString('ar-SA') }} ريال
                                </span>
                            </li>
                        </ul>
                        <div class="mt-6 space-y-2 border-t border-neutral-200 dark:border-neutral-800 pt-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-neutral-500 dark:text-neutral-400">المجموع الفرعي</span>
                                <span class="font-medium text-neutral-900 dark:text-white tabular-nums">
                                    {{ total.toLocaleString('ar-SA') }} ريال
                                </span>
                            </div>
                            <div class="flex justify-between text-lg font-semibold">
                                <span class="text-neutral-900 dark:text-white">الإجمالي</span>
                                <span class="text-neutral-900 dark:text-white tabular-nums">
                                    {{ total.toLocaleString('ar-SA') }} ريال
                                </span>
                            </div>
                        </div>
                        <Button
                            type="button"
                            class="mt-6 w-full rounded-xl py-6 text-base font-medium"
                            :disabled="loading"
                            @click="submit"
                        >
                            <template v-if="loading">جاري التوجيه للدفع...</template>
                            <template v-else>
                                <Lock class="ml-2 h-4 w-4" />
                                الدفع الآمن عبر نون
                                <ArrowRight class="mr-2 h-4 w-4" />
                            </template>
                        </Button>
                        <p class="mt-4 flex items-center justify-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400">
                            <Lock class="h-3.5 w-3.5" />
                            معاملاتك آمنة ومشفرة
                        </p>
                        <Link
                            :href="route('store.cart')"
                            class="mt-6 block text-center text-sm font-medium text-neutral-600 underline-offset-4 hover:underline dark:text-neutral-400"
                        >
                            العودة إلى السلة
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Submit button for mobile (inside form, shown only on small screens) -->
            <div class="mt-8 lg:hidden">
                <Button
                    type="button"
                    class="w-full rounded-xl py-6 text-base font-medium"
                    :disabled="loading"
                    @click="submit"
                >
                    <Lock class="ml-2 h-4 w-4" />
                    {{ loading ? 'جاري التوجيه للدفع...' : 'الدفع الآمن عبر نون' }}
                </Button>
            </div>
        </main>
    </div>
</template>
