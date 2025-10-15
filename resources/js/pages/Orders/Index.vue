<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ShoppingCart, Mail, Phone, DollarSign, CreditCard, Calendar, Trash2 } from 'lucide-vue-next';

interface Order {
    id: number;
    order_number: string;
    customer_name: string;
    customer_email: string | null;
    customer_phone: string | null;
    total_amount: number;
    currency: string;
    payment_method: string;
    status: string;
    created_at: string;
}

interface Props {
    orders: {
        data: Order[];
        current_page: number;
        last_page: number;
        total: number;
        from: number;
        to: number;
    };
}

defineProps<Props>();

defineOptions({ layout: AppLayout });

const deleteOrder = (order: Order) => {
    if (confirm('هل تريد حذف هذا الطلب؟')) {
        router.delete(route('orders.destroy', order.id), {
            preserveScroll: true,
        });
    }
};

const getStatusText = (status: string) => {
    const map: Record<string, string> = {
        pending: 'قيد الانتظار',
        paid: 'مدفوع',
        cancelled: 'ملغي',
    };
    return map[status] || status;
};

const getPaymentMethodText = (method: string) => {
    const map: Record<string, string> = {
        credit_card: 'بطاقة ائتمان',
        cash: 'نقدي',
        noon: 'Noon',
    };
    return map[method] || method;
};

const formatCurrency = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency || 'SAR',
    }).format(amount || 0);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Orders" />
    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-neutral-800 sm:rounded-lg">
                <div class="p-6 text-neutral-900 dark:text-neutral-100">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <ShoppingCart class="w-8 h-8 text-blue-600" />
                            <h1 class="text-2xl font-semibold">إدارة الطلبات</h1>
                        </div>
                        <div class="text-sm text-neutral-500">
                            إجمالي: {{ orders.total }}
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-neutral-200 dark:border-neutral-700">
                            <thead>
                                <tr class="bg-neutral-50 dark:bg-neutral-700">
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left">رقم الطلب</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left">اسم العميل</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left">البريد/الهاتف</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left">المبلغ</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left">طريقة الدفع</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left">الحالة</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left">التاريخ</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-center">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="orders.data.length === 0">
                                    <td colspan="8" class="border px-4 py-8 text-center text-neutral-500">
                                        لا توجد طلبات
                                    </td>
                                </tr>
                                <tr v-for="order in orders.data" :key="order.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-700">
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="font-mono text-sm">{{ order.order_number }}</div>
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        {{ order.customer_name }}
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="text-sm">
                                            <div v-if="order.customer_email" class="flex items-center gap-1">
                                                <Mail class="w-3 h-3" />
                                                {{ order.customer_email }}
                                            </div>
                                            <div v-if="order.customer_phone" class="flex items-center gap-1">
                                                <Phone class="w-3 h-3" />
                                                {{ order.customer_phone }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <span class="font-bold text-green-600">
                                            {{ formatCurrency(order.total_amount, order.currency) }}
                                        </span>
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        {{ getPaymentMethodText(order.payment_method) }}
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <Badge>{{ getStatusText(order.status) }}</Badge>
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        {{ formatDate(order.created_at) }}
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-center">
                                        <Button @click="deleteOrder(order)" variant="destructive" size="sm">
                                            <Trash2 class="w-4 h-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
