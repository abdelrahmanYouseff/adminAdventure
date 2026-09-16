<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowRight, User, Mail, Phone, CreditCard, Calendar, Package, HardHat, Pencil, Copy, Check, Trash2, MessageSquareText } from 'lucide-vue-next';
import { formatCurrency, formatDate, formatDateTime, formatInteger } from '@/lib/formatNumber';

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

interface DismantlingMeta {
    status: string;
    label: string;
    scheduled_at?: string | null;
    warehouse_returned_at?: string | null;
    progress_done: number;
    progress_total: number;
}

interface ActivityNote {
    id: number;
    body: string;
    user_name: string;
    user_role: string;
    created_at: string | null;
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
    payment_url?: string | null;
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
    dismantling?: DismantlingMeta | null;
    warehouse_returned_at?: string | null;
    warehouse_returned_by_name?: string | null;
    can_edit?: boolean;
    can_delete_notes?: boolean;
    activity_notes?: ActivityNote[];
    is_locked?: boolean;
}

interface Props {
    order: Order;
}

const props = defineProps<Props>();

defineOptions({ layout: AppLayout });

const page = usePage();
const successMessage = computed(() => (page.props.flash as { success?: string } | undefined)?.success);
const isPaid = computed(() => props.order.status === 'paid' || props.order.payment_status === 'paid');
const paymentLinkCopied = ref(false);
const deletingNoteId = ref<number | 'order-field' | null>(null);
const noteForm = useForm({
    body: '',
});
const activityNotes = computed(() => props.order.activity_notes ?? []);
const canDeleteNotes = computed(() => Boolean(props.order.can_delete_notes));

function submitNote() {
    const body = noteForm.body.trim();
    if (!body) {
        noteForm.setError('body', 'يجب كتابة الملاحظة.');
        return;
    }

    noteForm.body = body;
    noteForm.post(`/orders/${props.order.id}/notes`, {
        preserveScroll: true,
        onSuccess: () => {
            noteForm.reset('body');
            noteForm.clearErrors();
        },
    });
}

function deleteActivityNote(note: ActivityNote) {
    if (!confirm('حذف هذه الملاحظة؟')) return;

    deletingNoteId.value = note.id;
    router.delete(`/orders/${props.order.id}/notes/${note.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingNoteId.value = null;
        },
    });
}

function deleteOrderFieldNote() {
    if (!confirm('حذف هذه الملاحظة؟')) return;

    deletingNoteId.value = 'order-field';
    router.delete(`/orders/${props.order.id}/notes/order-field`, {
        preserveScroll: true,
        onFinish: () => {
            deletingNoteId.value = null;
        },
    });
}

async function copyPaymentLink() {
    if (!props.order.payment_url) return;

    try {
        await navigator.clipboard.writeText(props.order.payment_url);
    } catch {
        const input = document.createElement('textarea');
        input.value = props.order.payment_url;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        input.remove();
    }

    paymentLinkCopied.value = true;
    window.setTimeout(() => {
        paymentLinkCopied.value = false;
    }, 2500);
}

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
                    <Badge
                        v-if="order.dismantling?.status === 'returned'"
                        variant="default"
                        class="bg-emerald-600"
                    >
                        تم الاسترجاع
                    </Badge>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="order.can_edit && !order.is_locked"
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
                    <p v-if="order.dismantling?.label && order.dismantling.status !== 'none'" class="flex justify-between text-sm">
                        <span class="text-muted-foreground">الفك والاسترجاع</span>
                        <span
                            class="font-semibold"
                            :class="order.dismantling.status === 'returned' ? 'text-emerald-600' : 'text-foreground'"
                        >
                            {{ order.dismantling.label }}
                        </span>
                    </p>
                    <p
                        v-if="order.warehouse_returned_at"
                        class="flex justify-between text-sm"
                    >
                        <span class="text-muted-foreground">تاريخ التعميد</span>
                        <span dir="ltr">{{ formatDateTime(order.warehouse_returned_at) }}</span>
                    </p>
                    <p
                        v-if="order.warehouse_returned_by_name"
                        class="flex justify-between text-sm"
                    >
                        <span class="text-muted-foreground">عُمِّد بواسطة</span>
                        <span>{{ order.warehouse_returned_by_name }}</span>
                    </p>
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
                    <Button
                        v-if="order.payment_url"
                        type="button"
                        variant="outline"
                        class="w-full gap-2"
                        @click="copyPaymentLink"
                    >
                        <Check v-if="paymentLinkCopied" class="h-4 w-4 text-emerald-600" />
                        <Copy v-else class="h-4 w-4" />
                        {{ paymentLinkCopied ? 'تم نسخ رابط الدفع' : 'نسخ رابط دفع المبلغ المستحق' }}
                    </Button>
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
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <MessageSquareText class="h-5 w-5" />
                    ملاحظات
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-if="order.notes" class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-slate-900">ملاحظة الطلب</span>
                        <Button
                            v-if="canDeleteNotes"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-8 gap-1 text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                            :disabled="deletingNoteId === 'order-field'"
                            @click="deleteOrderFieldNote"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            حذف
                        </Button>
                    </div>
                    <p class="whitespace-pre-wrap text-sm leading-relaxed text-muted-foreground">{{ order.notes }}</p>
                </div>

                <div v-if="activityNotes.length" class="space-y-3">
                    <article
                        v-for="note in activityNotes"
                        :key="note.id"
                        class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3"
                    >
                        <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="text-sm font-semibold text-slate-900">{{ note.user_name }}</span>
                                <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-500 ring-1 ring-slate-200">
                                    {{ note.user_role }}
                                </span>
                                <span v-if="note.created_at" class="text-[11px] text-slate-400" dir="ltr">
                                    {{ formatDateTime(note.created_at) }}
                                </span>
                            </div>
                            <Button
                                v-if="canDeleteNotes"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-8 gap-1 text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                                :disabled="deletingNoteId === note.id"
                                @click="deleteActivityNote(note)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                حذف
                            </Button>
                        </div>
                        <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ note.body }}</p>
                    </article>
                </div>

                <p v-if="!order.notes && activityNotes.length === 0" class="py-2 text-center text-sm text-slate-400">
                    لا توجد ملاحظات على هذا الطلب بعد.
                </p>

                <div class="space-y-2 border-t border-slate-100 pt-3">
                    <label class="block text-xs font-semibold text-slate-600">إضافة ملاحظة</label>
                    <textarea
                        v-model="noteForm.body"
                        rows="3"
                        maxlength="2000"
                        placeholder="اكتب ملاحظة..."
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    />
                    <p v-if="noteForm.errors.body" class="text-sm text-rose-600">{{ noteForm.errors.body }}</p>
                    <div class="flex justify-end">
                        <Button
                            type="button"
                            size="sm"
                            class="gap-1.5"
                            :disabled="noteForm.processing"
                            @click="submitNote"
                        >
                            <MessageSquareText class="size-3.5" />
                            {{ noteForm.processing ? 'جاري الحفظ...' : 'حفظ الملاحظة' }}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
