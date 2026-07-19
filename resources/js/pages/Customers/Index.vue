<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Building2,
    Check,
    ChevronDown,
    Download,
    Edit,
    Eye,
    Plus,
    Trash2,
    User,
    X,
    XCircle,
} from 'lucide-vue-next';
import { formatDate, formatDateTime, formatInteger, formatPrice } from '@/lib/formatNumber';

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
    valid_until: string | null;
    notes: string | null;
    subtotal: number;
    tax_amount: number;
    total_amount: number;
    status: string;
    created_at: string;
    user: { id: number; name: string } | null;
    items: QuotationItem[];
}

interface Customer {
    key: string;
    id: number;
    type: 'individual' | 'company';
    name: string;
    contact_name: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    tax_number: string | null;
    notes: string | null;
    created_at: string | null;
    quotations_count: number;
    quotations: Quotation[];
}

interface Props {
    customers: Customer[];
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);
const formOpen = ref(false);
const expandedKey = ref<string | null>(null);
const expandedQuotationId = ref<number | null>(null);
const typeFilter = ref<'all' | 'individual' | 'company'>('all');

const form = useForm({
    company_name: '',
    contact_name: '',
    phone: '',
    email: '',
    address: '',
    tax_number: '',
    notes: '',
});

const filteredCustomers = computed(() => {
    if (typeFilter.value === 'all') {
        return props.customers;
    }

    return props.customers.filter((customer) => customer.type === typeFilter.value);
});

const stats = computed(() => ({
    all: props.customers.length,
    individual: props.customers.filter((c) => c.type === 'individual').length,
    company: props.customers.filter((c) => c.type === 'company').length,
}));

function openForm() {
    form.reset();
    form.clearErrors();
    formOpen.value = true;
}

function closeForm() {
    formOpen.value = false;
    form.reset();
    form.clearErrors();
}

function submit() {
    form.post(route('company-clients.store'), {
        preserveScroll: true,
        onSuccess: () => closeForm(),
    });
}

function destroyClient(customer: Customer) {
    if (customer.type !== 'company') {
        return;
    }

    if (!confirm(`حذف شركة «${customer.name}»؟`)) {
        return;
    }

    router.delete(route('company-clients.destroy', customer.id), {
        preserveScroll: true,
    });
}

function toggleCustomer(customer: Customer) {
    if (customer.type !== 'company') {
        return;
    }

    if (expandedKey.value === customer.key) {
        expandedKey.value = null;
        expandedQuotationId.value = null;
        return;
    }

    expandedKey.value = customer.key;
    expandedQuotationId.value = null;
}

function toggleQuotation(quotationId: number) {
    expandedQuotationId.value = expandedQuotationId.value === quotationId ? null : quotationId;
}

function updateStatus(quotationId: number, status: 'accepted' | 'rejected') {
    const label = status === 'accepted' ? 'قبول' : 'رفض';
    if (!confirm(`تأكيد ${label} عرض السعر؟`)) {
        return;
    }

    router.patch(
        route('quotations.update-status', quotationId),
        { status },
        { preserveScroll: true },
    );
}

function canDecide(status: string) {
    return status === 'draft' || status === 'sent';
}

function getStatusText(status: string) {
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

function getStatusClass(status: string) {
    switch (status) {
        case 'accepted':
            return 'border-green-200 bg-green-50 text-green-800 dark:border-green-900/50 dark:bg-green-950/40 dark:text-green-300';
        case 'rejected':
        case 'expired':
            return 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300';
        case 'sent':
            return 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-300';
        default:
            return '';
    }
}

function quotationPdfUrl(id: number): string {
    return `/quotations/${id}/pdf?v=${Date.now()}`;
}
</script>

<template>
    <Head title="العملاء" />

    <div class="flex min-w-0 flex-1 flex-col gap-4 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6">
        <p
            v-if="successMessage"
            class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300"
        >
            {{ successMessage }}
        </p>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight sm:text-3xl">العملاء</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    عملاء الموقع (فردي) وعملاء الشركات في قائمة واحدة — الإضافة الجديدة لعملاء الشركات فقط
                </p>
            </div>
            <Button
                v-if="!formOpen"
                class="h-10 shrink-0 gap-2 sm:h-11"
                @click="openForm"
            >
                <Plus class="h-4 w-4" />
                إضافة عميل شركة
            </Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-full px-4 py-2 text-sm font-semibold transition"
                :class="typeFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'"
                @click="typeFilter = 'all'"
            >
                الكل ({{ formatInteger(stats.all) }})
            </button>
            <button
                type="button"
                class="rounded-full px-4 py-2 text-sm font-semibold transition"
                :class="typeFilter === 'individual' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'"
                @click="typeFilter = 'individual'"
            >
                فردي ({{ formatInteger(stats.individual) }})
            </button>
            <button
                type="button"
                class="rounded-full px-4 py-2 text-sm font-semibold transition"
                :class="typeFilter === 'company' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'"
                @click="typeFilter = 'company'"
            >
                شركة ({{ formatInteger(stats.company) }})
            </button>
        </div>

        <Card v-if="formOpen" class="shadow-sm">
            <CardHeader class="flex flex-row items-center justify-between gap-3 p-4 sm:p-6">
                <CardTitle class="flex items-center gap-2 text-lg">
                    <Building2 class="h-5 w-5" />
                    إضافة عميل شركة
                </CardTitle>
                <Button variant="ghost" size="icon" class="h-9 w-9" @click="closeForm">
                    <X class="h-4 w-4" />
                </Button>
            </CardHeader>
            <CardContent class="p-4 pt-0 sm:p-6 sm:pt-0">
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="company_name">اسم الشركة *</Label>
                        <Input id="company_name" v-model="form.company_name" class="h-11" required />
                        <p v-if="form.errors.company_name" class="text-sm text-destructive">{{ form.errors.company_name }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="contact_name">اسم المسؤول</Label>
                        <Input id="contact_name" v-model="form.contact_name" class="h-11" />
                    </div>
                    <div class="space-y-2">
                        <Label for="phone">الجوال</Label>
                        <Input id="phone" v-model="form.phone" class="h-11" dir="ltr" />
                    </div>
                    <div class="space-y-2">
                        <Label for="email">البريد الإلكتروني</Label>
                        <Input id="email" v-model="form.email" type="email" class="h-11" dir="ltr" />
                    </div>
                    <div class="space-y-2">
                        <Label for="tax_number">الرقم الضريبي</Label>
                        <Input id="tax_number" v-model="form.tax_number" class="h-11" dir="ltr" />
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="address">العنوان</Label>
                        <Textarea id="address" v-model="form.address" rows="2" class="resize-none" />
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="notes">ملاحظات</Label>
                        <Textarea id="notes" v-model="form.notes" rows="2" class="resize-none" />
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:col-span-2 sm:flex-row sm:justify-end">
                        <Button type="button" variant="outline" class="h-10" @click="closeForm">إلغاء</Button>
                        <Button type="submit" class="h-10" :disabled="form.processing">
                            {{ form.processing ? 'جاري الحفظ...' : 'حفظ' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card class="shadow-sm">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="border-b bg-muted/40 text-sm">
                                <th class="w-10 px-2 py-3" />
                                <th class="px-4 py-3 text-right font-semibold">النوع</th>
                                <th class="px-4 py-3 text-right font-semibold">الاسم</th>
                                <th class="px-4 py-3 text-right font-semibold">المسؤول / التواصل</th>
                                <th class="px-4 py-3 text-right font-semibold">الجوال</th>
                                <th class="px-4 py-3 text-right font-semibold">البريد</th>
                                <th class="px-4 py-3 text-right font-semibold">عروض الأسعار</th>
                                <th class="px-4 py-3 text-right font-semibold">التاريخ</th>
                                <th class="px-4 py-3 text-center font-semibold">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredCustomers.length === 0">
                                <td colspan="9" class="px-4 py-12 text-center text-sm text-muted-foreground">
                                    لا يوجد عملاء في هذا القسم.
                                </td>
                            </tr>

                            <template v-for="customer in filteredCustomers" :key="customer.key">
                                <tr
                                    class="border-b last:border-0"
                                    :class="[
                                        customer.type === 'company' ? 'cursor-pointer hover:bg-muted/30' : '',
                                        expandedKey === customer.key ? 'bg-muted/20' : '',
                                    ]"
                                    @click="toggleCustomer(customer)"
                                >
                                    <td class="px-2 py-3 text-center">
                                        <ChevronDown
                                            v-if="customer.type === 'company'"
                                            class="mx-auto h-4 w-4 text-muted-foreground transition-transform"
                                            :class="expandedKey === customer.key ? 'rotate-180' : ''"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                                            :class="customer.type === 'company'
                                                ? 'bg-violet-50 text-violet-700 ring-violet-200'
                                                : 'bg-sky-50 text-sky-700 ring-sky-200'"
                                        >
                                            <Building2 v-if="customer.type === 'company'" class="h-3 w-3" />
                                            <User v-else class="h-3 w-3" />
                                            {{ customer.type === 'company' ? 'شركة' : 'فردي' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-semibold">{{ customer.name }}</td>
                                    <td class="px-4 py-3">
                                        {{ customer.type === 'company' ? (customer.contact_name || '—') : 'عميل الموقع' }}
                                    </td>
                                    <td class="px-4 py-3" dir="ltr">{{ customer.phone || '—' }}</td>
                                    <td class="px-4 py-3" dir="ltr">{{ customer.email || '—' }}</td>
                                    <td class="px-4 py-3">
                                        <Badge v-if="customer.type === 'company'" variant="secondary" class="tabular-nums">
                                            {{ formatInteger(customer.quotations_count) }} عرض
                                        </Badge>
                                        <span v-else class="text-sm text-muted-foreground">—</span>
                                    </td>
                                    <td class="px-4 py-3 tabular-nums text-muted-foreground" dir="ltr">
                                        {{ customer.created_at ? formatDateTime(customer.created_at) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center" @click.stop>
                                        <Button
                                            v-if="customer.type === 'company'"
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="h-9 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            @click="destroyClient(customer)"
                                        >
                                            <Trash2 class="ms-1.5 h-4 w-4" />
                                            حذف
                                        </Button>
                                        <span v-else class="text-xs text-muted-foreground">—</span>
                                    </td>
                                </tr>

                                <tr v-if="customer.type === 'company' && expandedKey === customer.key">
                                    <td colspan="9" class="bg-muted/10 px-3 py-4 sm:px-6">
                                        <div v-if="customer.quotations.length === 0" class="rounded-xl border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                                            لا توجد عروض أسعار مرتبطة بهذه الشركة بعد.
                                        </div>

                                        <div v-else class="space-y-3">
                                            <p class="text-sm font-medium text-muted-foreground">
                                                عروض الأسعار المرسلة لـ «{{ customer.name }}»
                                                ({{ formatInteger(customer.quotations_count) }})
                                            </p>

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
                                                                class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                                                :class="getStatusClass(quotation.status)"
                                                            >
                                                                {{ getStatusText(quotation.status) }}
                                                            </span>
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
                                                        <template v-if="canDecide(quotation.status)">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                class="h-9 gap-1.5 bg-green-600 text-white hover:bg-green-700"
                                                                @click="updateStatus(quotation.id, 'accepted')"
                                                            >
                                                                <Check class="h-4 w-4" />
                                                                قبول
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="destructive"
                                                                class="h-9 gap-1.5"
                                                                @click="updateStatus(quotation.id, 'rejected')"
                                                            >
                                                                <XCircle class="h-4 w-4" />
                                                                رفض
                                                            </Button>
                                                        </template>
                                                        <Button as-child type="button" size="sm" variant="outline" class="h-9 gap-1.5">
                                                            <Link :href="route('quotations.edit', quotation.id)">
                                                                <Edit class="h-4 w-4" />
                                                                تعديل
                                                            </Link>
                                                        </Button>
                                                        <Button as-child type="button" size="sm" variant="outline" class="h-9 gap-1.5">
                                                            <a
                                                                :href="quotationPdfUrl(quotation.id)"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                <Download class="h-4 w-4" />
                                                                PDF
                                                            </a>
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            class="h-9 gap-1.5"
                                                            @click="toggleQuotation(quotation.id)"
                                                        >
                                                            <Eye class="h-4 w-4" />
                                                            {{ expandedQuotationId === quotation.id ? 'إخفاء' : 'تفاصيل' }}
                                                        </Button>
                                                    </div>
                                                </div>

                                                <div
                                                    v-if="expandedQuotationId === quotation.id"
                                                    class="border-t bg-muted/20 px-4 py-4"
                                                >
                                                    <div class="mb-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                                        <div>
                                                            <p class="text-muted-foreground">العميل</p>
                                                            <p class="font-medium">{{ quotation.customer_name || '—' }}</p>
                                                        </div>
                                                        <div>
                                                            <p class="text-muted-foreground">الجوال</p>
                                                            <p class="font-medium" dir="ltr">{{ quotation.customer_phone || '—' }}</p>
                                                        </div>
                                                        <div>
                                                            <p class="text-muted-foreground">البريد</p>
                                                            <p class="font-medium" dir="ltr">{{ quotation.customer_email || '—' }}</p>
                                                        </div>
                                                        <div>
                                                            <p class="text-muted-foreground">العنوان</p>
                                                            <p class="font-medium">{{ quotation.customer_address || '—' }}</p>
                                                        </div>
                                                    </div>

                                                    <div class="overflow-x-auto rounded-lg border bg-background">
                                                        <table class="w-full text-sm">
                                                            <thead>
                                                                <tr class="border-b bg-muted/40">
                                                                    <th class="px-3 py-2 text-right font-medium">المنتج</th>
                                                                    <th class="px-3 py-2 text-right font-medium">الكمية</th>
                                                                    <th class="px-3 py-2 text-right font-medium">السعر</th>
                                                                    <th class="px-3 py-2 text-right font-medium">الإجمالي</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr
                                                                    v-for="item in quotation.items"
                                                                    :key="item.id"
                                                                    class="border-b last:border-0"
                                                                >
                                                                    <td class="px-3 py-2">
                                                                        <div class="font-medium">{{ item.product_name }}</div>
                                                                        <div v-if="item.description" class="text-xs text-muted-foreground">
                                                                            {{ item.description }}
                                                                        </div>
                                                                    </td>
                                                                    <td class="px-3 py-2 tabular-nums" dir="ltr">{{ formatInteger(item.quantity) }}</td>
                                                                    <td class="px-3 py-2 tabular-nums" dir="ltr">{{ formatPrice(item.unit_price) }}</td>
                                                                    <td class="px-3 py-2 tabular-nums font-medium" dir="ltr">{{ formatPrice(item.total_price) }}</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>

                                                    <div class="mt-3 flex flex-col items-end gap-1 text-sm">
                                                        <div class="flex gap-6">
                                                            <span class="text-muted-foreground">المجموع</span>
                                                            <span class="tabular-nums" dir="ltr">{{ formatPrice(quotation.subtotal) }} ر.س</span>
                                                        </div>
                                                        <div class="flex gap-6">
                                                            <span class="text-muted-foreground">الضريبة</span>
                                                            <span class="tabular-nums" dir="ltr">{{ formatPrice(quotation.tax_amount) }} ر.س</span>
                                                        </div>
                                                        <div class="flex gap-6 text-base font-bold">
                                                            <span>الإجمالي</span>
                                                            <span class="tabular-nums" dir="ltr">{{ formatPrice(quotation.total_amount) }} ر.س</span>
                                                        </div>
                                                    </div>

                                                    <p v-if="quotation.notes" class="mt-3 rounded-lg border bg-background px-3 py-2 text-sm text-muted-foreground">
                                                        <span class="font-medium text-foreground">ملاحظات:</span>
                                                        {{ quotation.notes }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
