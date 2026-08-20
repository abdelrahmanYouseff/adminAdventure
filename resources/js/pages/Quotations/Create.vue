<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    ArrowRight,
    Building2,
    Calendar,
    FileSpreadsheet,
    Layers3,
    Link2,
    Mail,
    MapPin,
    Package,
    Phone,
    Plus,
    Receipt,
    ShoppingCart,
    Trash2,
    User,
} from 'lucide-vue-next';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import ProductSearchCombobox from '@/components/ProductSearchCombobox.vue';
import { formatCurrency, formatInteger } from '@/lib/formatNumber';
import { ref, computed, watch } from 'vue';
import type { BreadcrumbItem } from '@/types';

interface Product {
    id: number;
    product_name: string;
    description: string;
    price: number;
    insurance_amount?: number | string | null;
    category_id?: number | null;
    brand_id?: number | null;
}

interface Category {
    id: number;
    category_name: string;
    brand_id?: number | null;
}

interface Brand {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    logo_url?: string | null;
    products_count?: number;
}

interface QuotationItem {
    product_id: number | null;
    product_name: string;
    description: string;
    statement: string;
    quantity: number;
    unit_price: number;
    discount_amount: number;
    total_price: number;
    insurance_amount: number;
}

interface QuotationTerm {
    ar: string;
    en: string;
}

interface Props {
    products: Product[];
    categories: Category[];
    brands: Brand[];
    selectedBrand: Brand | null;
    defaultTerms?: QuotationTerm[];
}

const props = withDefaults(defineProps<Props>(), {
    categories: () => [],
    brands: () => [],
    selectedBrand: null,
    defaultTerms: () => [],
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'عروض الأسعار', href: route('quotations.index') },
    { title: 'إنشاء عرض جديد', href: route('quotations.create') },
];

const cloneTerms = (terms: QuotationTerm[] = []): QuotationTerm[] =>
    terms.map((term) => ({ ar: term.ar, en: term.en }));

const form = useForm({
    brand_id: (props.selectedBrand?.id ?? null) as number | null,
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    customer_address: '',
    company_tax_number: '',
    valid_until: (() => {
        const date = new Date();
        date.setDate(date.getDate() + 7);
        return date.toISOString().slice(0, 10);
    })(),
    activity_at: '',
    installation_at: '',
    dismantling_at: '',
    insurance_amount: 0 as number,
    amount_paid: 0 as number,
    show_online_payment: false,
    notes: '',
    terms: cloneTerms(props.defaultTerms),
    items: [] as QuotationItem[],
});

function addTerm() {
    form.terms.push({ ar: '', en: '' });
}

function removeTerm(index: number) {
    form.terms.splice(index, 1);
}

function resetTermsToDefault() {
    form.terms = cloneTerms(props.defaultTerms);
}

const selectedCategoryId = ref<number | ''>('');
const selectedProductId = ref<number | null>(null);
const selectedQuantity = ref(1);
const selectedUnitPrice = ref(0);
const addMode = ref<'catalog' | 'custom'>('catalog');
const customName = ref('');
const customDescription = ref('');
const customStatement = ref('');
const customPrice = ref(0);
const customQuantity = ref(1);
const insuranceManual = ref(false);
const customerLookupStatus = ref<'idle' | 'loading' | 'found' | 'not_found'>('idle');
const customerLookupMessage = ref('');
const customerType = ref<'individual' | 'company'>('individual');
const customerFirstName = ref('');
const customerSecondName = ref('');
const nameValidationError = ref('');
let phoneLookupTimer: ReturnType<typeof setTimeout> | null = null;
let phoneLookupRequestId = 0;

function composeCustomerName(): string {
    return [customerFirstName.value, customerSecondName.value]
        .map((part) => part.trim())
        .filter(Boolean)
        .join(' ');
}

const filteredProducts = computed(() => {
    if (selectedCategoryId.value === '' || selectedCategoryId.value == null) {
        return props.products;
    }

    return props.products.filter((product) => product.category_id === Number(selectedCategoryId.value));
});

const subtotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (parseFloat(String(item.total_price)) || 0), 0);
});

const discountTotal = computed(() => {
    return form.items.reduce((sum, item) => {
        return sum + (Number(item.discount_amount || 0) * Number(item.quantity || 0));
    }, 0);
});

const grossSubtotal = computed(() => subtotal.value + discountTotal.value);

const suggestedInsurance = computed(() => {
    return form.items.reduce((sum, item) => {
        return sum + (Number(item.insurance_amount || 0) * Number(item.quantity || 0));
    }, 0);
});

const taxAmount = computed(() => subtotal.value * 0.15);
const totalAmount = computed(() => subtotal.value + taxAmount.value);
const amountPaid = computed(() => Math.max(0, Number(form.amount_paid || 0)));
const remainingAmount = computed(() => Math.max(0, totalAmount.value - amountPaid.value));
const itemsCount = computed(() => form.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0));

const selectedProduct = computed(() => {
    if (selectedProductId.value == null) return null;
    return props.products.find(p => p.id === selectedProductId.value) ?? null;
});

function syncInsuranceFromItems() {
    if (!insuranceManual.value) {
        form.insurance_amount = Math.round(suggestedInsurance.value * 100) / 100;
    }
}

watch(suggestedInsurance, () => {
    syncInsuranceFromItems();
});

watch(selectedCategoryId, () => {
    if (selectedProductId.value == null) return;
    const stillVisible = filteredProducts.value.some((p) => p.id === selectedProductId.value);
    if (!stillVisible) {
        selectedProductId.value = null;
        selectedUnitPrice.value = 0;
    }
});

const addItem = () => {
    if (selectedProductId.value == null || !selectedProduct.value) return;

    const product = selectedProduct.value;
    const quantityToAdd = Math.max(1, Number(selectedQuantity.value) || 1);
    const unitPrice = Number(selectedUnitPrice.value) || 0;
    const existingIndex = form.items.findIndex((item) => item.product_id === product.id);

    if (existingIndex >= 0) {
        const existing = form.items[existingIndex];
        existing.quantity = Number(existing.quantity) + quantityToAdd;
        // احتفظ بسعر الوحدة الحالي للبند إن وُجد، وحدّث الإجمالي
        existing.total_price = existing.quantity * Math.max(
            0,
            Number(existing.unit_price) - Number(existing.discount_amount || 0),
        );
    } else {
        form.items.push({
            product_id: product.id,
            product_name: product.product_name,
            description: product.description,
            statement: '',
            quantity: quantityToAdd,
            unit_price: unitPrice,
            discount_amount: 0,
            total_price: quantityToAdd * unitPrice,
            insurance_amount: Number(product.insurance_amount || 0),
        });
    }

    selectedProductId.value = null;
    selectedQuantity.value = 1;
    selectedUnitPrice.value = 0;
    syncInsuranceFromItems();
};

const addCustomItem = () => {
    const name = customName.value.trim();
    if (!name) return;

    const quantity = Math.max(1, Number(customQuantity.value) || 1);
    const unitPrice = Math.max(0, Number(customPrice.value) || 0);

    form.items.push({
        product_id: null,
        product_name: name,
        description: customDescription.value.trim(),
        statement: customStatement.value.trim(),
        quantity,
        unit_price: unitPrice,
        discount_amount: 0,
        total_price: quantity * unitPrice,
        insurance_amount: 0,
    });

    customName.value = '';
    customDescription.value = '';
    customStatement.value = '';
    customPrice.value = 0;
    customQuantity.value = 1;
    syncInsuranceFromItems();
};

const removeItem = (index: number) => {
    form.items.splice(index, 1);
    syncInsuranceFromItems();
};

const updateItemPrice = (index: number) => {
    const item = form.items[index];
    const unitPrice = Math.max(0, Number(item.unit_price) || 0);
    const discount = Math.min(unitPrice, Math.max(0, Number(item.discount_amount) || 0));

    item.unit_price = unitPrice;
    item.discount_amount = discount;
    item.total_price = Number(item.quantity || 0) * (unitPrice - discount);
    syncInsuranceFromItems();
};

function onInsuranceInput() {
    insuranceManual.value = true;
}

function resetInsuranceToSuggested() {
    insuranceManual.value = false;
    syncInsuranceFromItems();
}

const submit = () => {
    nameValidationError.value = '';

    if (customerType.value !== 'company') {
        form.company_tax_number = '';

        const first = customerFirstName.value.trim();
        const second = customerSecondName.value.trim();

        if (!first) {
            nameValidationError.value = 'الاسم الأول مطلوب.';
            return;
        }

        if (!second) {
            nameValidationError.value = 'الاسم الثاني مطلوب.';
            return;
        }

        form.customer_name = composeCustomerName();
    } else if (!form.customer_name.trim()) {
        nameValidationError.value = 'اسم الشركة مطلوب.';
        return;
    }

    form.post(route('quotations.store'), {
        preserveScroll: true,
        onError: () => {
            requestAnimationFrame(() => {
                document.getElementById('quotation-form-errors')?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });
            });
        },
    });
};

const fieldLabels: Record<string, string> = {
    brand_id: 'البراند',
    customer_name: 'اسم العميل',
    customer_email: 'البريد الإلكتروني',
    customer_phone: 'رقم الجوال',
    customer_address: 'العنوان',
    company_tax_number: 'الرقم الضريبي',
    valid_until: 'تاريخ الصلاحية',
    activity_at: 'تاريخ الفعالية',
    installation_at: 'تاريخ التركيب',
    dismantling_at: 'تاريخ الفك',
    insurance_amount: 'مبلغ التأمين',
    amount_paid: 'المبلغ المدفوع',
    notes: 'الملاحظات',
    items: 'بنود العرض',
    error: 'خطأ عام',
};

const itemFieldLabels: Record<string, string> = {
    product_id: 'المنتج',
    product_name: 'اسم الصنف',
    description: 'الوصف',
    statement: 'البيان',
    quantity: 'الكمية',
    unit_price: 'سعر الوحدة',
    discount_amount: 'الخصم',
};

function errorFieldLabel(key: string): string {
    const itemMatch = key.match(/^items\.(\d+)\.(.+)$/);
    if (itemMatch) {
        const index = Number(itemMatch[1]) + 1;
        const field = itemMatch[2];
        return `البند ${index} — ${itemFieldLabels[field] || field}`;
    }
    if (key.startsWith('items.')) {
        return 'بنود العرض';
    }
    return fieldLabels[key] || key;
}

const formErrorEntries = computed(() =>
    Object.entries(form.errors).map(([key, message]) => ({
        key,
        label: errorFieldLabel(key),
        message: Array.isArray(message) ? message.join('، ') : String(message),
    })),
);

function setCustomerType(type: 'individual' | 'company') {
    customerType.value = type;
    if (type !== 'company') {
        form.company_tax_number = '';
    }
    nameValidationError.value = '';
}

function digitsOnly(value: string): string {
    return value.replace(/\D+/g, '');
}

function isLookupReady(phone: string): boolean {
    const digits = digitsOnly(phone);
    // 5XXXXXXXX (9) or 05XXXXXXXX (10)
    return digits.length >= 9;
}

async function lookupCustomerByPhone(phone: string) {
    const requestId = ++phoneLookupRequestId;
    customerLookupStatus.value = 'loading';
    customerLookupMessage.value = 'جاري البحث عن العميل...';

    try {
        const response = await fetch(
            `${route('quotations.lookup-customer')}?phone=${encodeURIComponent(phone)}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        );

        if (requestId !== phoneLookupRequestId) {
            return;
        }

        const data = await response.json();

        if (!data?.success || !data.customer) {
            customerLookupStatus.value = 'not_found';
            customerLookupMessage.value = data?.message || 'لا يوجد عميل بهذا الرقم.';
            setCustomerType('individual');
            return;
        }

        const customer = data.customer;
        const resolvedType =
            customer.customer_type === 'company' || customer.source === 'company_client'
                ? 'company'
                : 'individual';
        setCustomerType(resolvedType);

        if (customer.customer_name) {
            if (resolvedType === 'company') {
                form.customer_name = customer.customer_name;
                customerFirstName.value = '';
                customerSecondName.value = '';
            } else {
                customerFirstName.value = customer.customer_name;
                customerSecondName.value = '';
                form.customer_name = customer.customer_name;
            }
        if (customer.customer_email) {
            form.customer_email = customer.customer_email;
        }
        if (customer.customer_phone) {
            form.customer_phone = customer.customer_phone;
        }
        if (customer.customer_address) {
            form.customer_address = customer.customer_address;
        }

        if (resolvedType === 'company') {
            form.company_tax_number = customer.company_tax_number || '';
        }

        customerLookupStatus.value = 'found';
        customerLookupMessage.value = data.message || 'تم تعبئة بيانات العميل تلقائياً.';
    } catch {
        if (requestId !== phoneLookupRequestId) {
            return;
        }
        customerLookupStatus.value = 'not_found';
        customerLookupMessage.value = 'تعذر البحث عن العميل الآن.';
    }
}

watch(selectedProductId, (newValue) => {
    if (newValue != null) {
        const product = props.products.find(p => p.id === newValue);
        if (product) {
            selectedUnitPrice.value = product.price;
        }
    }
});

watch(
    () => form.customer_phone,
    (phone) => {
        if (phoneLookupTimer) {
            clearTimeout(phoneLookupTimer);
            phoneLookupTimer = null;
        }

        const trimmed = (phone || '').trim();
        if (!isLookupReady(trimmed)) {
            customerLookupStatus.value = 'idle';
            customerLookupMessage.value = '';
            return;
        }

        phoneLookupTimer = setTimeout(() => {
            lookupCustomerByPhone(trimmed);
        }, 450);
    },
);
</script>

<template>
    <Head title="إنشاء عرض سعر" />

    <AppSidebarLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <FileSpreadsheet class="h-6 w-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">إنشاء عرض سعر</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            <template v-if="!selectedBrand">اختر البراند أولاً ثم أكمل بيانات عرض السعر</template>
                            <template v-else>عرض سعر لـ {{ selectedBrand.name }} — أدخل بيانات العميل وأضف المنتجات</template>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 self-start">
                    <Button v-if="selectedBrand" as-child variant="outline" class="shrink-0 gap-2">
                        <Link :href="route('quotations.create')">
                            <Building2 class="h-4 w-4" />
                            تغيير البراند
                        </Link>
                    </Button>
                    <Button as-child variant="outline" class="shrink-0 gap-2">
                        <Link :href="route('quotations.index')">
                            <ArrowRight class="h-4 w-4" />
                            العودة للقائمة
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Brand selection first step -->
            <div v-if="!selectedBrand" class="space-y-4">
                <div v-if="brands.length === 0" class="rounded-2xl border border-dashed p-12 text-center">
                    <Building2 class="mx-auto h-12 w-12 text-muted-foreground/50" />
                    <p class="mt-3 font-semibold">لا توجد براندات نشطة</p>
                    <Button class="mt-4" as-child>
                        <Link :href="route('brands.index')">إدارة البراندات</Link>
                    </Button>
                </div>

                <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="brand in brands"
                        :key="brand.id"
                        :href="route('quotations.create', { brand: brand.id })"
                        class="group overflow-hidden rounded-2xl border bg-card shadow-sm transition hover:-translate-y-1 hover:border-primary/30 hover:shadow-lg"
                    >
                        <div class="relative flex h-40 items-center justify-center overflow-hidden bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-900">
                            <img
                                v-if="brand.logo_url"
                                :src="brand.logo_url"
                                :alt="brand.name"
                                class="h-full w-full object-contain p-6 transition duration-300 group-hover:scale-105"
                            />
                            <Building2 v-else class="h-14 w-14 text-muted-foreground/30" />
                        </div>
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h2 class="truncate text-lg font-bold group-hover:text-primary">{{ brand.name }}</h2>
                                    <p v-if="brand.description" class="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                        {{ brand.description }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1 rounded-lg bg-muted px-2.5 py-1.5 text-sm font-semibold">
                                    <Layers3 class="h-4 w-4" />
                                    {{ formatInteger(brand.products_count || 0) }}
                                </div>
                            </div>
                            <div class="mt-4 border-t pt-3 text-sm font-medium text-primary">إنشاء عرض سعر لهذا البراند</div>
                        </div>
                    </Link>
                </div>
            </div>

            <template v-else>
            <!-- Errors -->
            <div
                v-if="formErrorEntries.length > 0"
                id="quotation-form-errors"
                class="rounded-xl border border-red-300 bg-red-50 px-4 py-4 text-sm text-red-800 shadow-sm dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200"
                role="alert"
            >
                <p class="mb-2 text-base font-bold">فشل حفظ عرض السعر — تفاصيل الأخطاء:</p>
                <ul class="space-y-2">
                    <li
                        v-for="entry in formErrorEntries"
                        :key="entry.key"
                        class="rounded-lg border border-red-200/80 bg-white/70 px-3 py-2 dark:border-red-900/40 dark:bg-red-950/30"
                    >
                        <span class="font-semibold">{{ entry.label }}:</span>
                        <span class="ms-1">{{ entry.message }}</span>
                    </li>
                </ul>
            </div>

            <form @submit.prevent="submit" class="grid gap-6 lg:grid-cols-3 lg:items-start">
                <!-- Main column -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Customer -->
                    <section class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm">
                        <div class="flex items-center gap-3 border-b border-border/60 bg-muted/30 px-5 py-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                <User class="h-4 w-4" />
                            </div>
                            <div>
                                <h2 class="font-semibold text-foreground">بيانات العميل</h2>
                                <p class="text-xs text-muted-foreground">معلومات التواصل وصلاحية العرض</p>
                            </div>
                        </div>

                        <div class="space-y-5 p-5 sm:p-6">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="customer_phone" class="flex items-center gap-1.5">
                                        <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                                        رقم الجوال
                                        <span class="text-red-500">*</span>
                                    </Label>
                                    <div class="flex h-11 overflow-hidden rounded-xl border border-input bg-background" dir="ltr">
                                        <span class="flex shrink-0 items-center border-e border-input bg-muted/50 px-3 text-sm font-medium text-muted-foreground">
                                            +966
                                        </span>
                                        <Input
                                            id="customer_phone"
                                            v-model="form.customer_phone"
                                            type="tel"
                                            inputmode="numeric"
                                            placeholder="5XXXXXXXX"
                                            class="h-full border-0 shadow-none focus-visible:ring-0"
                                            required
                                        />
                                    </div>
                                    <p
                                        v-if="customerLookupStatus === 'loading'"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ customerLookupMessage }}
                                    </p>
                                    <p
                                        v-else-if="customerLookupStatus === 'found'"
                                        class="text-xs font-medium text-emerald-600 dark:text-emerald-400"
                                    >
                                        {{ customerLookupMessage }}
                                    </p>
                                    <p
                                        v-else-if="customerLookupStatus === 'not_found'"
                                        class="text-xs text-amber-600 dark:text-amber-400"
                                    >
                                        {{ customerLookupMessage }} يمكنك إدخال البيانات يدوياً.
                                    </p>
                                    <p v-else class="text-xs text-muted-foreground">
                                        اكتب رقم الجوال وسيتم تعبئة البيانات تلقائياً من عملاء الشركات أو العملاء إن وُجد.
                                    </p>
                                </div>

                                <div class="space-y-2 sm:col-span-2">
                                    <Label class="flex items-center gap-1.5">
                                        <Building2 class="h-3.5 w-3.5 text-muted-foreground" />
                                        نوع العميل
                                    </Label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button
                                            type="button"
                                            class="h-11 rounded-xl border text-sm font-medium transition"
                                            :class="
                                                customerType === 'individual'
                                                    ? 'border-primary bg-primary/10 text-primary'
                                                    : 'border-input bg-background text-muted-foreground hover:bg-muted/40'
                                            "
                                            @click="setCustomerType('individual')"
                                        >
                                            فرد
                                        </button>
                                        <button
                                            type="button"
                                            class="h-11 rounded-xl border text-sm font-medium transition"
                                            :class="
                                                customerType === 'company'
                                                    ? 'border-primary bg-primary/10 text-primary'
                                                    : 'border-input bg-background text-muted-foreground hover:bg-muted/40'
                                            "
                                            @click="setCustomerType('company')"
                                        >
                                            شركة
                                        </button>
                                    </div>
                                </div>

                                <template v-if="customerType === 'individual'">
                                    <div class="space-y-2">
                                        <Label for="customer_first_name" class="flex items-center gap-1.5">
                                            <User class="h-3.5 w-3.5 text-muted-foreground" />
                                            الاسم الأول
                                            <span class="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="customer_first_name"
                                            v-model="customerFirstName"
                                            placeholder="مثال: أحمد"
                                            class="h-11 rounded-xl"
                                            required
                                            @input="nameValidationError = ''"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="customer_second_name" class="flex items-center gap-1.5">
                                            الاسم الثاني
                                            <span class="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="customer_second_name"
                                            v-model="customerSecondName"
                                            placeholder="مثال: محمد"
                                            class="h-11 rounded-xl"
                                            required
                                            @input="nameValidationError = ''"
                                        />
                                    </div>
                                </template>

                                <div v-else class="space-y-2 sm:col-span-2">
                                    <Label for="customer_name" class="flex items-center gap-1.5">
                                        <Building2 class="h-3.5 w-3.5 text-muted-foreground" />
                                        اسم الشركة
                                        <span class="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="customer_name"
                                        v-model="form.customer_name"
                                        placeholder="مثال: شركة المغامرة"
                                        class="h-11 rounded-xl"
                                        required
                                        @input="nameValidationError = ''"
                                    />
                                </div>

                                <p
                                    v-if="nameValidationError || form.errors.customer_name"
                                    class="text-xs text-red-600 sm:col-span-2"
                                >
                                    {{ nameValidationError || form.errors.customer_name }}
                                </p>

                                <div class="space-y-2">
                                    <Label for="customer_email" class="flex items-center gap-1.5">
                                        <Mail class="h-3.5 w-3.5 text-muted-foreground" />
                                        البريد الإلكتروني
                                    </Label>
                                    <Input
                                        id="customer_email"
                                        v-model="form.customer_email"
                                        type="email"
                                        placeholder="example@email.com"
                                        class="h-11 rounded-xl"
                                        dir="ltr"
                                    />
                                </div>

                                <div v-if="customerType === 'company'" class="space-y-2">
                                    <Label for="company_tax_number" class="flex items-center gap-1.5">
                                        <Receipt class="h-3.5 w-3.5 text-muted-foreground" />
                                        الرقم الضريبي للشركة
                                    </Label>
                                    <Input
                                        id="company_tax_number"
                                        v-model="form.company_tax_number"
                                        type="text"
                                        placeholder="يُجلب تلقائياً إن كانت الشركة مسجّلة"
                                        class="h-11 rounded-xl text-right"
                                        dir="rtl"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        عند إدخال رقم هاتف شركة مسجّلة يُعبَّأ تلقائياً، وإلا يمكن إدخاله يدوياً.
                                    </p>
                                    <p v-if="form.errors.company_tax_number" class="text-xs text-rose-600">
                                        {{ form.errors.company_tax_number }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="valid_until" class="flex items-center gap-1.5">
                                        <Calendar class="h-3.5 w-3.5 text-muted-foreground" />
                                        صالح حتى
                                        <span class="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="valid_until"
                                        v-model="form.valid_until"
                                        type="date"
                                        class="h-11 rounded-xl"
                                        required
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="activity_at" class="flex items-center gap-1.5">
                                        <Calendar class="h-3.5 w-3.5 text-muted-foreground" />
                                        تاريخ الفعالية مع الوقت
                                    </Label>
                                    <Input
                                        id="activity_at"
                                        v-model="form.activity_at"
                                        type="datetime-local"
                                        class="h-11 rounded-xl"
                                    />
                                    <p v-if="form.errors.activity_at" class="text-xs text-rose-600">{{ form.errors.activity_at }}</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="installation_at" class="flex items-center gap-1.5">
                                        <Calendar class="h-3.5 w-3.5 text-muted-foreground" />
                                        تاريخ التركيب مع الوقت
                                    </Label>
                                    <Input
                                        id="installation_at"
                                        v-model="form.installation_at"
                                        type="datetime-local"
                                        class="h-11 rounded-xl"
                                    />
                                    <p v-if="form.errors.installation_at" class="text-xs text-rose-600">{{ form.errors.installation_at }}</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="dismantling_at" class="flex items-center gap-1.5">
                                        <Calendar class="h-3.5 w-3.5 text-muted-foreground" />
                                        تاريخ الفك مع الوقت
                                    </Label>
                                    <Input
                                        id="dismantling_at"
                                        v-model="form.dismantling_at"
                                        type="datetime-local"
                                        class="h-11 rounded-xl"
                                    />
                                    <p v-if="form.errors.dismantling_at" class="text-xs text-rose-600">{{ form.errors.dismantling_at }}</p>
                                </div>

                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="customer_address" class="flex items-center gap-1.5">
                                        <MapPin class="h-3.5 w-3.5 text-muted-foreground" />
                                        العنوان
                                    </Label>
                                    <Textarea
                                        id="customer_address"
                                        v-model="form.customer_address"
                                        placeholder="المدينة، الحي، الشارع..."
                                        rows="2"
                                        class="rounded-xl resize-none"
                                    />
                                </div>

                                <div class="space-y-2 sm:col-span-2">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <Label for="insurance_amount" class="flex items-center gap-1.5">
                                            <Receipt class="h-3.5 w-3.5 text-muted-foreground" />
                                            مبلغ التأمين
                                        </Label>
                                        <button
                                            v-if="insuranceManual"
                                            type="button"
                                            class="text-xs font-medium text-sky-600 hover:underline"
                                            @click="resetInsuranceToSuggested"
                                        >
                                            إعادة الحساب من المنتجات ({{ formatCurrency(suggestedInsurance) }})
                                        </button>
                                    </div>
                                    <Input
                                        id="insurance_amount"
                                        v-model.number="form.insurance_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="h-11 rounded-xl"
                                        dir="ltr"
                                        @input="onInsuranceInput"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        يُحسب تلقائياً من تأمين المنتجات ويمكن تعديله يدوياً. بدون ضريبة.
                                    </p>
                                    <p v-if="form.errors.insurance_amount" class="text-xs text-rose-600">{{ form.errors.insurance_amount }}</p>
                                </div>

                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="notes" class="flex items-center gap-1.5">
                                        <FileSpreadsheet class="h-3.5 w-3.5 text-muted-foreground" />
                                        ملاحظات
                                    </Label>
                                    <Textarea
                                        id="notes"
                                        v-model="form.notes"
                                        placeholder="شروط خاصة، ملاحظات للعميل..."
                                        rows="2"
                                        class="rounded-xl resize-none"
                                    />
                                </div>

                                <div class="sm:col-span-2">
                                    <label
                                        for="show_online_payment"
                                        class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-primary/30 bg-primary/5 p-4 transition hover:border-primary/50 hover:bg-primary/10"
                                    >
                                        <input
                                            id="show_online_payment"
                                            v-model="form.show_online_payment"
                                            type="checkbox"
                                            class="mt-1 size-4 shrink-0 rounded border-input text-primary accent-primary"
                                        />
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                                                <Link2 class="h-4 w-4 text-primary" />
                                                إظهار الدفع الإلكتروني في عرض السعر
                                            </div>
                                            <p class="text-xs leading-relaxed text-muted-foreground">
                                                عند التفعيل تظهر جزئية Online Payment / الدفع الإلكتروني مع رابط الدفع داخل ملف PDF (يحتاج مبلغ متبقي على العميل).
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Terms & Conditions -->
                    <section class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 bg-muted/30 px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                                    <FileSpreadsheet class="h-4 w-4" />
                                </div>
                                <div>
                                    <h2 class="font-semibold text-foreground">الشروط والأحكام</h2>
                                    <p class="text-xs text-muted-foreground">يمكنك تعديل أو حذف أي بند قبل إنشاء العرض</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" variant="outline" size="sm" class="gap-1.5 rounded-lg" @click="resetTermsToDefault">
                                    استعادة الافتراضي
                                </Button>
                                <Button type="button" size="sm" class="gap-1.5 rounded-lg" @click="addTerm">
                                    <Plus class="h-4 w-4" />
                                    إضافة شرط
                                </Button>
                            </div>
                        </div>

                        <div class="space-y-4 p-5 sm:p-6">
                            <div
                                v-if="form.terms.length === 0"
                                class="rounded-xl border border-dashed border-border bg-muted/10 px-4 py-8 text-center text-sm text-muted-foreground"
                            >
                                لا توجد شروط حالياً. أضف شرطاً أو استعد الشروط الافتراضية.
                            </div>

                            <div
                                v-for="(term, index) in form.terms"
                                :key="index"
                                class="space-y-3 rounded-xl border border-border/60 bg-muted/10 p-4"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-foreground">شرط {{ index + 1 }}</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 gap-1.5 rounded-lg text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/30"
                                        @click="removeTerm(index)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                        حذف
                                    </Button>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label :for="`term-ar-${index}`" class="text-xs text-muted-foreground">العربي</Label>
                                        <Textarea
                                            :id="`term-ar-${index}`"
                                            v-model="term.ar"
                                            rows="3"
                                            dir="rtl"
                                            class="rounded-xl resize-none text-sm"
                                            placeholder="نص الشرط بالعربي"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label :for="`term-en-${index}`" class="text-xs text-muted-foreground">English</Label>
                                        <Textarea
                                            :id="`term-en-${index}`"
                                            v-model="term.en"
                                            rows="3"
                                            dir="ltr"
                                            class="rounded-xl resize-none text-sm"
                                            placeholder="Term text in English"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Products -->
                    <section class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 bg-muted/30 px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    <Package class="h-4 w-4" />
                                </div>
                                <div>
                                    <h2 class="font-semibold text-foreground">بنود العرض</h2>
                                    <p class="text-xs text-muted-foreground">المنتجات والكميات والأسعار</p>
                                </div>
                            </div>
                            <Badge v-if="form.items.length > 0" variant="secondary" class="tabular-nums">
                                {{ form.items.length }} منتج · {{ itemsCount }} وحدة
                            </Badge>
                        </div>

                        <div class="space-y-5 p-5 sm:p-6">
                            <!-- Add item bar -->
                            <div class="rounded-xl border border-dashed border-border bg-muted/20 p-4 sm:p-5">
                                <div class="mb-4 flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="addMode === 'catalog' ? 'default' : 'outline'"
                                        class="rounded-lg"
                                        @click="addMode = 'catalog'"
                                    >
                                        منتج من النظام
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="addMode === 'custom' ? 'default' : 'outline'"
                                        class="rounded-lg"
                                        @click="addMode = 'custom'"
                                    >
                                        منتج غير موجود بالنظام
                                    </Button>
                                </div>

                                <div v-if="addMode === 'catalog'">
                                    <p class="mb-4 text-sm font-medium text-foreground">إضافة منتج للعرض</p>
                                    <div class="grid gap-3 sm:grid-cols-12 sm:items-end">
                                        <div class="space-y-2 sm:col-span-3">
                                            <Label for="category-filter" class="text-xs text-muted-foreground">الصنف</Label>
                                            <select
                                                id="category-filter"
                                                v-model="selectedCategoryId"
                                                class="flex h-11 w-full rounded-xl border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            >
                                                <option value="">كل الأصناف</option>
                                                <option
                                                    v-for="category in categories"
                                                    :key="category.id"
                                                    :value="category.id"
                                                >
                                                    {{ category.category_name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="space-y-2 sm:col-span-4">
                                            <Label for="product-search" class="text-xs text-muted-foreground">المنتج</Label>
                                            <ProductSearchCombobox
                                                v-model="selectedProductId"
                                                :products="filteredProducts"
                                                input-id="product-search"
                                            />
                                        </div>
                                        <div class="space-y-2 sm:col-span-1">
                                            <Label for="quantity" class="text-xs text-muted-foreground">الكمية</Label>
                                            <Input
                                                id="quantity"
                                                v-model="selectedQuantity"
                                                type="number"
                                                min="1"
                                                class="h-11 rounded-xl tabular-nums"
                                            />
                                        </div>
                                        <div class="space-y-2 sm:col-span-2">
                                            <Label for="unit_price" class="text-xs text-muted-foreground">سعر الوحدة (ر.س)</Label>
                                            <Input
                                                id="unit_price"
                                                v-model="selectedUnitPrice"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="h-11 rounded-xl tabular-nums"
                                                dir="ltr"
                                            />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <Button
                                                type="button"
                                                class="h-11 w-full gap-2 rounded-xl"
                                                :disabled="selectedProductId == null"
                                                @click="addItem"
                                            >
                                                <Plus class="h-4 w-4" />
                                                إضافة
                                            </Button>
                                        </div>
                                    </div>
                                    <p v-if="selectedProduct" class="mt-3 text-xs text-muted-foreground">
                                        السعر الافتراضي:
                                        <span class="font-medium tabular-nums text-foreground" dir="ltr">{{ formatCurrency(selectedProduct.price) }}</span>
                                        <span v-if="form.items.some((i) => i.product_id === selectedProduct.id)" class="ms-2 text-amber-600">
                                            · موجود في العرض — سيتم زيادة الكمية
                                        </span>
                                    </p>
                                    <p v-else-if="selectedCategoryId !== '' && !filteredProducts.length" class="mt-3 text-xs text-amber-600">
                                        لا توجد منتجات في هذا الصنف.
                                    </p>
                                </div>

                                <div v-else class="space-y-3">
                                    <p class="text-sm font-medium text-foreground">إضافة منتج مخصص</p>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="space-y-2 sm:col-span-2">
                                            <Label for="custom_name" class="text-xs text-muted-foreground">اسم الصنف</Label>
                                            <Input
                                                id="custom_name"
                                                v-model="customName"
                                                type="text"
                                                class="h-11 rounded-xl"
                                                placeholder="مثال: طاولة إضافية"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="custom_description" class="text-xs text-muted-foreground">الوصف</Label>
                                            <Input
                                                id="custom_description"
                                                v-model="customDescription"
                                                type="text"
                                                class="h-11 rounded-xl"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="custom_statement" class="text-xs text-muted-foreground">البيان</Label>
                                            <Input
                                                id="custom_statement"
                                                v-model="customStatement"
                                                type="text"
                                                class="h-11 rounded-xl"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="custom_quantity" class="text-xs text-muted-foreground">الكمية</Label>
                                            <Input
                                                id="custom_quantity"
                                                v-model="customQuantity"
                                                type="number"
                                                min="1"
                                                class="h-11 rounded-xl tabular-nums"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="custom_price" class="text-xs text-muted-foreground">السعر</Label>
                                            <Input
                                                id="custom_price"
                                                v-model="customPrice"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="h-11 rounded-xl tabular-nums"
                                                dir="ltr"
                                            />
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        class="h-11 gap-2 rounded-xl"
                                        :disabled="!customName.trim()"
                                        @click="addCustomItem"
                                    >
                                        <Plus class="h-4 w-4" />
                                        إضافة الصنف المخصص
                                    </Button>
                                </div>
                            </div>

                            <!-- Items table -->
                            <div v-if="form.items.length > 0" class="overflow-hidden rounded-xl border border-border/60">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/40 hover:bg-muted/40">
                                            <TableHead class="font-semibold">المنتج</TableHead>
                                            <TableHead class="hidden font-semibold md:table-cell">الوصف</TableHead>
                                            <TableHead class="w-24 font-semibold text-center">الكمية</TableHead>
                                            <TableHead class="w-32 font-semibold">السعر</TableHead>
                                            <TableHead class="w-32 font-semibold">خصم / وحدة</TableHead>
                                            <TableHead class="w-28 font-semibold">الإجمالي</TableHead>
                                            <TableHead class="w-12" />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="(item, index) in form.items"
                                            :key="index"
                                            class="group"
                                        >
                                            <TableCell>
                                                <div class="font-medium text-foreground">{{ item.product_name }}</div>
                                                <div v-if="!item.product_id" class="mt-0.5 text-[11px] text-amber-700">صنف مخصص</div>
                                                <div class="mt-0.5 line-clamp-1 text-xs text-muted-foreground md:hidden">
                                                    {{ item.description }}
                                                </div>
                                                <div v-if="item.statement" class="mt-0.5 text-xs text-muted-foreground">
                                                    البيان: {{ item.statement }}
                                                </div>
                                            </TableCell>
                                            <TableCell class="hidden max-w-[200px] truncate text-sm text-muted-foreground md:table-cell">
                                                {{ item.description || '—' }}
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    v-model="item.quantity"
                                                    type="number"
                                                    min="1"
                                                    class="mx-auto h-9 w-16 rounded-lg text-center tabular-nums"
                                                    @input="updateItemPrice(index)"
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    v-model="item.unit_price"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    class="h-9 w-full min-w-[5.5rem] rounded-lg tabular-nums"
                                                    dir="ltr"
                                                    @input="updateItemPrice(index)"
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    v-model="item.discount_amount"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    :max="Number(item.unit_price) || 0"
                                                    class="h-9 w-full min-w-[5.5rem] rounded-lg border-amber-200 bg-amber-50/50 tabular-nums focus-visible:ring-amber-400"
                                                    dir="ltr"
                                                    @input="updateItemPrice(index)"
                                                />
                                                <p
                                                    v-if="Number(item.discount_amount) > 0"
                                                    class="mt-1 text-[10px] text-amber-700"
                                                >
                                                    بعد الخصم:
                                                    <span dir="ltr">{{ formatCurrency(Number(item.unit_price) - Number(item.discount_amount)) }}</span>
                                                </p>
                                            </TableCell>
                                            <TableCell class="font-semibold tabular-nums text-foreground" dir="ltr">
                                                {{ formatCurrency(item.total_price) }}
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-8 w-8 rounded-lg text-muted-foreground opacity-70 transition-opacity hover:bg-red-50 hover:text-red-600 group-hover:opacity-100 dark:hover:bg-red-950/30"
                                                    title="حذف البند"
                                                    @click="removeItem(index)"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>

                            <!-- Empty state -->
                            <div
                                v-else
                                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/10 px-6 py-12 text-center"
                            >
                                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted">
                                    <ShoppingCart class="h-7 w-7 text-muted-foreground" />
                                </div>
                                <p class="font-medium text-foreground">لا توجد بنود بعد</p>
                                <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                                    اختر منتجاً من البحث أعلاه واضغط «إضافة» لبناء عرض السعر
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Sidebar summary -->
                <aside class="lg:sticky lg:top-6 lg:col-span-1">
                    <div class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm">
                        <div class="flex items-center gap-3 border-b border-border/60 bg-muted/30 px-5 py-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                <Receipt class="h-4 w-4" />
                            </div>
                            <div>
                                <h2 class="font-semibold text-foreground">ملخص العرض</h2>
                                <p class="text-xs text-muted-foreground">الحسابات قبل الحفظ</p>
                            </div>
                        </div>

                        <div class="space-y-4 p-5">
                            <div class="space-y-3 rounded-xl bg-muted/30 p-4">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">عدد البنود</span>
                                    <span class="font-medium tabular-nums">{{ form.items.length }}</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">إجمالي الوحدات</span>
                                    <span class="font-medium tabular-nums">{{ itemsCount }}</span>
                                </div>
                                <div class="border-t border-border/60 pt-3">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-muted-foreground">المجموع قبل الخصم</span>
                                        <span class="font-medium tabular-nums" dir="ltr">{{ formatCurrency(grossSubtotal) }}</span>
                                    </div>
                                    <div
                                        v-if="discountTotal > 0"
                                        class="mt-2 flex items-center justify-between text-sm text-amber-700"
                                    >
                                        <span>إجمالي الخصم</span>
                                        <span class="font-semibold tabular-nums" dir="ltr">- {{ formatCurrency(discountTotal) }}</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-sm">
                                        <span class="text-muted-foreground">المجموع بعد الخصم</span>
                                        <span class="font-medium tabular-nums" dir="ltr">{{ formatCurrency(subtotal) }}</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-sm">
                                        <span class="text-muted-foreground">ض.ق.م (15%)</span>
                                        <span class="font-medium tabular-nums" dir="ltr">{{ formatCurrency(taxAmount) }}</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-sm">
                                        <span class="text-muted-foreground">مبلغ التأمين <span class="text-[11px]">(لا يُحسب بالإجمالي)</span></span>
                                        <span class="font-medium tabular-nums" dir="ltr">{{ formatCurrency(form.insurance_amount || 0) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between rounded-xl bg-primary/5 px-4 py-4 ring-1 ring-primary/10">
                                <span class="font-semibold text-foreground">الإجمالي النهائي</span>
                                <span class="text-xl font-bold tabular-nums text-primary" dir="ltr">
                                    {{ formatCurrency(totalAmount) }}
                                </span>
                            </div>

                            <div class="space-y-2 rounded-xl border border-border/60 bg-muted/20 p-4">
                                <Label for="amount_paid" class="text-sm font-medium text-foreground">
                                    المدفوع من الإجمالي
                                </Label>
                                <Input
                                    id="amount_paid"
                                    v-model.number="form.amount_paid"
                                    type="number"
                                    min="0"
                                    :max="totalAmount"
                                    step="0.01"
                                    class="h-11 rounded-xl tabular-nums"
                                    dir="ltr"
                                    placeholder="0.00"
                                />
                                <p class="text-xs text-muted-foreground">
                                    أدخل المبلغ الذي دفعه العميل. عند الحفظ يُنشأ سند قبض تلقائياً. اعتماد العرض يحوّله لطلب؛ بدون مدفوعات يظهر فوراً. أمر العمل بعد اعتماد المحاسب لسند القبض.
                                </p>
                                <p v-if="form.errors.amount_paid" class="text-xs text-rose-600">{{ form.errors.amount_paid }}</p>
                                <div class="flex items-center justify-between border-t border-border/60 pt-3 text-sm">
                                    <span class="text-muted-foreground">المتبقي</span>
                                    <span
                                        class="font-semibold tabular-nums"
                                        :class="remainingAmount > 0 ? 'text-amber-700' : 'text-emerald-700'"
                                        dir="ltr"
                                    >
                                        {{ formatCurrency(remainingAmount) }}
                                    </span>
                                </div>
                            </div>

                            <label
                                for="show_online_payment_summary"
                                class="flex cursor-pointer items-start gap-3 rounded-xl border border-border/60 bg-muted/20 p-4 transition hover:bg-muted/30"
                            >
                                <input
                                    id="show_online_payment_summary"
                                    v-model="form.show_online_payment"
                                    type="checkbox"
                                    class="mt-1 size-4 shrink-0 rounded border-input text-primary accent-primary"
                                />
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-sm font-medium text-foreground">
                                        <Link2 class="h-4 w-4 text-primary" />
                                        إظهار الدفع الإلكتروني في عرض السعر
                                    </div>
                                    <p class="text-xs leading-relaxed text-muted-foreground">
                                        يظهر رابط الدفع في ملف PDF عند وجود مبلغ متبقي.
                                    </p>
                                </div>
                            </label>

                            <div class="space-y-2 pt-1">
                                <Button
                                    type="submit"
                                    class="h-11 w-full gap-2 rounded-xl text-base font-semibold"
                                    :disabled="form.processing || form.items.length === 0"
                                >
                                    <FileSpreadsheet class="h-4 w-4" />
                                    {{ form.processing ? 'جاري الحفظ...' : 'إنشاء عرض السعر' }}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-11 w-full rounded-xl"
                                    @click="$inertia.visit(route('quotations.index'))"
                                >
                                    إلغاء
                                </Button>
                            </div>

                            <p v-if="form.items.length === 0" class="text-center text-xs text-muted-foreground">
                                أضف منتجاً واحداً على الأقل لتفعيل الحفظ
                            </p>
                        </div>
                    </div>
                </aside>
            </form>
            </template>
        </div>
    </AppSidebarLayout>
</template>
