<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    ArrowRight,
    Building2,
    Calendar,
    ChevronDown,
    CreditCard,
    Eye,
    FileText,
    ImageIcon,
    Mail,
    MapPin,
    MoreHorizontal,
    Package,
    Phone,
    Receipt,
    ShoppingBag,
    User,
    Wallet,
} from 'lucide-vue-next';
import { formatCurrency, formatDate, formatDateTime, formatInteger, formatPrice } from '@/lib/formatNumber';

interface QuotationItem {
    id: number;
    product_name: string;
    description: string | null;
    quantity: number;
    unit_price: number;
    total_price: number;
}

interface Quotation {
    id: number;
    quotation_number: string;
    customer_name: string;
    customer_email: string | null;
    customer_phone: string | null;
    customer_address: string | null;
    company_tax_number: string | null;
    valid_until: string | null;
    notes: string | null;
    subtotal: number;
    tax_amount: number;
    insurance_amount: number;
    total_amount: number;
    status: string;
    created_at: string;
    user: { id: number; name: string } | null;
    items: QuotationItem[];
}

interface OrderItemRow {
    product_id?: number | null;
    name: string;
    image_url?: string | null;
    quantity: number;
    price: number;
    discount_amount: number;
    total: number;
}

interface PaymentReceiptRow {
    id: number;
    receipt_number: string;
    amount: number;
    amount_paid_after: number;
    remaining_after: number;
    created_at: string | null;
}

interface OrderRow {
    id: number;
    order_number: string;
    invoice_number: string | null;
    customer_name: string | null;
    customer_phone: string | null;
    customer_email: string | null;
    address: string | null;
    activity_date: string | null;
    currency: string;
    total_amount: number;
    discount_total?: number;
    amount_paid: number;
    remaining_amount: number;
    insurance_amount: number;
    status: string;
    payment_status: string | null;
    payment_method: string | null;
    created_at: string | null;
    items: OrderItemRow[];
    products_count?: number;
    latest_receipt_id: number | null;
    receipts: PaymentReceiptRow[];
    can_settle: boolean;
    payment_receipt_url: string;
}

interface Customer {
    key: string;
    id: number;
    type: 'individual' | 'company';
    name: string;
    contact_name: string | null;
    phone: string | null;
    phone_secondary: string | null;
    email: string | null;
    address: string | null;
    tax_number: string | null;
    notes: string | null;
    country: string | null;
    gender: string | null;
    date_of_birth: string | null;
    profile_completed: boolean | null;
    iban: string | null;
    iban_image_url: string | null;
    created_at: string | null;
    quotations_count: number;
    quotations: Quotation[];
    orders: OrderRow[];
    orders_count: number;
}

interface Props {
    customer: Customer;
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);
const errorMessage = computed(() => page.props.flash?.error as string | undefined);
const expandedQuotationId = ref<number | null>(null);
const expandedOrderId = ref<number | null>(null);
const settleOrderId = ref<number | null>(null);
const productsDialogOpen = ref(false);
const productsDialogOrder = ref<OrderRow | null>(null);
const ibanImageInput = ref<HTMLInputElement | null>(null);
const ibanImagePreview = ref<string | null>(null);

const settleForm = useForm({
    amount: '' as number | string,
    payment_method: 'cash',
    notes: '',
});

const bankForm = useForm({
    phone_secondary: props.customer.phone_secondary ?? '',
    iban: props.customer.iban ?? '',
    iban_image: null as File | null,
    remove_iban_image: false as boolean,
});

watch(
    () => [props.customer.phone_secondary, props.customer.iban, props.customer.iban_image_url] as const,
    ([phoneSecondary, iban]) => {
        bankForm.phone_secondary = phoneSecondary ?? '';
        bankForm.iban = iban ?? '';
        bankForm.iban_image = null;
        bankForm.remove_iban_image = false;
        clearIbanPreview();
        if (ibanImageInput.value) {
            ibanImageInput.value.value = '';
        }
    },
);

const typeLabel = computed(() => (props.customer.type === 'company' ? 'شركة' : 'فرد'));

const genderLabel = computed(() => {
    switch (props.customer.gender) {
        case 'male':
            return 'ذكر';
        case 'female':
            return 'أنثى';
        default:
            return props.customer.gender || '—';
    }
});

const currentIbanImage = computed(() => {
    if (bankForm.remove_iban_image) {
        return null;
    }

    return ibanImagePreview.value || props.customer.iban_image_url;
});

function clearIbanPreview() {
    if (ibanImagePreview.value) {
        URL.revokeObjectURL(ibanImagePreview.value);
        ibanImagePreview.value = null;
    }
}

function onIbanImageSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    bankForm.iban_image = file;
    bankForm.remove_iban_image = false;
    clearIbanPreview();

    if (file) {
        ibanImagePreview.value = URL.createObjectURL(file);
    }
}

function removeIbanImage() {
    bankForm.iban_image = null;
    bankForm.remove_iban_image = true;
    clearIbanPreview();
    if (ibanImageInput.value) {
        ibanImageInput.value.value = '';
    }
}

function submitBank() {
    bankForm.post(`/customers/${props.customer.type}/${props.customer.id}/bank`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            bankForm.iban_image = null;
            bankForm.remove_iban_image = false;
            clearIbanPreview();
            if (ibanImageInput.value) {
                ibanImageInput.value.value = '';
            }
        },
    });
}

function toggleQuotation(id: number) {
    expandedQuotationId.value = expandedQuotationId.value === id ? null : id;
}

function getQuotationStatusText(status: string) {
    switch (status) {
        case 'draft':
            return 'مسودة';
        case 'sent':
            return 'مرسل';
        case 'accepted':
            return 'مقبول';
        case 'rejected':
            return 'مرفوض';
        case 'expired':
            return 'منتهي';
        default:
            return status;
    }
}

function getQuotationStatusClass(status: string) {
    switch (status) {
        case 'draft':
            return 'bg-slate-50 text-slate-700 ring-slate-200';
        case 'sent':
            return 'bg-sky-50 text-sky-700 ring-sky-200';
        case 'accepted':
            return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
        case 'rejected':
            return 'bg-rose-50 text-rose-700 ring-rose-200';
        case 'expired':
            return 'bg-amber-50 text-amber-700 ring-amber-200';
        default:
            return 'bg-muted text-muted-foreground ring-border';
    }
}

function getOrderStatusText(status: string) {
    switch (status) {
        case 'pending':
            return 'قيد الانتظار';
        case 'confirmed':
            return 'مؤكد';
        case 'processing':
            return 'قيد التنفيذ';
        case 'paid':
            return 'مدفوع';
        case 'completed':
            return 'مكتمل';
        case 'cancelled':
            return 'ملغي';
        default:
            return status;
    }
}

function toggleOrder(orderId: number) {
    expandedOrderId.value = expandedOrderId.value === orderId ? null : orderId;
}

function openProductsDialog(order: OrderRow) {
    productsDialogOrder.value = order;
    productsDialogOpen.value = true;
}

function openSettle(order: OrderRow) {
    settleOrderId.value = order.id;
    settleForm.amount = '';
    settleForm.payment_method = order.payment_method || 'cash';
    settleForm.notes = '';
    settleForm.clearErrors();
}

function fillFullRemaining(order: OrderRow) {
    settleForm.amount = Number(order.remaining_amount) || 0;
    settleForm.clearErrors('amount');
}

function closeSettle() {
    settleOrderId.value = null;
    settleForm.reset();
}

function submitSettle(order: OrderRow) {
    const amount = Number(settleForm.amount);
    const remaining = Number(order.remaining_amount);

    if (!Number.isFinite(amount) || amount <= 0) {
        settleForm.setError('amount', 'أدخل مبلغ سداد أكبر من صفر.');
        return;
    }

    if (amount > remaining + 0.009) {
        settleForm.setError('amount', `المبلغ أكبر من المتبقي (${remaining}). يمكنك سداد جزء منه فقط.`);
        return;
    }

    settleForm.clearErrors('amount');
    settleForm.amount = amount;

    settleForm.post(`/orders/${order.id}/settle-payment`, {
        preserveScroll: true,
        onSuccess: () => {
            closeSettle();
        },
    });
}

function receiptUrl(order: OrderRow, receiptId?: number | null) {
    if (receiptId) {
        return route('orders.payment-receipts.show', { order: order.id, receipt: receiptId });
    }
    return order.payment_receipt_url || route('orders.payment-receipt', order.id);
}
</script>

<template>
    <Head :title="`ملف العميل — ${customer.name}`" />

    <div class="space-y-6 p-4 sm:p-6" dir="rtl">
        <p
            v-if="successMessage"
            class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800"
        >
            {{ successMessage }}
        </p>
        <p
            v-if="errorMessage"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800"
        >
            {{ errorMessage }}
        </p>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <Link
                    href="/customers"
                    class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowRight class="h-4 w-4" />
                    العودة للعملاء
                </Link>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight">{{ customer.name }}</h1>
                    <span
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                        :class="customer.type === 'company'
                            ? 'bg-violet-50 text-violet-700 ring-violet-200'
                            : 'bg-sky-50 text-sky-700 ring-sky-200'"
                    >
                        <Building2 v-if="customer.type === 'company'" class="h-3 w-3" />
                        <User v-else class="h-3 w-3" />
                        {{ typeLabel }}
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">
                    ملف العميل — كل البيانات والطلبات وعروض الأسعار المرتبطة
                </p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">عروض الأسعار</CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-bold tabular-nums">
                    {{ formatInteger(customer.quotations_count) }}
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">الطلبات</CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-bold tabular-nums">
                    {{ formatInteger(customer.orders_count) }}
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">تاريخ التسجيل</CardTitle>
                </CardHeader>
                <CardContent class="text-base font-semibold tabular-nums" dir="ltr">
                    {{ customer.created_at ? formatDateTime(customer.created_at) : '—' }}
                </CardContent>
            </Card>
            <Card v-if="customer.type === 'individual'">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">اكتمال الملف</CardTitle>
                </CardHeader>
                <CardContent>
                    <Badge :variant="customer.profile_completed ? 'default' : 'secondary'">
                        {{ customer.profile_completed ? 'مكتمل' : 'غير مكتمل' }}
                    </Badge>
                </CardContent>
            </Card>
            <Card v-else>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">الرقم الضريبي</CardTitle>
                </CardHeader>
                <CardContent class="text-base font-semibold" dir="ltr">
                    {{ customer.tax_number || '—' }}
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader class="border-b pb-4">
                <CardTitle class="text-lg">بيانات العميل</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="divide-y">
                    <div class="grid sm:grid-cols-2">
                        <div class="flex items-start gap-3 px-4 py-3.5 sm:px-6 sm:border-l">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                <User class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 space-y-0.5">
                                <p class="text-xs text-muted-foreground">الاسم</p>
                                <p class="truncate text-sm font-semibold">{{ customer.name }}</p>
                            </div>
                        </div>

                        <div v-if="customer.type === 'company'" class="flex items-start gap-3 px-4 py-3.5 sm:px-6">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                                <Building2 class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 space-y-0.5">
                                <p class="text-xs text-muted-foreground">اسم المسؤول</p>
                                <p class="truncate text-sm font-semibold">{{ customer.contact_name || '—' }}</p>
                            </div>
                        </div>

                        <div v-else class="flex items-start gap-3 px-4 py-3.5 sm:px-6">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600">
                                <User class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 space-y-0.5">
                                <p class="text-xs text-muted-foreground">الجنس</p>
                                <p class="text-sm font-semibold">{{ genderLabel }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2">
                        <div class="flex items-start gap-3 px-4 py-3.5 sm:px-6 sm:border-l">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                <Phone class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 space-y-0.5">
                                <p class="text-xs text-muted-foreground">الجوال</p>
                                <p class="text-sm font-semibold tabular-nums" dir="ltr">{{ customer.phone || '—' }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 px-4 py-3.5 sm:px-6">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-700">
                                <Phone class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 space-y-0.5">
                                <p class="text-xs text-muted-foreground">جوال آخر</p>
                                <p class="text-sm font-semibold tabular-nums" dir="ltr">{{ customer.phone_secondary || '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2">
                        <div class="flex items-start gap-3 px-4 py-3.5 sm:px-6 sm:border-l">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700">
                                <Mail class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 space-y-0.5">
                                <p class="text-xs text-muted-foreground">البريد الإلكتروني</p>
                                <p class="break-all text-sm font-semibold" dir="ltr">{{ customer.email || '—' }}</p>
                            </div>
                        </div>

                        <div v-if="customer.type === 'company'" class="flex items-start gap-3 px-4 py-3.5 sm:px-6">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <Receipt class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 space-y-0.5">
                                <p class="text-xs text-muted-foreground">الرقم الضريبي</p>
                                <p class="text-sm font-semibold tabular-nums" dir="ltr">{{ customer.tax_number || '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="customer.type === 'company'" class="grid sm:grid-cols-2">
                        <div class="flex items-start gap-3 px-4 py-3.5 sm:px-6 sm:border-l sm:col-span-2">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                                <MapPin class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 space-y-0.5">
                                <p class="text-xs text-muted-foreground">العنوان</p>
                                <p class="text-sm font-semibold">{{ customer.address || '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-else class="grid sm:grid-cols-2">
                        <div class="flex items-start gap-3 px-4 py-3.5 sm:px-6 sm:border-l">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                                <MapPin class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 space-y-0.5">
                                <p class="text-xs text-muted-foreground">الدولة</p>
                                <p class="text-sm font-semibold">{{ customer.country || '—' }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 px-4 py-3.5 sm:px-6">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <Calendar class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 space-y-0.5">
                                <p class="text-xs text-muted-foreground">تاريخ الميلاد</p>
                                <p class="text-sm font-semibold tabular-nums" dir="ltr">
                                    {{ customer.date_of_birth ? formatDate(customer.date_of_birth) : '—' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="customer.notes" class="px-4 py-3.5 sm:px-6">
                        <p class="mb-1.5 text-xs text-muted-foreground">ملاحظات</p>
                        <p class="whitespace-pre-wrap rounded-lg bg-muted/50 px-3 py-2.5 text-sm leading-relaxed">
                            {{ customer.notes }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="border-b pb-4">
                <CardTitle class="flex items-center gap-2 text-lg">
                    <CreditCard class="h-5 w-5" />
                    الحساب البنكي (الآيبان)
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-5 p-4 sm:p-6">
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitBank">
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="customer_phone_secondary">جوال آخر</Label>
                        <Input
                            id="customer_phone_secondary"
                            v-model="bankForm.phone_secondary"
                            class="h-11"
                            dir="ltr"
                            placeholder="05xxxxxxxx"
                        />
                        <p v-if="bankForm.errors.phone_secondary" class="text-sm text-destructive">
                            {{ bankForm.errors.phone_secondary }}
                        </p>
                    </div>

                    <div class="space-y-2 sm:col-span-2">
                        <Label for="customer_iban">رقم الآيبان</Label>
                        <Input
                            id="customer_iban"
                            v-model="bankForm.iban"
                            class="h-11 font-mono tracking-wide"
                            dir="ltr"
                            placeholder="SAxxxxxxxxxxxxxxxxxxxxxx"
                            maxlength="34"
                        />
                        <p v-if="bankForm.errors.iban" class="text-sm text-destructive">{{ bankForm.errors.iban }}</p>
                    </div>

                    <div class="space-y-2 sm:col-span-2">
                        <Label for="customer_iban_image">صورة الآيبان</Label>
                        <input
                            id="customer_iban_image"
                            ref="ibanImageInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            class="block w-full cursor-pointer rounded-lg border border-input bg-background px-3 py-2.5 text-sm file:me-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white"
                            @change="onIbanImageSelected"
                        />
                        <p class="text-xs text-muted-foreground">JPG أو PNG أو WebP — بحد أقصى 5 ميجابايت</p>
                        <p v-if="bankForm.errors.iban_image" class="text-sm text-destructive">{{ bankForm.errors.iban_image }}</p>
                    </div>

                    <div v-if="currentIbanImage" class="sm:col-span-2">
                        <div class="relative inline-block overflow-hidden rounded-xl border bg-muted/20 p-2">
                            <a :href="currentIbanImage" target="_blank" rel="noopener noreferrer" class="block">
                                <img
                                    :src="currentIbanImage"
                                    alt="صورة الآيبان"
                                    class="max-h-56 max-w-full rounded-lg object-contain"
                                />
                            </a>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                class="absolute start-3 top-3 h-8"
                                @click="removeIbanImage"
                            >
                                إزالة الصورة
                            </Button>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex items-center gap-2 rounded-xl border border-dashed px-4 py-6 text-sm text-muted-foreground sm:col-span-2"
                    >
                        <ImageIcon class="h-4 w-4 shrink-0" />
                        لا توجد صورة آيبان مرفوعة حالياً.
                    </div>

                    <div class="flex justify-end sm:col-span-2">
                        <Button type="submit" class="h-10 min-w-32" :disabled="bankForm.processing">
                            {{ bankForm.processing ? 'جاري الحفظ...' : 'حفظ الحساب البنكي' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between gap-2">
                <CardTitle class="flex items-center gap-2 text-lg">
                    <ShoppingBag class="h-5 w-5" />
                    الطلبات
                    <Badge variant="secondary" class="tabular-nums">{{ formatInteger(customer.orders_count) }}</Badge>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="customer.orders.length === 0" class="rounded-xl border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                    لا توجد طلبات مرتبطة بهذا العميل.
                </div>
                <div v-else class="overflow-x-auto rounded-xl border bg-background">
                    <table class="w-full min-w-[1180px] text-sm">
                        <thead class="border-b bg-slate-50 text-xs text-slate-600">
                            <tr>
                                <th class="w-10 px-3 py-3 text-center font-semibold"></th>
                                <th class="px-3 py-3 text-right font-semibold">رقم الطلب</th>
                                <th class="px-3 py-3 text-right font-semibold">المنتجات</th>
                                <th class="px-3 py-3 text-right font-semibold">تاريخ الفعالية</th>
                                <th class="px-3 py-3 text-left font-semibold">الإجمالي</th>
                                <th class="px-3 py-3 text-left font-semibold">المدفوع</th>
                                <th class="px-3 py-3 text-left font-semibold">المتبقي</th>
                                <th class="w-16 px-3 py-3 text-center font-semibold">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="order in customer.orders" :key="order.id">
                                <tr class="border-b transition-colors hover:bg-slate-50/70">
                                    <td class="px-3 py-3 text-center">
                                        <button
                                            type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                            :aria-label="expandedOrderId === order.id ? 'إخفاء التفاصيل' : 'عرض التفاصيل'"
                                            @click="toggleOrder(order.id)"
                                        >
                                            <ChevronDown
                                                class="h-4 w-4 transition-transform"
                                                :class="expandedOrderId === order.id ? 'rotate-180' : ''"
                                            />
                                        </button>
                                    </td>
                                    <td class="px-3 py-3">
                                        <p class="font-semibold tabular-nums text-slate-900" dir="ltr">{{ order.order_number }}</p>
                                        <p v-if="order.invoice_number" class="mt-0.5 text-xs text-muted-foreground" dir="ltr">
                                            {{ order.invoice_number }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="h-8 gap-1.5"
                                            :disabled="!order.items.length"
                                            @click="openProductsDialog(order)"
                                        >
                                            <Package class="h-3.5 w-3.5" />
                                            عرض المنتجات
                                            <span
                                                v-if="order.items.length"
                                                class="rounded-full bg-muted px-1.5 text-[11px] font-semibold tabular-nums"
                                            >
                                                {{ formatInteger(order.products_count ?? order.items.length) }}
                                            </span>
                                        </Button>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 font-medium tabular-nums" dir="ltr">
                                        {{ order.activity_date ? formatDate(order.activity_date) : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-left font-semibold tabular-nums" dir="ltr">
                                        {{ formatCurrency(order.total_amount, order.currency) }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-left font-semibold tabular-nums text-emerald-700" dir="ltr">
                                        {{ formatCurrency(order.amount_paid, order.currency) }}
                                    </td>
                                    <td
                                        class="whitespace-nowrap px-3 py-3 text-left font-bold tabular-nums"
                                        :class="order.remaining_amount > 0 ? 'text-amber-600' : 'text-emerald-600'"
                                        dir="ltr"
                                    >
                                        {{ formatCurrency(order.remaining_amount, order.currency) }}
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="icon" class="h-8 w-8">
                                                    <MoreHorizontal class="h-4 w-4" />
                                                    <span class="sr-only">الإجراءات</span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-44">
                                                <DropdownMenuItem as-child>
                                                    <a :href="receiptUrl(order)" target="_blank" rel="noopener" class="cursor-pointer">
                                                        <FileText class="ms-2 h-4 w-4" />
                                                        سند قبض
                                                    </a>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="order.can_settle"
                                                    class="cursor-pointer"
                                                    @click="openSettle(order)"
                                                >
                                                    <Wallet class="ms-2 h-4 w-4" />
                                                    سداد
                                                </DropdownMenuItem>
                                                <DropdownMenuItem as-child>
                                                    <Link :href="`/orders/${order.id}`" class="cursor-pointer">
                                                        <Eye class="ms-2 h-4 w-4" />
                                                        عرض الطلب
                                                    </Link>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </td>
                                </tr>

                                <tr v-if="expandedOrderId === order.id" class="border-b bg-slate-50/70">
                                    <td colspan="8" class="p-4">
                                        <div class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)]">
                                            <div class="overflow-hidden rounded-lg border bg-background">
                                                <div class="border-b bg-muted/30 px-4 py-2.5">
                                                    <p class="font-semibold">تفاصيل المنتجات</p>
                                                </div>
                                                <table class="w-full text-sm">
                                                    <thead class="text-xs text-muted-foreground">
                                                        <tr>
                                                            <th class="px-3 py-2 text-right font-medium">المنتج</th>
                                                            <th class="px-3 py-2 text-center font-medium">الكمية</th>
                                                            <th class="px-3 py-2 text-left font-medium">السعر</th>
                                                            <th class="px-3 py-2 text-left font-medium">الخصم</th>
                                                            <th class="px-3 py-2 text-left font-medium">الإجمالي</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="(item, idx) in order.items" :key="`${order.id}-${idx}`" class="border-t">
                                                            <td class="px-3 py-2 font-medium">{{ item.name }}</td>
                                                            <td class="px-3 py-2 text-center tabular-nums" dir="ltr">{{ formatInteger(item.quantity) }}</td>
                                                            <td class="px-3 py-2 text-left tabular-nums" dir="ltr">{{ formatCurrency(item.price, order.currency) }}</td>
                                                            <td class="px-3 py-2 text-left tabular-nums text-amber-700" dir="ltr">{{ formatCurrency(item.discount_amount, order.currency) }}</td>
                                                            <td class="px-3 py-2 text-left font-semibold tabular-nums" dir="ltr">{{ formatCurrency(item.total, order.currency) }}</td>
                                                        </tr>
                                                        <tr v-if="order.items.length === 0">
                                                            <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">لا توجد منتجات</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div class="overflow-hidden rounded-lg border bg-background">
                                                <div class="border-b bg-muted/30 px-4 py-2.5">
                                                    <p class="font-semibold">سندات القبض</p>
                                                </div>
                                                <div v-if="order.receipts.length > 0" class="divide-y">
                                                    <div
                                                        v-for="receipt in order.receipts"
                                                        :key="receipt.id"
                                                        class="flex items-center justify-between gap-3 px-4 py-3"
                                                    >
                                                        <div class="min-w-0">
                                                            <p class="font-semibold tabular-nums" dir="ltr">{{ receipt.receipt_number }}</p>
                                                            <p class="mt-0.5 text-xs text-muted-foreground">
                                                                دفعة {{ formatCurrency(receipt.amount, order.currency) }}
                                                                · المتبقي {{ formatCurrency(receipt.remaining_after, order.currency) }}
                                                            </p>
                                                        </div>
                                                        <Button as-child variant="outline" size="sm" class="h-8 shrink-0">
                                                            <a :href="receiptUrl(order, receipt.id)" target="_blank" rel="noopener">فتح</a>
                                                        </Button>
                                                    </div>
                                                </div>
                                                <p v-else class="px-4 py-8 text-center text-sm text-muted-foreground">
                                                    لا توجد سندات قبض.
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="settleOrderId === order.id" class="border-b bg-amber-50/70">
                                    <td colspan="8" class="p-4">
                                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <Wallet class="h-4 w-4 text-amber-700" />
                                                <p class="font-semibold text-amber-900">سداد الطلب {{ order.order_number }}</p>
                                            </div>
                                            <p class="text-xs text-amber-800">
                                                يمكنك سداد جزء من المتبقي أو كامل المبلغ
                                            </p>
                                        </div>
                                        <div class="grid gap-3 lg:grid-cols-3">
                                            <div class="space-y-1.5">
                                                <div class="flex items-center justify-between gap-2">
                                                    <Label for="settle-amount">مبلغ السداد</Label>
                                                    <button
                                                        type="button"
                                                        class="text-xs font-medium text-amber-800 underline-offset-2 hover:underline"
                                                        @click="fillFullRemaining(order)"
                                                    >
                                                        سداد كامل المتبقي
                                                    </button>
                                                </div>
                                                <Input
                                                    id="settle-amount"
                                                    v-model="settleForm.amount"
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    :max="order.remaining_amount"
                                                    class="h-10 tabular-nums"
                                                    dir="ltr"
                                                    placeholder="مثال: 500"
                                                />
                                                <p v-if="settleForm.errors.amount" class="text-xs text-red-600">{{ settleForm.errors.amount }}</p>
                                                <p class="text-xs text-amber-800">
                                                    المتبقي الحالي: {{ formatCurrency(order.remaining_amount, order.currency) }}
                                                </p>
                                            </div>
                                            <div class="space-y-1.5">
                                                <Label for="settle-method">طريقة الدفع</Label>
                                                <select
                                                    id="settle-method"
                                                    v-model="settleForm.payment_method"
                                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                                >
                                                    <option value="cash">نقدي</option>
                                                    <option value="bank_transfer">تحويل بنكي</option>
                                                    <option value="credit_card">بطاقة ائتمان</option>
                                                    <option value="noon">Noon</option>
                                                    <option value="paypal">PayPal</option>
                                                </select>
                                            </div>
                                            <div class="space-y-1.5">
                                                <Label for="settle-notes">ملاحظات</Label>
                                                <Input id="settle-notes" v-model="settleForm.notes" class="h-10" placeholder="اختياري" />
                                            </div>
                                        </div>
                                        <div class="mt-3 flex gap-2">
                                            <Button type="button" class="h-9" :disabled="settleForm.processing" @click="submitSettle(order)">
                                                {{ settleForm.processing ? 'جاري السداد...' : 'تأكيد السداد وإصدار سند قبض' }}
                                            </Button>
                                            <Button type="button" variant="outline" class="h-9" @click="closeSettle">إلغاء</Button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-lg">
                    <Receipt class="h-5 w-5" />
                    عروض الأسعار
                    <Badge variant="secondary" class="tabular-nums">{{ formatInteger(customer.quotations_count) }}</Badge>
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div v-if="customer.quotations.length === 0" class="rounded-xl border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                    لا توجد عروض أسعار مرتبطة بهذا العميل.
                </div>

                <div
                    v-for="quotation in customer.quotations"
                    :key="quotation.id"
                    class="overflow-hidden rounded-xl border bg-background shadow-sm"
                >
                    <div
                        class="flex cursor-pointer flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                        @click="toggleQuotation(quotation.id)"
                    >
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold" dir="ltr">{{ quotation.quotation_number }}</span>
                                <span
                                    class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium ring-1"
                                    :class="getQuotationStatusClass(quotation.status)"
                                >
                                    {{ getQuotationStatusText(quotation.status) }}
                                </span>
                                <ChevronDown
                                    class="h-4 w-4 text-muted-foreground transition-transform"
                                    :class="expandedQuotationId === quotation.id ? 'rotate-180' : ''"
                                />
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ formatDate(quotation.created_at) }}
                                <span v-if="quotation.valid_until"> · صالح حتى {{ formatDate(quotation.valid_until) }}</span>
                                <span v-if="quotation.user"> · بواسطة {{ quotation.user.name }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2" @click.stop>
                            <span class="me-2 text-base font-bold tabular-nums" dir="ltr">
                                {{ formatPrice(quotation.total_amount) }} ر.س
                            </span>
                            <Button as-child variant="outline" size="sm" class="h-9">
                                <Link :href="`/quotations/${quotation.id}`">تفاصيل</Link>
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="expandedQuotationId === quotation.id"
                        class="border-t bg-muted/20 px-4 py-4"
                    >
                        <div class="mb-3 grid gap-2 text-sm sm:grid-cols-3">
                            <div>
                                <span class="text-muted-foreground">المجموع الفرعي: </span>
                                <span class="tabular-nums" dir="ltr">{{ formatPrice(quotation.subtotal) }} ر.س</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">الضريبة: </span>
                                <span class="tabular-nums" dir="ltr">{{ formatPrice(quotation.tax_amount) }} ر.س</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">التأمين: </span>
                                <span class="tabular-nums" dir="ltr">{{ formatPrice(quotation.insurance_amount) }} ر.س</span>
                            </div>
                        </div>
                        <div v-if="quotation.notes" class="mb-3 rounded-lg bg-background p-3 text-sm">
                            {{ quotation.notes }}
                        </div>
                        <div class="overflow-x-auto rounded-lg border bg-background">
                            <table class="w-full min-w-[480px] text-sm">
                                <thead class="bg-muted/40 text-muted-foreground">
                                    <tr>
                                        <th class="px-3 py-2 text-right font-semibold">المنتج</th>
                                        <th class="px-3 py-2 text-right font-semibold">الكمية</th>
                                        <th class="px-3 py-2 text-right font-semibold">السعر</th>
                                        <th class="px-3 py-2 text-right font-semibold">الإجمالي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in quotation.items"
                                        :key="item.id"
                                        class="border-t"
                                    >
                                        <td class="px-3 py-2">
                                            <div class="font-medium">{{ item.product_name }}</div>
                                            <div v-if="item.description" class="text-xs text-muted-foreground">
                                                {{ item.description }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 tabular-nums">{{ formatInteger(item.quantity) }}</td>
                                        <td class="px-3 py-2 tabular-nums" dir="ltr">{{ formatPrice(item.unit_price) }}</td>
                                        <td class="px-3 py-2 font-medium tabular-nums" dir="ltr">{{ formatPrice(item.total_price) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="productsDialogOpen">
        <DialogContent class="max-w-lg sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>منتجات الطلب</DialogTitle>
                <DialogDescription v-if="productsDialogOrder">
                    {{ productsDialogOrder.order_number }}
                    ·
                    {{ formatInteger(productsDialogOrder.items.length) }} منتج
                </DialogDescription>
            </DialogHeader>

            <div v-if="productsDialogOrder" class="max-h-[60vh] space-y-3 overflow-y-auto pe-1">
                <div
                    v-for="(item, idx) in productsDialogOrder.items"
                    :key="`${productsDialogOrder.id}-${item.product_id ?? idx}`"
                    class="flex items-center gap-3 rounded-xl border bg-muted/20 p-3"
                >
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-background">
                        <img
                            v-if="item.image_url"
                            :src="item.image_url"
                            :alt="item.name"
                            class="h-full w-full object-cover"
                        />
                        <Package v-else class="h-6 w-6 text-muted-foreground" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold leading-snug text-slate-900">{{ item.name }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            الكمية:
                            <span class="font-medium tabular-nums text-slate-800" dir="ltr">
                                {{ formatInteger(item.quantity) }}
                            </span>
                        </p>
                    </div>
                </div>

                <p
                    v-if="productsDialogOrder.items.length === 0"
                    class="rounded-xl border border-dashed px-4 py-10 text-center text-sm text-muted-foreground"
                >
                    لا توجد منتجات في هذا الطلب.
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
