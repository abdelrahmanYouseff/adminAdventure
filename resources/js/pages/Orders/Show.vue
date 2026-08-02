<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowRight, User, Mail, Phone, CreditCard, FileText, Calendar, Package, HardHat, Pencil } from 'lucide-vue-next';
import { formatCurrency, formatDate, formatInteger } from '@/lib/formatNumber';

interface OrderItem {
    name: string;
    quantity: number;
    price: number;
    discount_amount?: number;
    duration?: number;
    amount?: number;
}

interface ProductPivot {
    quantity: number;
    price: number;
    discount_amount?: number;
}

interface Product {
    id: number;
    product_name: string;
    pivot: ProductPivot;
}

interface Invoice {
    id: number;
    invoice_number: string;
    amount: number;
    status: string;
}

interface Order {
    id: number;
    order_number: string;
    customer_name: string;
    customer_email: string | null;
    customer_phone: string | null;
    total_amount: number;
    discount_total?: number | null;
    amount_paid?: number | null;
    remaining_amount?: number | null;
    currency: string;
    payment_method: string;
    payment_status?: string | null;
    payment_id: string | null;
    status: string;
    activity_date?: string | null;
    activity_time?: string | null;
    address?: string | null;
    notes: string | null;
    items: OrderItem[] | null;
    created_at: string;
    updated_at: string;
    user?: { name: string; email: string } | null;
    invoice?: Invoice | null;
    products?: Product[];
}

interface Props {
    order: Order;
}

const props = defineProps<Props>();

defineOptions({ layout: AppLayout });

const page = usePage();
const successMessage = computed(() => (page.props.flash as { success?: string } | undefined)?.success);
const isPaid = computed(() => props.order.status === 'paid' || props.order.payment_status === 'paid');

const getStatusText = (status: string) => {
    const map: Record<string, string> = {
        pending: 'قيد الانتظار',
        processing: 'قيد المعالجة',
        paid: 'مدفوع',
        cancelled: 'ملغي',
        refunded: 'مسترد',
    };
    return map[status] || status;
};

const getStatusBadgeVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'pending':
        case 'processing':
            return 'secondary';
        case 'paid':
            return 'default';
        case 'cancelled':
        case 'refunded':
            return 'destructive';
        default:
            return 'outline';
    }
};

const getPaymentMethodText = (method: string) => {
    const map: Record<string, string> = {
        credit_card: 'بطاقة ائتمان',
        cash: 'نقدي',
        bank_transfer: 'تحويل بنكي',
        paypal: 'PayPal',
        noon: 'Noon',
    };
    return map[method] || method;
};

const orderItems = () => {
    const rows: { name: string; quantity: number; price: number; discount: number; total: number }[] = [];
    const items = props.order.items || [];

    if (items.length > 0) {
        items.forEach((item: OrderItem) => {
            const q = Number(item.quantity) || 0;
            const p = Number(item.price) || 0;
            const discount = Number(item.discount_amount) || 0;
            const duration = Number(item.duration) || 1;
            const total = item.amount != null ? Number(item.amount) : q * (p - discount) * duration;

            rows.push({
                name: item.name || '—',
                quantity: q,
                price: p,
                discount,
                total,
            });
        });

        return rows;
    }

    (props.order.products || []).forEach((product: Product) => {
        const q = Number(product.pivot?.quantity) || 0;
        const p = Number(product.pivot?.price) || 0;
        const discount = Number(product.pivot?.discount_amount) || 0;
        rows.push({
            name: product.product_name || '—',
            quantity: q,
            price: p,
            discount,
            total: q * (p - discount),
        });
    });

    return rows;
};
</script>

<template>
    <Head :title="`طلب ${order.order_number}`" />
    <div class="space-y-6 py-6">
        <div
            v-if="successMessage"
            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
        >
            {{ successMessage }}
        </div>

        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Link :href="route('orders.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowRight class="me-2 h-4 w-4" />
                        العودة للطلبات
                    </Button>
                </Link>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight font-mono">{{ order.order_number }}</h1>
                    <Badge :variant="getStatusBadgeVariant(order.status)">
                        {{ getStatusText(order.status) }}
                    </Badge>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="order.status !== 'cancelled' && order.status !== 'refunded'"
                    as-child
                    variant="outline"
                    class="h-10 gap-2"
                >
                    <Link :href="route('orders.edit', order.id)">
                        <Pencil class="h-4 w-4" />
                        تعديل الطلب
                    </Link>
                </Button>
                <Button
                    v-if="isPaid"
                    as-child
                    variant="outline"
                    class="h-10 gap-2"
                >
                    <Link :href="`/worker-orders/${order.id}`">
                        <HardHat class="h-4 w-4" />
                        فتح أمر العمل
                    </Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- بيانات العميل -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <User class="h-5 w-5" />
                        بيانات العميل
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p class="font-medium">{{ order.customer_name }}</p>
                    <p v-if="order.customer_email" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Mail class="h-4 w-4 shrink-0" />
                        {{ order.customer_email }}
                    </p>
                    <p v-if="order.customer_phone" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Phone class="h-4 w-4 shrink-0" />
                        {{ order.customer_phone }}
                    </p>
                    <p v-if="!order.customer_email && !order.customer_phone" class="text-sm text-muted-foreground">
                        لا يوجد بريد أو هاتف
                    </p>
                </CardContent>
            </Card>

            <!-- الدفع والفاتورة -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CreditCard class="h-5 w-5" />
                        الدفع والفاتورة
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p class="flex justify-between text-sm">
                        <span class="text-muted-foreground">طريقة الدفع</span>
                        <span>{{ getPaymentMethodText(order.payment_method) }}</span>
                    </p>
                    <p v-if="order.payment_id" class="flex justify-between text-sm">
                        <span class="text-muted-foreground">رقم/معرف الدفع</span>
                        <span class="font-mono">{{ order.payment_id }}</span>
                    </p>
                    <p class="flex justify-between text-sm">
                        <span class="text-muted-foreground">الإجمالي</span>
                        <span class="font-bold text-green-600 dark:text-green-400">
                            {{ formatCurrency(Number(order.total_amount), order.currency) }}
                        </span>
                    </p>
                    <p v-if="order.invoice" class="flex justify-between text-sm">
                        <span class="text-muted-foreground">الفاتورة</span>
                        <span class="font-mono">{{ order.invoice.invoice_number }}</span>
                    </p>
                    <p class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Calendar class="h-4 w-4 shrink-0" />
                        {{ formatDate(order.created_at) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- عناصر الطلب -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Package class="h-5 w-5" />
                    عناصر الطلب
                </CardTitle>
                <CardDescription>
                    المنتجات والبنود المطلوبة
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>المنتج / البند</TableHead>
                            <TableHead class="text-center">الكمية</TableHead>
                            <TableHead class="text-left">السعر</TableHead>
                            <TableHead class="text-left">الخصم / وحدة</TableHead>
                            <TableHead class="text-left">الإجمالي</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="orderItems().length === 0">
                            <TableCell colspan="5" class="text-center text-muted-foreground py-8">
                                لا توجد عناصر
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="(row, i) in orderItems()" :key="i">
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell class="text-center tabular-nums" dir="ltr">{{ formatInteger(row.quantity) }}</TableCell>
                            <TableCell>{{ formatCurrency(row.price, order.currency) }}</TableCell>
                            <TableCell :class="row.discount > 0 ? 'font-medium text-amber-700' : 'text-muted-foreground'">
                                {{ formatCurrency(row.discount, order.currency) }}
                            </TableCell>
                            <TableCell>{{ formatCurrency(row.total, order.currency) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <div class="mt-4 space-y-2 border-t pt-4 text-sm">
                    <div v-if="Number(order.discount_total ?? 0) > 0" class="flex justify-between text-amber-700">
                        <span>إجمالي الخصم</span>
                        <span class="font-semibold tabular-nums" dir="ltr">
                            - {{ formatCurrency(Number(order.discount_total), order.currency) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">إجمالي المطلوب</span>
                        <span class="font-semibold tabular-nums" dir="ltr">
                            {{ formatCurrency(Number(order.total_amount), order.currency) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">المدفوع</span>
                        <span class="font-semibold tabular-nums text-emerald-700" dir="ltr">
                            {{ formatCurrency(Number(order.amount_paid ?? 0), order.currency) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">المتبقي</span>
                        <span
                            class="text-lg font-bold tabular-nums"
                            :class="Number(order.remaining_amount ?? 0) > 0 ? 'text-amber-600' : 'text-emerald-600'"
                            dir="ltr"
                        >
                            {{ formatCurrency(Number(order.remaining_amount ?? Math.max(0, Number(order.total_amount) - Number(order.amount_paid ?? 0))), order.currency) }}
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- ملاحظات -->
        <Card v-if="order.notes">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <FileText class="h-5 w-5" />
                    ملاحظات
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground whitespace-pre-wrap">{{ order.notes }}</p>
            </CardContent>
        </Card>
    </div>
</template>
