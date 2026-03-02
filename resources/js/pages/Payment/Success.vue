<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Copy, Check, MessageCircle } from 'lucide-vue-next';

interface OrderItem {
    name: string;
    quantity: number;
    amount: number;
    price?: number;
}

interface OrderDetails {
    order_number: string;
    customer_name: string;
    customer_phone: string | null;
    customer_email: string | null;
    address: string | null;
    activity_date: string | null;
    total_amount: number;
    currency: string;
    items: OrderItem[];
}

const props = defineProps<{
    processed: boolean;
    order_id: string | null;
    payment_id: string | null;
    order: OrderDetails | null;
    whatsapp_number: string;
}>();

const showPopup = ref(false);
const copied = ref(false);

onMounted(() => {
    if (props.processed) {
        showPopup.value = true;
    }
});

const copyOrderNumber = async () => {
    const text = props.order?.order_number ?? props.order_id ?? '';
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch {
        copied.value = false;
    }
};

const whatsappUrl = computed(() => {
    if (!props.whatsapp_number) return null;
    const orderRef = props.order?.order_number ?? props.order_id ?? '';
    const text = orderRef
        ? `مرحباً، أنا عميل. رقم طلبي: ${orderRef} - أود الاستفسار عن الطلب.`
        : 'مرحباً، أود الاستفسار.';
    return `https://wa.me/${props.whatsapp_number}?text=${encodeURIComponent(text)}`;
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-teal-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950">
        <Head title="تم الدفع بنجاح - عالم المغامرة" />

        <Dialog :open="showPopup" @update:open="showPopup = $event">
            <DialogContent class="text-center sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center justify-center gap-2 text-emerald-600 dark:text-emerald-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        تمت عملية الدفع بنجاح
                    </DialogTitle>
                </DialogHeader>
                <p class="text-muted-foreground py-2">
                    تم تأكيد دفعتك وإنشاء الطلب والفاتورة.
                </p>
                <DialogFooter class="flex justify-center sm:justify-center">
                    <Button type="button" @click="showPopup = false">
                        حسناً
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <div class="relative overflow-hidden">
            <div class="absolute inset-0 -z-10 opacity-30 pointer-events-none">
                <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-emerald-400 blur-3xl mix-blend-multiply"></div>
                <div class="absolute -bottom-24 -left-24 w-72 h-72 rounded-full bg-teal-400 blur-3xl mix-blend-multiply"></div>
            </div>

            <div class="max-w-2xl mx-auto px-4 pt-16 pb-12">
                <div class="flex items-center justify-center gap-4 mb-8">
                    <div class="w-14 h-14 rounded-2xl bg-white/80 dark:bg-white/10 backdrop-blur flex items-center justify-center shadow-lg ring-1 ring-black/5">
                        <AppLogo />
                    </div>
                </div>

                <div class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-xl rounded-3xl shadow-2xl ring-1 ring-black/5 dark:ring-white/5 overflow-hidden">
                    <div class="px-8 py-10 text-center">
                        <div class="mx-auto w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">
                            تم الدفع بنجاح
                        </h1>
                        <p class="text-gray-600 dark:text-gray-400 mb-8">
                            {{ processed ? 'تم تأكيد دفعتك وإنشاء الطلب والفاتورة.' : 'تم استلام إشعار الدفع. إذا لم يظهر الطلب، تواصل مع الدعم.' }}
                        </p>

                        <!-- تفاصيل الطلب -->
                        <div v-if="order" class="text-right rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-6 mb-8">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">تفاصيل الطلب</h2>

                            <!-- رقم الطلب + نسخ -->
                            <div class="flex items-center justify-between gap-3 mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                                <span class="text-sm text-gray-500 dark:text-gray-400">رقم الطلب</span>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ order.order_number }}</span>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        class="h-9 w-9 shrink-0"
                                        :title="copied ? 'تم النسخ' : 'نسخ رقم الطلب'"
                                        @click="copyOrderNumber"
                                    >
                                        <Check v-if="copied" class="h-4 w-4 text-emerald-600" />
                                        <Copy v-else class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between gap-4">
                                    <span class="text-gray-500 dark:text-gray-400">الاسم</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ order.customer_name }}</span>
                                </div>
                                <div v-if="order.customer_phone" class="flex justify-between gap-4">
                                    <span class="text-gray-500 dark:text-gray-400">رقم الهاتف</span>
                                    <a :href="`tel:${order.customer_phone}`" class="font-medium text-gray-900 dark:text-white">{{ order.customer_phone }}</a>
                                </div>
                                <div v-if="order.address" class="flex justify-between gap-4">
                                    <span class="text-gray-500 dark:text-gray-400">العنوان</span>
                                    <span class="font-medium text-gray-900 dark:text-white max-w-[60%] break-words">{{ order.address }}</span>
                                </div>
                                <div v-if="order.activity_date" class="flex justify-between gap-4">
                                    <span class="text-gray-500 dark:text-gray-400">تاريخ الفعالية</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ order.activity_date }}</span>
                                </div>
                            </div>

                            <!-- المنتجات -->
                            <div v-if="order.items && order.items.length" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">المنتجات</p>
                                <ul class="space-y-2">
                                    <li
                                        v-for="(item, i) in order.items"
                                        :key="i"
                                        class="flex justify-between text-sm"
                                    >
                                        <span class="text-gray-900 dark:text-white">{{ item.name }} × {{ item.quantity }}</span>
                                        <span class="text-gray-700 dark:text-gray-300 tabular-nums">{{ Number(item.amount ?? (item.price ?? 0) * item.quantity).toLocaleString('ar-SA') }} {{ order.currency }}</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- الإجمالي -->
                            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">الإجمالي</span>
                                <span class="text-lg font-semibold text-gray-900 dark:text-white tabular-nums">{{ Number(order.total_amount).toLocaleString('ar-SA') }} {{ order.currency }}</span>
                            </div>
                        </div>

                        <!-- عند عدم وجود تفاصيل: عرض رقم الطلب فقط مع نسخ -->
                        <div v-else-if="order_id" class="flex items-center justify-center gap-2 mb-8">
                            <span class="text-gray-500 dark:text-gray-400">رقم الطلب:</span>
                            <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ order_id }}</span>
                            <Button type="button" variant="outline" size="icon" class="h-9 w-9" @click="copyOrderNumber">
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                            <Link
                                href="/store"
                                class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-6 py-3 text-white font-medium hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                            >
                                العودة للمتجر
                            </Link>
                            <a
                                v-if="whatsappUrl"
                                :href="whatsappUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border-2 border-emerald-600 bg-white dark:bg-gray-900 px-6 py-3 text-emerald-600 dark:text-emerald-400 font-medium hover:bg-emerald-50 dark:hover:bg-emerald-950/30 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                            >
                                <MessageCircle class="h-5 w-5" />
                                تواصل واتساب
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
