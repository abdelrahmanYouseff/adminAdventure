<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { ArrowRight, Check, Download, Mail, Printer, RefreshCw, X } from 'lucide-vue-next';
import { formatCurrency, formatDate as formatDateValue, formatDateTime as formatDateTimeValue } from '@/lib/formatNumber';
import type { BreadcrumbItem } from '@/types';

interface InvoiceUser {
    full_name?: string | null;
    name?: string | null;
    email?: string | null;
    phone?: string | null;
    country?: string | null;
}

interface InvoiceRental {
    product?: { product_name?: string | null } | null;
    rental_start_date?: string | null;
    rental_end_date?: string | null;
}

interface Invoice {
    id: number;
    invoice_number: string;
    amount: number | string;
    status: string;
    payment_method?: string | null;
    created_at: string;
    updated_at: string;
    due_date?: string | null;
    user?: InvoiceUser | null;
    rental?: InvoiceRental | null;
}

const props = defineProps<{
    invoice: Invoice;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'الفواتير', href: '/invoices' },
    { title: `فاتورة ${props.invoice.invoice_number}`, href: route('invoices.show', props.invoice.id) },
];

function updateStatus(status: string) {
    router.patch(route('invoices.update-status', props.invoice.id), { status });
}

function printInvoice() {
    window.open(route('invoices.pdf', props.invoice.id), '_blank');
}

function downloadPDF() {
    window.location.href = route('invoices.pdf', props.invoice.id);
}

function resendInvoice() {
    window.open(`mailto:${props.invoice.user?.email ?? ''}?subject=${encodeURIComponent(`فاتورة ${props.invoice.invoice_number}`)}`);
}

function formatDate(date?: string | null) {
    if (!date) return '—';
    return formatDateValue(date);
}

function formatDateTime(date?: string | null) {
    if (!date) return '—';
    return formatDateTimeValue(date);
}

function formatStatus(status: string) {
    const statusMap: Record<string, string> = {
        pending: 'قيد الانتظار',
        paid: 'مدفوعة',
        cancelled: 'ملغاة',
        overdue: 'متأخرة',
    };
    return statusMap[status] || status;
}

function formatPaymentMethod(method?: string | null) {
    const methodMap: Record<string, string> = {
        noon: 'نون باي',
        mock: 'دفع تجريبي',
        cash: 'نقداً',
        bank_transfer: 'تحويل بنكي',
    };
    return method ? methodMap[method] || method : '—';
}

function getStatusBadgeClass(status: string) {
    const classes: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        overdue: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    };
    return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
}
</script>

<template>
    <Head :title="`فاتورة ${invoice.invoice_number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 rounded-xl p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('invoices.index')">
                        <Button variant="outline" size="sm" class="gap-2">
                            <ArrowRight class="h-4 w-4" />
                            العودة للفواتير
                        </Button>
                    </Link>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white" dir="ltr">
                        {{ invoice.invoice_number }}
                    </h2>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" class="gap-2" @click="printInvoice">
                        <Printer class="h-4 w-4" />
                        عرض PDF
                    </Button>
                    <Button variant="outline" size="sm" class="gap-2" @click="downloadPDF">
                        <Download class="h-4 w-4" />
                        تحميل PDF
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>تفاصيل الفاتورة</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <Label class="text-sm text-gray-500">رقم الفاتورة</Label>
                                    <p class="text-lg font-semibold" dir="ltr">{{ invoice.invoice_number }}</p>
                                </div>
                                <div>
                                    <Label class="text-sm text-gray-500">الحالة</Label>
                                    <span
                                        :class="getStatusBadgeClass(invoice.status)"
                                        class="mt-1 inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
                                    >
                                        {{ formatStatus(invoice.status) }}
                                    </span>
                                </div>
                                <div>
                                    <Label class="text-sm text-gray-500">المبلغ</Label>
                                    <p class="text-2xl font-bold">{{ formatCurrency(invoice.amount) }}</p>
                                </div>
                                <div>
                                    <Label class="text-sm text-gray-500">طريقة الدفع</Label>
                                    <p class="text-lg">{{ formatPaymentMethod(invoice.payment_method) }}</p>
                                </div>
                                <div>
                                    <Label class="text-sm text-gray-500">تاريخ الإصدار</Label>
                                    <p class="text-lg" dir="ltr">{{ formatDate(invoice.created_at) }}</p>
                                </div>
                                <div>
                                    <Label class="text-sm text-gray-500">تاريخ الاستحقاق</Label>
                                    <p class="text-lg" dir="ltr">{{ formatDate(invoice.due_date) }}</p>
                                </div>
                            </div>

                            <div class="mt-8 border-t border-gray-200 pt-8 dark:border-gray-700">
                                <h3 class="mb-4 text-lg font-semibold">بيانات العميل</h3>
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div>
                                        <Label class="text-sm text-gray-500">الاسم</Label>
                                        <p class="text-lg">{{ invoice.user?.full_name || invoice.user?.name || '—' }}</p>
                                    </div>
                                    <div>
                                        <Label class="text-sm text-gray-500">البريد الإلكتروني</Label>
                                        <p class="text-lg" dir="ltr">{{ invoice.user?.email || '—' }}</p>
                                    </div>
                                    <div>
                                        <Label class="text-sm text-gray-500">الجوال</Label>
                                        <p class="text-lg" dir="ltr">{{ invoice.user?.phone || '—' }}</p>
                                    </div>
                                    <div>
                                        <Label class="text-sm text-gray-500">الدولة</Label>
                                        <p class="text-lg">{{ invoice.user?.country || '—' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="invoice.rental" class="mt-8 border-t border-gray-200 pt-8 dark:border-gray-700">
                                <h3 class="mb-4 text-lg font-semibold">بيانات الحجز</h3>
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div>
                                        <Label class="text-sm text-gray-500">المنتج</Label>
                                        <p class="text-lg">{{ invoice.rental.product?.product_name || '—' }}</p>
                                    </div>
                                    <div>
                                        <Label class="text-sm text-gray-500">فترة الحجز</Label>
                                        <p class="text-lg" dir="ltr">
                                            {{ formatDate(invoice.rental.rental_start_date) }}
                                            -
                                            {{ formatDate(invoice.rental.rental_end_date) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>إجراءات</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <Button
                                v-if="invoice.status === 'pending'"
                                class="w-full gap-2"
                                @click="updateStatus('paid')"
                            >
                                <Check class="h-4 w-4" />
                                تعليم كمدفوعة
                            </Button>

                            <Button
                                v-if="invoice.status === 'pending'"
                                variant="destructive"
                                class="w-full gap-2"
                                @click="updateStatus('cancelled')"
                            >
                                <X class="h-4 w-4" />
                                إلغاء الفاتورة
                            </Button>

                            <Button
                                v-if="invoice.status === 'paid'"
                                variant="outline"
                                class="w-full gap-2"
                                @click="updateStatus('pending')"
                            >
                                <RefreshCw class="h-4 w-4" />
                                إرجاع لقيد الانتظار
                            </Button>

                            <Button variant="outline" class="w-full gap-2" @click="resendInvoice">
                                <Mail class="h-4 w-4" />
                                إرسال بالبريد
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>سجل الفاتورة</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium">إنشاء الفاتورة</p>
                                    <p class="text-xs text-gray-500" dir="ltr">{{ formatDateTime(invoice.created_at) }}</p>
                                </div>
                            </div>

                            <div v-if="invoice.status === 'paid'" class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium">اكتمال الدفع</p>
                                    <p class="text-xs text-gray-500" dir="ltr">{{ formatDateTime(invoice.updated_at) }}</p>
                                </div>
                            </div>

                            <div v-if="invoice.status === 'cancelled'" class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium">إلغاء الفاتورة</p>
                                    <p class="text-xs text-gray-500" dir="ltr">{{ formatDateTime(invoice.updated_at) }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
