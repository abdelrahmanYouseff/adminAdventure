<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    ShoppingCart,
    Search,
    Mail,
    Phone,
    MoreHorizontal,
    Trash2,
    ChevronLeft,
    ChevronRight,
    Eye,
} from 'lucide-vue-next';

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
        from: number | null;
        to: number | null;
        per_page: number;
    };
    filters: {
        search?: string;
        status?: string;
        payment_method?: string;
        currency?: string;
    };
}

const props = withDefaults(
    defineProps<Props>(),
    {
        filters: () => ({}),
    }
);

defineOptions({ layout: AppLayout });

const searchInput = ref(props.filters?.search ?? '');
const statusFilter = ref(props.filters?.status ?? 'all');
const paymentFilter = ref(props.filters?.payment_method ?? 'all');
const currencyFilter = ref(props.filters?.currency ?? 'all');

function applyFilters(page = 1) {
    router.get(route('orders.index'), {
        search: searchInput.value || undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        payment_method: paymentFilter.value !== 'all' ? paymentFilter.value : undefined,
        currency: currencyFilter.value !== 'all' ? currencyFilter.value : undefined,
        page: page > 1 ? page : undefined,
    }, { preserveState: true });
}

watch([statusFilter, paymentFilter, currencyFilter], () => {
    applyFilters(1);
});

function onSearchSubmit() {
    applyFilters(1);
}

function goToPage(page: number) {
    if (page >= 1 && page <= props.orders.last_page) applyFilters(page);
}

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
    }).format(amount || 0);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const pendingCount = () => props.orders.data.filter((o) => o.status === 'pending').length;
const paidCount = () => props.orders.data.filter((o) => o.status === 'paid').length;
</script>

<template>
    <Head title="إدارة الطلبات" />
    <div class="space-y-6 py-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">إدارة الطلبات</h1>
                <p class="text-muted-foreground">
                    عرض وبحث وفلترة الطلبات
                </p>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">إجمالي الطلبات</CardTitle>
                    <ShoppingCart class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ orders.total }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">قيد الانتظار (هذه الصفحة)</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ pendingCount() }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">مدفوع (هذه الصفحة)</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ paidCount() }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">في هذه الصفحة</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ orders.data.length }}</div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters & Table -->
        <Card>
            <CardHeader>
                <CardTitle>قائمة الطلبات</CardTitle>
                <CardDescription>
                    استخدم البحث والفلترة لتضييق النتائج
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Filters -->
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                    <div class="flex-1 min-w-[200px]">
                        <Label for="search" class="sr-only">بحث</Label>
                        <div class="relative">
                            <Search class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="search"
                                v-model="searchInput"
                                type="search"
                                placeholder="رقم الطلب، اسم العميل، البريد..."
                                class="pr-10"
                                @keydown.enter.prevent="onSearchSubmit"
                            />
                        </div>
                    </div>
                    <Button @click="onSearchSubmit" type="button">
                        بحث
                    </Button>
                    <div class="flex flex-wrap gap-2">
                        <div class="flex flex-col gap-1">
                            <Label for="status" class="text-xs text-muted-foreground">الحالة</Label>
                            <select
                                id="status"
                                v-model="statusFilter"
                                class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs"
                            >
                                <option value="all">الكل</option>
                                <option value="pending">قيد الانتظار</option>
                                <option value="processing">قيد المعالجة</option>
                                <option value="paid">مدفوع</option>
                                <option value="cancelled">ملغي</option>
                                <option value="refunded">مسترد</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label for="payment" class="text-xs text-muted-foreground">طريقة الدفع</Label>
                            <select
                                id="payment"
                                v-model="paymentFilter"
                                class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs"
                            >
                                <option value="all">الكل</option>
                                <option value="credit_card">بطاقة ائتمان</option>
                                <option value="cash">نقدي</option>
                                <option value="bank_transfer">تحويل بنكي</option>
                                <option value="paypal">PayPal</option>
                                <option value="noon">Noon</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label for="currency" class="text-xs text-muted-foreground">العملة</Label>
                            <select
                                id="currency"
                                v-model="currencyFilter"
                                class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs"
                            >
                                <option value="all">الكل</option>
                                <option value="SAR">SAR</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>رقم الطلب</TableHead>
                                <TableHead>العميل</TableHead>
                                <TableHead>التواصل</TableHead>
                                <TableHead>المبلغ</TableHead>
                                <TableHead>طريقة الدفع</TableHead>
                                <TableHead>الحالة</TableHead>
                                <TableHead>التاريخ</TableHead>
                                <TableHead class="w-[50px]">إجراءات</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="orders.data.length === 0">
                                <TableCell colspan="8" class="h-24 text-center text-muted-foreground">
                                    لا توجد طلبات
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="order in orders.data"
                                :key="order.id"
                                class="hover:bg-muted/50"
                            >
                                <TableCell class="font-mono text-sm font-medium">
                                    {{ order.order_number }}
                                </TableCell>
                                <TableCell>
                                    {{ order.customer_name }}
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-col gap-0.5 text-sm text-muted-foreground">
                                        <span v-if="order.customer_email" class="flex items-center gap-1">
                                            <Mail class="h-3 w-3 shrink-0" />
                                            {{ order.customer_email }}
                                        </span>
                                        <span v-if="order.customer_phone" class="flex items-center gap-1">
                                            <Phone class="h-3 w-3 shrink-0" />
                                            {{ order.customer_phone }}
                                        </span>
                                        <span v-if="!order.customer_email && !order.customer_phone">—</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span class="font-semibold text-green-600 dark:text-green-400">
                                        {{ formatCurrency(Number(order.total_amount), order.currency) }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    {{ getPaymentMethodText(order.payment_method) }}
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="getStatusBadgeVariant(order.status)">
                                        {{ getStatusText(order.status) }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ formatDate(order.created_at) }}
                                </TableCell>
                                <TableCell>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" class="h-8 w-8 p-0">
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem as-child>
                                                <Link :href="route('orders.show', order.id)">
                                                    <Eye class="mr-2 h-4 w-4" />
                                                    عرض التفاصيل
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="deleteOrder(order)"
                                                class="text-destructive focus:text-destructive"
                                            >
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                حذف
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="orders.last_page > 1"
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        عرض
                        <span class="font-medium">{{ orders.from ?? 0 }}</span>
                        إلى
                        <span class="font-medium">{{ orders.to ?? 0 }}</span>
                        من
                        <span class="font-medium">{{ orders.total }}</span>
                        طلب
                    </p>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="orders.current_page <= 1"
                            @click="goToPage(orders.current_page - 1)"
                        >
                            <ChevronRight class="h-4 w-4" />
                            السابق
                        </Button>
                        <span class="text-sm text-muted-foreground">
                            {{ orders.current_page }} / {{ orders.last_page }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="orders.current_page >= orders.last_page"
                            @click="goToPage(orders.current_page + 1)"
                        >
                            التالي
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
