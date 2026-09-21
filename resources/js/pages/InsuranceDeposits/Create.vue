<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/formatNumber';
import { ArrowRight, ChevronDown, FileText, Search, ShieldPlus, UploadCloud } from 'lucide-vue-next';
import Swal from 'sweetalert2';

interface InvoiceOption {
    id: number;
    order_id: number | null;
    invoice_number: string;
    customer_name: string;
    customer_phone: string | null;
    invoice_amount: number;
    insurance_amount: number;
}

interface Props {
    invoices: InvoiceOption[];
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string } | undefined) ?? {});

const invoiceQuery = ref('');
const dropdownOpen = ref(false);
const searching = ref(false);
const remoteInvoices = ref<InvoiceOption[]>([...props.invoices]);
const selectedInvoice = ref<InvoiceOption | null>(null);
const comboboxRef = ref<HTMLElement | null>(null);
const activeIndex = ref(0);
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let searchAbort: AbortController | null = null;

const PAYMENT_PROOF_ACCEPT = 'image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf';
const paymentProofPreviews = ref<string[]>([]);

const form = useForm({
    invoice_id: '' as number | '',
    insurance_amount: 0 as number,
    payment_proof: [] as File[],
});

const selected = computed(() => selectedInvoice.value);

onClickOutside(comboboxRef, () => {
    dropdownOpen.value = false;
});

watch(
    () => [flash.value.success, flash.value.error] as const,
    ([success, error]) => {
        if (success) {
            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح',
                text: success,
                confirmButtonText: 'حسناً',
                confirmButtonColor: '#2563EB',
                timer: 3200,
                timerProgressBar: true,
            });
            return;
        }

        if (error) {
            Swal.fire({
                icon: 'error',
                title: 'تعذر الإجراء',
                text: error,
                confirmButtonText: 'حسناً',
                confirmButtonColor: '#2563EB',
            });
        }
    },
    { immediate: true },
);

watch(selectedInvoice, (row) => {
    form.invoice_id = row?.id ?? '';

    if (!row) {
        return;
    }

    if (!form.insurance_amount || form.insurance_amount <= 0) {
        form.insurance_amount = row.insurance_amount > 0 ? row.insurance_amount : 0;
    }
});

function matchesQuery(row: InvoiceOption, q: string): boolean {
    if (!q) {
        return true;
    }

    return (
        row.invoice_number.toLowerCase().includes(q)
        || row.customer_name.toLowerCase().includes(q)
        || (row.customer_phone || '').includes(q)
        || String(row.invoice_amount).includes(q)
    );
}

const visibleInvoices = computed(() => {
    const q = invoiceQuery.value.trim().toLowerCase();
    const source = remoteInvoices.value.length ? remoteInvoices.value : props.invoices;

    return source.filter((row) => matchesQuery(row, q)).slice(0, 12);
});

async function fetchInvoices(search: string) {
    searchAbort?.abort();
    searchAbort = new AbortController();
    searching.value = true;

    try {
        const params = new URLSearchParams();
        if (search.trim() !== '') {
            params.set('search', search.trim());
        }

        const query = params.toString();
        const res = await fetch(`/insurance-deposits/invoices${query ? `?${query}` : ''}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: searchAbort.signal,
        });

        if (!res.ok) {
            return;
        }

        const data = await res.json() as { invoices?: InvoiceOption[] };
        remoteInvoices.value = data.invoices ?? [];
        activeIndex.value = 0;
    } catch (error) {
        if ((error as { name?: string }).name !== 'AbortError') {
            remoteInvoices.value = props.invoices.filter((row) => matchesQuery(row, search.trim().toLowerCase()));
        }
    } finally {
        searching.value = false;
    }
}

function scheduleSearch(value: string) {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        void fetchInvoices(value);
    }, 120);
}

function openDropdown() {
    dropdownOpen.value = true;
    activeIndex.value = 0;
}

function onInvoiceInput() {
    const current = selectedInvoice.value;
    const q = invoiceQuery.value.trim();

    if (current && q && !matchesQuery(current, q.toLowerCase()) && current.invoice_number !== q) {
        selectedInvoice.value = null;
        form.invoice_id = '';
    }

    openDropdown();
    scheduleSearch(invoiceQuery.value);
}

function selectInvoice(row: InvoiceOption) {
    selectedInvoice.value = row;
    invoiceQuery.value = `${row.invoice_number} — ${row.customer_name}`;
    dropdownOpen.value = false;
}

function onInvoiceKeydown(event: KeyboardEvent) {
    if (!dropdownOpen.value && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        openDropdown();
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = Math.min(activeIndex.value + 1, Math.max(visibleInvoices.value.length - 1, 0));
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
        return;
    }

    if (event.key === 'Enter') {
        const row = visibleInvoices.value[activeIndex.value];
        if (row) {
            event.preventDefault();
            selectInvoice(row);
        }
        return;
    }

    if (event.key === 'Escape') {
        dropdownOpen.value = false;
    }
}

function isPdfFile(file: File | undefined): boolean {
    if (!file) {
        return false;
    }

    return file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
}

function paymentProofSelectedLabel(count: number): string {
    if (count <= 0) {
        return 'اضغط لاختيار إيصال الدفع';
    }

    return count === 1 ? 'تم اختيار ملف واحد' : `تم اختيار ${count} ملفات`;
}

function clearPaymentProofPreview() {
    paymentProofPreviews.value.forEach((url) => URL.revokeObjectURL(url));
    paymentProofPreviews.value = [];
}

function handlePaymentProofChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    if (!files.length) {
        return;
    }

    const nextFiles = [...form.payment_proof, ...files].slice(0, 10);
    clearPaymentProofPreview();
    form.payment_proof = nextFiles;
    paymentProofPreviews.value = nextFiles.map((file) => URL.createObjectURL(file));
    form.clearErrors('payment_proof');
    input.value = '';
}

function removePaymentProof(index: number) {
    const nextFiles = form.payment_proof.filter((_, i) => i !== index);
    clearPaymentProofPreview();
    form.payment_proof = nextFiles;
    paymentProofPreviews.value = nextFiles.map((file) => URL.createObjectURL(file));
}

onBeforeUnmount(() => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
    searchAbort?.abort();
    clearPaymentProofPreview();
});

function submit() {
    if (!form.invoice_id) {
        Swal.fire({
            icon: 'info',
            title: 'اختر الفاتورة',
            text: 'اختر فاتورة العميل من القائمة أولاً.',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#2563EB',
        });
        return;
    }

    if (Number(form.insurance_amount) <= 0) {
        Swal.fire({
            icon: 'info',
            title: 'مبلغ التأمين',
            text: 'أدخل مبلغ استرداد التأمين.',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#2563EB',
        });
        return;
    }

    if (form.payment_proof.length === 0) {
        Swal.fire({
            icon: 'info',
            title: 'إيصال الدفع',
            text: 'لازم ترفق إيصال الدفع قبل رفع الطلب.',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#2563EB',
        });
        return;
    }

    form.post('/insurance-deposits', {
        preserveScroll: true,
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="رفع طلب استرداد التأمين" />

    <div class="py-8 sm:py-12" dir="rtl">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <Link
                    href="/insurance-deposits"
                    class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800"
                >
                    <ArrowRight class="h-4 w-4" />
                    العودة لقائمة الاسترداد
                </Link>
                <h1 class="text-2xl font-bold text-slate-900">رفع طلب استرداد التأمين</h1>
                    <p class="mt-1 text-sm text-slate-500">
                    اختر فاتورة العميل، أدخل المبلغ، وأرفق إيصال الدفع. بدون الإيصال الطلب مش هيترفع. يفضل في نفس الصفحة بانتظار مدير العمال.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-5 flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3 text-emerald-800 ring-1 ring-emerald-100">
                    <ShieldPlus class="h-5 w-5 shrink-0" />
                    <p class="text-sm">
                        بعد الإرسال يبقى الطلب هنا بانتظار اعتماد مدير العمال، ثم المحاسب، ثم الادمن، ثم المحاسب لاعتماد التحويل.
                    </p>
                </div>

                <div class="space-y-5">
                    <div ref="comboboxRef" class="relative space-y-2">
                        <Label for="invoice_filter" class="text-sm font-medium">بحث سريع عن الفاتورة</Label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                id="invoice_filter"
                                v-model="invoiceQuery"
                                type="text"
                                autocomplete="off"
                                class="h-11 rounded-xl pr-10 pl-10"
                                placeholder="اكتب رقم الفاتورة أو اسم العميل..."
                                @focus="openDropdown(); scheduleSearch(invoiceQuery)"
                                @input="onInvoiceInput"
                                @keydown="onInvoiceKeydown"
                            />
                            <ChevronDown class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        </div>

                        <div
                            v-if="dropdownOpen"
                            class="absolute z-30 mt-1 max-h-80 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                        >
                            <p v-if="searching && visibleInvoices.length === 0" class="px-3 py-3 text-sm text-slate-500">
                                جاري البحث...
                            </p>
                            <p v-else-if="visibleInvoices.length === 0" class="px-3 py-3 text-sm text-amber-700">
                                لا توجد فواتير مطابقة.
                            </p>
                            <button
                                v-for="(row, index) in visibleInvoices"
                                :key="row.id"
                                type="button"
                                class="flex w-full flex-col gap-0.5 px-3 py-2.5 text-right transition"
                                :class="index === activeIndex ? 'bg-sky-50' : 'hover:bg-slate-50'"
                                @mousedown.prevent="selectInvoice(row)"
                                @mouseenter="activeIndex = index"
                            >
                                <span class="font-semibold tabular-nums text-slate-900" dir="ltr">{{ row.invoice_number }}</span>
                                <span class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                                    <span class="font-medium text-slate-700">{{ row.customer_name }}</span>
                                    <span class="tabular-nums font-semibold text-slate-800">{{ formatCurrency(row.invoice_amount) }}</span>
                                </span>
                            </button>
                        </div>
                        <p v-if="form.errors.invoice_id" class="text-xs text-rose-600">{{ form.errors.invoice_id }}</p>
                    </div>

                    <div
                        v-if="selected"
                        class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-slate-100"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold text-slate-900">{{ selected.customer_name }}</span>
                            <span class="font-semibold tabular-nums text-slate-900" dir="ltr">{{ selected.invoice_number }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                            <span v-if="selected.customer_phone" dir="ltr">{{ selected.customer_phone }}</span>
                            <span>مبلغ الفاتورة: {{ formatCurrency(selected.invoice_amount) }}</span>
                            <span>تأمين مسجّل: {{ formatCurrency(selected.insurance_amount) }}</span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="insurance_amount" class="text-sm font-medium">مبلغ استرداد التأمين</Label>
                        <Input
                            id="insurance_amount"
                            v-model.number="form.insurance_amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="h-11 rounded-xl tabular-nums"
                            dir="ltr"
                            placeholder="0.00"
                        />
                        <p class="text-xs text-slate-500">
                            الطلب يظهر مباشرة في قائمة استرداد التأمين بانتظار اعتماد مدير العمال. لا يُرسل لسندات القبض.
                        </p>
                        <p v-if="form.errors.insurance_amount" class="text-xs text-rose-600">
                            {{ form.errors.insurance_amount }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="payment_proof" class="text-sm font-medium">إيصال الدفع</Label>
                        <p class="text-xs text-slate-500">
                            إرفاق الإيصال إلزامي. صورة أو PDF، حتى 5 ميجابايت، بحد أقصى 10 ملفات.
                        </p>
                        <label
                            for="payment_proof"
                            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center transition hover:border-emerald-400 hover:bg-emerald-50/40"
                        >
                            <UploadCloud class="h-6 w-6 text-slate-500" />
                            <span class="text-sm font-medium text-slate-800">
                                {{ paymentProofSelectedLabel(form.payment_proof.length) }}
                            </span>
                        </label>
                        <input
                            id="payment_proof"
                            type="file"
                            class="sr-only"
                            :accept="PAYMENT_PROOF_ACCEPT"
                            multiple
                            @change="handlePaymentProofChange"
                        />
                        <div v-if="paymentProofPreviews.length" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <div
                                v-for="(preview, index) in paymentProofPreviews"
                                :key="`${preview}-${index}`"
                                class="relative overflow-hidden rounded-xl border border-slate-200"
                            >
                                <span
                                    v-if="isPdfFile(form.payment_proof[index])"
                                    class="flex aspect-square w-full flex-col items-center justify-center gap-1 bg-slate-50 px-2 text-center"
                                >
                                    <FileText class="h-7 w-7 text-rose-600" />
                                    <span class="line-clamp-2 px-1 text-[11px] font-medium">
                                        {{ form.payment_proof[index]?.name || 'ملف PDF' }}
                                    </span>
                                </span>
                                <img
                                    v-else
                                    :src="preview"
                                    :alt="`معاينة إيصال ${index + 1}`"
                                    class="aspect-square w-full object-cover"
                                />
                                <button
                                    type="button"
                                    class="absolute left-1.5 top-1.5 rounded-lg bg-white/90 px-2 py-1 text-[11px] font-semibold text-slate-700 shadow"
                                    @click="removePaymentProof(index)"
                                >
                                    إزالة
                                </button>
                            </div>
                        </div>
                        <p v-if="form.errors.payment_proof" class="text-xs text-rose-600">
                            {{ form.errors.payment_proof }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2">
                        <Button
                            type="button"
                            class="h-11 rounded-xl bg-emerald-600 px-6 hover:bg-emerald-700"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            {{ form.processing ? 'جاري الإرسال...' : 'رفع الطلب' }}
                        </Button>
                        <Button as-child type="button" variant="outline" class="h-11 rounded-xl">
                            <Link href="/insurance-deposits">إلغاء</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
