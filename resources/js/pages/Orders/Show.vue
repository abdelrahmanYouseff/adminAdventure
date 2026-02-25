<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowRight, User, Mail, Phone, CreditCard, FileText, Calendar, Package } from 'lucide-vue-next';

interface OrderItem {
    name: string;
    quantity: number;
    price: number;
}

interface ProductPivot {
    quantity: number;
    price: number;
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
    currency: string;
    payment_method: string;
    payment_id: string | null;
    status: string;
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

const formatCurrency = (amount: number, currency: string) => {
    return new Intl.NumberFormat('ar-SA', {
        style: 'currency',
        currency: currency || 'SAR',
    }).format(Number(amount) || 0);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const orderItems = () => {
    const rows: { name: string; quantity: number; price: number; total: number }[] = [];
    const items = props.order.items || [];
    items.forEach((item: OrderItem) => {
        const q = Number(item.quantity) || 0;
        const p = Number(item.price) || 0;
        rows.push({
            name: item.name || '—',
            quantity: q,
            price: p,
            total: q * p,
        });
    });
    (props.order.products || []).forEach((product: Product) => {
        const q = Number(product.pivot?.quantity) || 0;
        const p = Number(product.pivot?.price) || 0;
        rows.push({
            name: product.product_name || '—',
            quantity: q,
            price: p,
            total: q * p,
        });
    });
    return rows;
};
</script>

<template>
    <Head :title="`طلب ${order.order_number}`" />
    <div class="space-y-6 py-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Link :href="route('orders.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowRight class="ml-2 h-4 w-4" />
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
                            <TableHead class="text-left">الإجمالي</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="orderItems().length === 0">
                            <TableCell colspan="4" class="text-center text-muted-foreground py-8">
                                لا توجد عناصر
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="(row, i) in orderItems()" :key="i">
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell class="text-center">{{ row.quantity }}</TableCell>
                            <TableCell>{{ formatCurrency(row.price, order.currency) }}</TableCell>
                            <TableCell>{{ formatCurrency(row.total, order.currency) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <div class="mt-4 flex justify-end border-t pt-4">
                    <p class="text-lg font-bold">
                        الإجمالي: {{ formatCurrency(Number(order.total_amount), order.currency) }}
                    </p>
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
