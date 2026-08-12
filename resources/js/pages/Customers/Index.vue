<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Building2,
    CalendarDays,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Download,
    Edit,
    Eye,
    Filter,
    Mail,
    Pencil,
    Phone,
    Plus,
    Search,
    Trash2,
    User,
    Users,
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
    phone_secondary: string | null;
    email: string | null;
    address: string | null;
    tax_number: string | null;
    iban: string | null;
    iban_image_url: string | null;
    notes: string | null;
    country: string | null;
    gender: string | null;
    date_of_birth: string | null;
    created_at: string | null;
    quotations_count: number;
    quotations: Quotation[];
}

type TypeTab = 'all' | 'individual' | 'company';
type DateFilter = 'all' | '7' | '30';

interface Props {
    customers: Customer[];
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);
const errorMessage = computed(() => page.props.flash?.error as string | undefined);

const activeTab = ref<TypeTab>('all');
const searchQuery = ref('');
const dateFilter = ref<DateFilter>('all');
const showFilters = ref(false);
const currentPage = ref(1);
const perPage = 10;
const selectedKeys = ref<string[]>([]);

const showModal = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const editingCustomer = ref<Customer | null>(null);
const expandedKey = ref<string | null>(null);
const expandedQuotationId = ref<number | null>(null);

const typeTabs: { key: TypeTab; label: string }[] = [
    { key: 'all', label: 'الكل' },
    { key: 'individual', label: 'فردي' },
    { key: 'company', label: 'شركة' },
];

const typeBadgeClass: Record<'individual' | 'company', string> = {
    individual: 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-100 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-900/50',
    company: 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-100 dark:bg-violet-950/40 dark:text-violet-300 dark:ring-violet-900/50',
};

const form = useForm({
    company_name: '',
    name: '',
    contact_name: '',
    phone: '',
    phone_secondary: '',
    email: '',
    address: '',
    tax_number: '',
    country: '',
    gender: '' as '' | 'male' | 'female',
    date_of_birth: '',
    iban: '',
    iban_image: null as File | null,
    remove_iban_image: false,
    notes: '',
});

const ibanImageInput = ref<HTMLInputElement | null>(null);
const ibanImagePreview = ref<string | null>(null);

const formTitle = computed(() => {
    if (formMode.value === 'create') {
        return 'إضافة عميل شركة';
    }

    return editingCustomer.value?.type === 'company'
        ? 'تعديل عميل شركة'
        : 'تعديل عميل فردي';
});

const currentIbanPreview = computed(() => {
    if (form.remove_iban_image) {
        return ibanImagePreview.value;
    }

    return ibanImagePreview.value || editingCustomer.value?.iban_image_url || null;
});

const filteredCustomers = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const now = Date.now();

    return props.customers.filter((customer) => {
        if (activeTab.value !== 'all' && customer.type !== activeTab.value) {
            return false;
        }

        if (dateFilter.value !== 'all' && customer.created_at) {
            const days = Number(dateFilter.value);
            const created = new Date(customer.created_at).getTime();
            if (Number.isNaN(created) || now - created > days * 24 * 60 * 60 * 1000) {
                return false;
            }
        }

        if (!query) {
            return true;
        }

        return [
            customer.name,
            customer.contact_name ?? '',
            customer.phone ?? '',
            customer.phone_secondary ?? '',
            customer.email ?? '',
            customer.country ?? '',
            customer.type === 'company' ? 'شركة' : 'فردي',
            String(customer.id),
        ]
            .join(' ')
            .toLowerCase()
            .includes(query);
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredCustomers.value.length / perPage)));

const paginatedCustomers = computed(() => {
    const start = (currentPage.value - 1) * perPage;
    return filteredCustomers.value.slice(start, start + perPage);
});

const showingFrom = computed(() => {
    if (filteredCustomers.value.length === 0) {
        return 0;
    }
    return (currentPage.value - 1) * perPage + 1;
});

const showingTo = computed(() => Math.min(currentPage.value * perPage, filteredCustomers.value.length));

const pageNumbers = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    const pages: Array<number | 'ellipsis'> = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);

    if (start > 2) pages.push('ellipsis');
    for (let i = start; i <= end; i += 1) pages.push(i);
    if (end < total - 1) pages.push('ellipsis');
    pages.push(total);
    return pages;
});

const allVisibleSelected = computed(
    () =>
        paginatedCustomers.value.length > 0
        && paginatedCustomers.value.every((customer) => selectedKeys.value.includes(customer.key)),
);

watch([activeTab, searchQuery, dateFilter], () => {
    currentPage.value = 1;
    selectedKeys.value = [];
    expandedKey.value = null;
    expandedQuotationId.value = null;
});

watch(totalPages, (pages) => {
    if (currentPage.value > pages) {
        currentPage.value = pages;
    }
});

function tabCount(tab: TypeTab): number {
    if (tab === 'all') {
        return props.customers.length;
    }
    return props.customers.filter((customer) => customer.type === tab).length;
}

function toggleSelectAll() {
    if (allVisibleSelected.value) {
        const visible = new Set(paginatedCustomers.value.map((customer) => customer.key));
        selectedKeys.value = selectedKeys.value.filter((key) => !visible.has(key));
        return;
    }

    selectedKeys.value = Array.from(new Set([
        ...selectedKeys.value,
        ...paginatedCustomers.value.map((customer) => customer.key),
    ]));
}

function toggleSelect(key: string) {
    if (selectedKeys.value.includes(key)) {
        selectedKeys.value = selectedKeys.value.filter((item) => item !== key);
        return;
    }
    selectedKeys.value = [...selectedKeys.value, key];
}

function goToPage(pageNumber: number) {
    if (pageNumber < 1 || pageNumber > totalPages.value) {
        return;
    }
    currentPage.value = pageNumber;
}

function dateFilterLabel(value: DateFilter): string {
    if (value === '7') return 'آخر 7 أيام';
    if (value === '30') return 'آخر 30 يوم';
    return 'كل الفترات';
}

function resetIbanImageUi() {
    form.iban_image = null;
    form.remove_iban_image = false;
    if (ibanImagePreview.value) {
        URL.revokeObjectURL(ibanImagePreview.value);
        ibanImagePreview.value = null;
    }
    if (ibanImageInput.value) {
        ibanImageInput.value.value = '';
    }
}

function openForm() {
    formMode.value = 'create';
    editingCustomer.value = null;
    form.reset();
    form.clearErrors();
    resetIbanImageUi();
    showModal.value = true;
}

function openEditForm(customer: Customer) {
    formMode.value = 'edit';
    editingCustomer.value = customer;
    form.clearErrors();
    form.company_name = customer.type === 'company' ? customer.name : '';
    form.name = customer.type === 'individual' ? customer.name : '';
    form.contact_name = customer.contact_name ?? '';
    form.phone = customer.phone ?? '';
    form.phone_secondary = customer.phone_secondary ?? '';
    form.email = customer.email ?? '';
    form.address = customer.address ?? '';
    form.tax_number = customer.tax_number ?? '';
    form.country = customer.country ?? '';
    form.gender = (customer.gender as '' | 'male' | 'female') || '';
    form.date_of_birth = customer.date_of_birth ?? '';
    form.iban = customer.iban ?? '';
    form.notes = customer.notes ?? '';
    resetIbanImageUi();
    showModal.value = true;
}

function closeForm() {
    showModal.value = false;
    formMode.value = 'create';
    editingCustomer.value = null;
    form.reset();
    form.clearErrors();
    resetIbanImageUi();
}

function onIbanImageSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    form.iban_image = file;
    form.remove_iban_image = false;

    if (ibanImagePreview.value) {
        URL.revokeObjectURL(ibanImagePreview.value);
        ibanImagePreview.value = null;
    }

    if (file) {
        ibanImagePreview.value = URL.createObjectURL(file);
    }
}

function clearIbanImage() {
    form.iban_image = null;
    if (ibanImagePreview.value) {
        URL.revokeObjectURL(ibanImagePreview.value);
        ibanImagePreview.value = null;
    }
    if (ibanImageInput.value) {
        ibanImageInput.value.value = '';
    }

    if (formMode.value === 'edit' && editingCustomer.value?.iban_image_url) {
        form.remove_iban_image = true;
    }
}

function submit() {
    if (formMode.value === 'create') {
        form.post(route('company-clients.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => closeForm(),
        });
        return;
    }

    const customer = editingCustomer.value;
    if (!customer) {
        return;
    }

    form.post(`/customers/${customer.type}/${customer.id}`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closeForm(),
    });
}

function destroyCustomer(customer: Customer) {
    const label = customer.type === 'company' ? `شركة «${customer.name}»` : `عميل «${customer.name}»`;
    if (!confirm(`هل أنت متأكد من حذف ${label}؟`)) {
        return;
    }

    router.delete(route('customers.destroy', { type: customer.type, id: customer.id }), {
        preserveScroll: true,
    });
}

function customerProfileUrl(customer: Customer) {
    return `/customers/${customer.type}/${customer.id}`;
}

function openCustomerProfile(customer: Customer) {
    router.visit(customerProfileUrl(customer));
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
            return 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50';
        case 'rejected':
        case 'expired':
            return 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-100 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900/50';
        case 'sent':
            return 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-100 dark:bg-blue-950/40 dark:text-blue-300 dark:ring-blue-900/50';
        default:
            return 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700';
    }
}

function quotationPdfUrl(id: number): string {
    return `/quotations/${id}/pdf?v=${Date.now()}`;
}
</script>

<template>
    <Head title="العملاء" />

    <div class="flex min-w-0 flex-1 flex-col gap-5 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">
                    <Users class="size-6 text-blue-600" />
                    العملاء
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
                    عملاء الموقع (فردي) وعملاء الشركات — الإضافة الجديدة لعملاء الشركات فقط
                </p>
            </div>
            <Button class="gap-2 self-start rounded-xl" @click="openForm">
                <Plus class="size-4" />
                إضافة عميل شركة
            </Button>
        </div>

        <p
            v-if="successMessage"
            class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300"
        >
            {{ successMessage }}
        </p>
        <p
            v-if="errorMessage"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300"
        >
            {{ errorMessage }}
        </p>

        <div class="overflow-x-auto">
            <div class="flex min-w-max items-center gap-1 border-b border-gray-200 dark:border-neutral-700">
                <button
                    v-for="tab in typeTabs"
                    :key="tab.key"
                    type="button"
                    class="relative px-3 py-2.5 text-sm font-medium transition-colors sm:px-4"
                    :class="
                        activeTab === tab.key
                            ? 'text-blue-700 dark:text-blue-300'
                            : 'text-gray-500 hover:text-gray-800 dark:text-neutral-400 dark:hover:text-neutral-200'
                    "
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                    <span class="ms-1.5 text-xs tabular-nums text-gray-400">({{ formatInteger(tabCount(tab.key)) }})</span>
                    <span
                        v-if="activeTab === tab.key"
                        class="absolute inset-x-0 -bottom-px h-0.5 rounded-full bg-blue-600"
                    />
                </button>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
            <div class="flex flex-col gap-3 border-b border-gray-100 p-4 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    <label class="flex h-10 w-full max-w-sm items-center gap-2 rounded-full border border-gray-200 bg-white px-3.5 text-gray-400 transition focus-within:border-blue-300 focus-within:ring-2 focus-within:ring-blue-100 dark:border-neutral-700 dark:bg-neutral-950 dark:focus-within:border-blue-700 dark:focus-within:ring-blue-950">
                        <Search class="size-4 shrink-0 stroke-[1.75]" />
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="ابحث هنا..."
                            class="w-full bg-transparent text-sm text-gray-800 outline-none placeholder:text-gray-400 dark:text-neutral-100"
                        />
                    </label>

                    <button
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-full border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        :class="showFilters ? 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300' : ''"
                        @click="showFilters = !showFilters"
                    >
                        <Filter class="size-4 stroke-[1.75]" />
                        فلاتر
                    </button>

                    <div class="relative">
                        <select
                            v-model="dateFilter"
                            class="h-10 appearance-none rounded-full border border-gray-200 bg-white pe-9 ps-10 text-sm font-medium text-gray-600 outline-none transition hover:bg-gray-50 focus:border-blue-300 focus:ring-2 focus:ring-blue-100 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        >
                            <option value="all">كل الفترات</option>
                            <option value="7">آخر 7 أيام</option>
                            <option value="30">آخر 30 يوم</option>
                        </select>
                        <CalendarDays class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    </div>
                </div>

                <p class="text-xs text-gray-400 sm:text-sm">
                    {{ dateFilterLabel(dateFilter) }} · {{ formatInteger(filteredCustomers.length) }} نتيجة
                </p>
            </div>

            <div v-if="showFilters" class="border-b border-gray-100 px-4 py-3 dark:border-neutral-800">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="tab in typeTabs"
                        :key="`filter-${tab.key}`"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                        :class="
                            activeTab === tab.key
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700'
                        "
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

                    <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] border-collapse text-sm">
                            <thead>
                        <tr class="border-b border-gray-100 text-start dark:border-neutral-800">
                            <th class="w-12 px-4 py-3.5">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    :checked="allVisibleSelected"
                                    @change="toggleSelectAll"
                                />
                            </th>
                            <th class="w-10 px-2 py-3.5" />
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">النوع</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الاسم</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">التواصل</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الجوال</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">البريد</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">عروض الأسعار</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">التاريخ</th>
                            <th class="px-4 py-3.5 text-end text-[13px] font-semibold text-gray-700 dark:text-neutral-200">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                        <tr v-if="paginatedCustomers.length === 0">
                            <td colspan="10" class="px-4 py-16 text-center text-gray-500 dark:text-neutral-400">
                                لا يوجد عملاء مطابقون للبحث أو الفلتر الحالي.
                            </td>
                        </tr>

                        <template v-for="customer in paginatedCustomers" :key="customer.key">
                            <tr
                                class="cursor-pointer border-b border-gray-100 transition hover:bg-gray-50/70 dark:border-neutral-800 dark:hover:bg-neutral-800/40"
                                :class="expandedKey === customer.key ? 'bg-gray-50/80 dark:bg-neutral-800/30' : ''"
                                @click="openCustomerProfile(customer)"
                            >
                                <td class="px-4 py-4" @click.stop>
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        :checked="selectedKeys.includes(customer.key)"
                                        @change="toggleSelect(customer.key)"
                                    />
                                </td>
                                <td class="px-2 py-4 text-center" @click.stop="toggleCustomer(customer)">
                                    <button
                                        v-if="customer.type === 'company'"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-neutral-800"
                                        :title="expandedKey === customer.key ? 'إخفاء العروض' : 'عرض العروض'"
                                    >
                                        <ChevronDown
                                            class="size-4 transition-transform"
                                            :class="expandedKey === customer.key ? 'rotate-180' : ''"
                                        />
                                    </button>
                                </td>
                                <td class="px-3 py-4">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="typeBadgeClass[customer.type]"
                                    >
                                        <Building2 v-if="customer.type === 'company'" class="size-3" />
                                        <User v-else class="size-3" />
                                        {{ customer.type === 'company' ? 'شركة' : 'فردي' }}
                                    </span>
                                </td>
                                <td class="px-3 py-4">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ customer.name }}</span>
                                </td>
                                <td class="px-3 py-4 text-gray-600 dark:text-neutral-300">
                                    {{ customer.type === 'company' ? (customer.contact_name || '—') : 'عميل الموقع' }}
                                </td>
                                <td class="px-3 py-4 text-gray-600 dark:text-neutral-300">
                                    <div class="flex flex-col items-start gap-0.5" dir="ltr">
                                        <div class="flex items-center gap-2">
                                            <Phone class="size-3.5 shrink-0 text-gray-400" />
                                            <span>{{ customer.phone || '—' }}</span>
                                        </div>
                                        <span v-if="customer.phone_secondary" class="ps-5 text-xs text-gray-400">
                                            {{ customer.phone_secondary }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-4 text-gray-600 dark:text-neutral-300">
                                    <div class="flex items-center gap-2" dir="ltr">
                                        <Mail class="size-3.5 shrink-0 text-gray-400" />
                                        <span class="truncate">{{ customer.email || '—' }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-4">
                                    <span
                                        v-if="customer.type === 'company'"
                                        class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold tabular-nums text-gray-700 dark:bg-neutral-800 dark:text-neutral-300"
                                    >
                                        {{ formatInteger(customer.quotations_count) }} عرض
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-3 py-4 tabular-nums text-gray-600 dark:text-neutral-300" dir="ltr">
                                    {{ customer.created_at ? formatDateTime(customer.created_at) : '—' }}
                                </td>
                                <td class="px-4 py-4" @click.stop>
                                    <div class="flex items-center justify-end gap-1.5">
                                        <Link
                                            :href="customerProfileUrl(customer)"
                                            class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600 dark:border-neutral-700 dark:hover:border-blue-900 dark:hover:bg-blue-950/40 dark:hover:text-blue-300"
                                            title="عرض"
                                        >
                                            <Eye class="size-3.5 stroke-[1.75]" />
                                        </Link>
                                        <button
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600 dark:border-neutral-700 dark:hover:border-blue-900 dark:hover:bg-blue-950/40 dark:hover:text-blue-300"
                                            title="تعديل"
                                            @click="openEditForm(customer)"
                                        >
                                            <Pencil class="size-3.5 stroke-[1.75]" />
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-neutral-700 dark:hover:border-red-900 dark:hover:bg-red-950/40 dark:hover:text-red-300"
                                            title="حذف"
                                            @click="destroyCustomer(customer)"
                                        >
                                            <Trash2 class="size-3.5 stroke-[1.75]" />
                                        </button>
                                    </div>
                                    </td>
                                </tr>

                            <tr v-if="customer.type === 'company' && expandedKey === customer.key">
                                <td colspan="10" class="border-b border-gray-100 bg-gray-50/70 px-4 py-4 dark:border-neutral-800 dark:bg-neutral-800/20 sm:px-6">
                                    <div
                                        v-if="customer.quotations.length === 0"
                                        class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-neutral-700 dark:text-neutral-400"
                                    >
                                        لا توجد عروض أسعار مرتبطة بهذه الشركة بعد.
                                    </div>

                                    <div v-else class="space-y-3">
                                        <p class="text-sm font-medium text-gray-500 dark:text-neutral-400">
                                            عروض الأسعار المرسلة لـ «{{ customer.name }}»
                                            ({{ formatInteger(customer.quotations_count) }})
                                        </p>

                                        <div
                                            v-for="quotation in customer.quotations"
                                            :key="quotation.id"
                                            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900"
                                        >
                                            <div
                                                class="flex cursor-pointer flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                                @click="toggleQuotation(quotation.id)"
                                            >
                                                <div class="min-w-0 space-y-1">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="font-semibold text-gray-900 dark:text-white" dir="ltr">
                                                            {{ quotation.quotation_number }}
                                                        </span>
                                                        <span
                                                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                                            :class="getStatusClass(quotation.status)"
                                                        >
                                                            {{ getStatusText(quotation.status) }}
                                                        </span>
                                                    </div>
                                                    <p class="text-sm text-gray-400">
                                                        {{ formatDate(quotation.created_at) }}
                                                        <span v-if="quotation.valid_until"> · صالح حتى {{ formatDate(quotation.valid_until) }}</span>
                                                        <span v-if="quotation.user"> · بواسطة {{ quotation.user.name }}</span>
                                                    </p>
                                                </div>
                                                <div class="flex flex-wrap items-center gap-2" @click.stop>
                                                    <span class="me-2 text-base font-bold tabular-nums text-gray-900 dark:text-white" dir="ltr">
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
                                                            عرض السعر
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
                                                class="border-t border-gray-100 bg-gray-50/60 px-4 py-4 dark:border-neutral-800 dark:bg-neutral-800/30"
                                            >
                                                <div class="mb-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                                    <div>
                                                        <p class="text-gray-400">العميل</p>
                                                        <p class="font-medium text-gray-900 dark:text-white">{{ quotation.customer_name || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-gray-400">الجوال</p>
                                                        <p class="font-medium tabular-nums text-gray-900 dark:text-white" dir="ltr">{{ quotation.customer_phone || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-gray-400">البريد</p>
                                                        <p class="font-medium text-gray-900 dark:text-white" dir="ltr">{{ quotation.customer_email || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-gray-400">العنوان</p>
                                                        <p class="font-medium text-gray-900 dark:text-white">{{ quotation.customer_address || '—' }}</p>
                                                    </div>
                                                </div>

                                                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
                                                    <table class="w-full text-sm">
                                                        <thead>
                                                            <tr class="border-b border-gray-100 dark:border-neutral-800">
                                                                <th class="px-3 py-2 text-start font-medium text-gray-600 dark:text-neutral-300">المنتج</th>
                                                                <th class="px-3 py-2 text-start font-medium text-gray-600 dark:text-neutral-300">الكمية</th>
                                                                <th class="px-3 py-2 text-start font-medium text-gray-600 dark:text-neutral-300">السعر</th>
                                                                <th class="px-3 py-2 text-start font-medium text-gray-600 dark:text-neutral-300">الإجمالي</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr
                                                                v-for="item in quotation.items"
                                                                :key="item.id"
                                                                class="border-b border-gray-100 last:border-0 dark:border-neutral-800"
                                                            >
                                                                <td class="px-3 py-2">
                                                                    <div class="font-medium text-gray-900 dark:text-white">{{ item.product_name }}</div>
                                                                    <div v-if="item.description" class="text-xs text-gray-400">
                                                                        {{ item.description }}
                                                                    </div>
                                                                </td>
                                                                <td class="px-3 py-2 tabular-nums text-gray-600 dark:text-neutral-300" dir="ltr">{{ formatInteger(item.quantity) }}</td>
                                                                <td class="px-3 py-2 tabular-nums text-gray-600 dark:text-neutral-300" dir="ltr">{{ formatPrice(item.unit_price) }}</td>
                                                                <td class="px-3 py-2 tabular-nums font-medium text-gray-900 dark:text-white" dir="ltr">{{ formatPrice(item.total_price) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                                                <div class="mt-3 flex flex-col items-end gap-1 text-sm">
                                                    <div class="flex gap-6">
                                                        <span class="text-gray-400">المجموع</span>
                                                        <span class="tabular-nums text-gray-900 dark:text-white" dir="ltr">{{ formatPrice(quotation.subtotal) }} ر.س</span>
                                                    </div>
                                                    <div class="flex gap-6">
                                                        <span class="text-gray-400">الضريبة</span>
                                                        <span class="tabular-nums text-gray-900 dark:text-white" dir="ltr">{{ formatPrice(quotation.tax_amount) }} ر.س</span>
                                                    </div>
                                                    <div class="flex gap-6 text-base font-bold text-gray-900 dark:text-white">
                                                        <span>الإجمالي</span>
                                                        <span class="tabular-nums" dir="ltr">{{ formatPrice(quotation.total_amount) }} ر.س</span>
                                                    </div>
                                                </div>

                                                <p
                                                    v-if="quotation.notes"
                                                    class="mt-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400"
                                                >
                                                    <span class="font-medium text-gray-900 dark:text-white">ملاحظات:</span>
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

            <div class="flex flex-col gap-3 border-t border-gray-100 px-4 py-4 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500 dark:text-neutral-400">
                    عرض {{ formatInteger(showingFrom) }} - {{ formatInteger(showingTo) }} من {{ formatInteger(filteredCustomers.length) }}
                </p>

                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 disabled:opacity-40 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                        :disabled="currentPage <= 1"
                        @click="goToPage(currentPage - 1)"
                    >
                        <ChevronRight class="size-4" />
                    </button>

                    <template v-for="(item, index) in pageNumbers" :key="`${item}-${index}`">
                        <span v-if="item === 'ellipsis'" class="px-1 text-gray-400">...</span>
                        <button
                            v-else
                            type="button"
                            class="inline-flex size-8 items-center justify-center rounded-full text-sm font-medium transition"
                            :class="
                                currentPage === item
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700'
                            "
                            @click="goToPage(item)"
                        >
                            {{ item }}
                        </button>
                    </template>

                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 disabled:opacity-40 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                        :disabled="currentPage >= totalPages"
                        @click="goToPage(currentPage + 1)"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            @click.self="closeForm"
        >
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeForm" />

            <div class="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white shadow-2xl dark:bg-neutral-800" dir="rtl">
                <div class="flex items-center justify-between border-b border-neutral-200 p-6 dark:border-neutral-700">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                            <Pencil v-if="formMode === 'edit'" class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            <Building2 v-else class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                                {{ formTitle }}
                            </h2>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ formMode === 'edit' ? 'يمكنك تعديل بيانات العميل والآيبان' : 'إضافة عميل شركة جديد للقائمة' }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="text-neutral-400 transition-colors hover:text-neutral-600 dark:hover:text-neutral-200"
                        @click="closeForm"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form class="grid gap-4 p-6 sm:grid-cols-2" @submit.prevent="submit">
                    <template v-if="formMode === 'create' || editingCustomer?.type === 'company'">
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="company_name">اسم الشركة <span class="text-red-500">*</span></Label>
                            <Input id="company_name" v-model="form.company_name" required :class="{ 'border-red-500': form.errors.company_name }" />
                            <p v-if="form.errors.company_name" class="text-xs text-red-500">{{ form.errors.company_name }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="contact_name">اسم المسؤول</Label>
                            <Input id="contact_name" v-model="form.contact_name" />
                        </div>
                    </template>

                    <template v-else>
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="name">اسم العميل <span class="text-red-500">*</span></Label>
                            <Input id="name" v-model="form.name" required :class="{ 'border-red-500': form.errors.name }" />
                            <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="gender">الجنس</Label>
                            <select
                                id="gender"
                                v-model="form.gender"
                                class="flex h-10 w-full rounded-md border border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                            >
                                <option value="">—</option>
                                <option value="male">ذكر</option>
                                <option value="female">أنثى</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="date_of_birth">تاريخ الميلاد</Label>
                            <Input id="date_of_birth" v-model="form.date_of_birth" type="date" dir="ltr" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="country">الدولة</Label>
                            <Input id="country" v-model="form.country" />
                        </div>
                    </template>

                    <div class="space-y-1.5">
                        <Label for="phone">الجوال</Label>
                        <Input id="phone" v-model="form.phone" dir="ltr" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="phone_secondary">جوال آخر</Label>
                        <Input id="phone_secondary" v-model="form.phone_secondary" dir="ltr" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="email">البريد الإلكتروني</Label>
                        <Input id="email" v-model="form.email" type="email" dir="ltr" :class="{ 'border-red-500': form.errors.email }" />
                        <p v-if="form.errors.email" class="text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>

                    <div v-if="formMode === 'create' || editingCustomer?.type === 'company'" class="space-y-1.5">
                        <Label for="tax_number">الرقم الضريبي</Label>
                        <Input id="tax_number" v-model="form.tax_number" dir="ltr" />
                    </div>

                    <div class="space-y-1.5" :class="formMode === 'edit' && editingCustomer?.type === 'individual' ? 'sm:col-span-2' : ''">
                        <Label for="iban">رقم الآيبان (IBAN)</Label>
                        <Input
                            id="iban"
                            v-model="form.iban"
                            class="font-mono tracking-wide"
                            dir="ltr"
                            placeholder="SAxxxxxxxxxxxxxxxxxxxxxx"
                            maxlength="34"
                            :class="{ 'border-red-500': form.errors.iban }"
                        />
                        <p v-if="form.errors.iban" class="text-xs text-red-500">{{ form.errors.iban }}</p>
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="iban_image">صورة الآيبان</Label>
                        <input
                            id="iban_image"
                            ref="ibanImageInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            class="block w-full cursor-pointer rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm file:me-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white dark:border-neutral-700 dark:bg-neutral-900"
                            @change="onIbanImageSelected"
                        />
                        <p class="text-xs text-neutral-500">JPG أو PNG أو WebP — بحد أقصى 5 ميجابايت</p>
                        <p v-if="form.errors.iban_image" class="text-xs text-red-500">{{ form.errors.iban_image }}</p>
                        <div v-if="currentIbanPreview" class="relative mt-2 inline-block overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                            <img :src="currentIbanPreview" alt="معاينة صورة الآيبان" class="max-h-40 max-w-full object-contain" />
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                class="absolute start-2 top-2 h-8"
                                @click="clearIbanImage"
                            >
                                إزالة
                            </Button>
                        </div>
                    </div>

                    <div v-if="formMode === 'create' || editingCustomer?.type === 'company'" class="space-y-1.5 sm:col-span-2">
                        <Label for="address">العنوان</Label>
                        <Textarea id="address" v-model="form.address" rows="2" class="resize-none" />
                    </div>
                    <div v-if="formMode === 'create' || editingCustomer?.type === 'company'" class="space-y-1.5 sm:col-span-2">
                        <Label for="notes">ملاحظات</Label>
                        <Textarea id="notes" v-model="form.notes" rows="2" class="resize-none" />
                    </div>

                    <div class="flex gap-3 pt-2 sm:col-span-2">
                        <Button type="submit" class="flex-1" :disabled="form.processing">
                            {{ form.processing ? 'جاري الحفظ...' : (formMode === 'edit' ? 'حفظ التعديلات' : 'حفظ') }}
                        </Button>
                        <Button type="button" variant="outline" class="flex-1" @click="closeForm">
                            إلغاء
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
