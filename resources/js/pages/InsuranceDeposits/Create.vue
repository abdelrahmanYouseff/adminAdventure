<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/formatNumber';
import { ArrowRight, FileText, ShieldPlus, UploadCloud } from 'lucide-vue-next';
import Swal from 'sweetalert2';

interface CustomerOption {
    id: number;
    order_number: string;
    customer_name: string;
    customer_phone: string | null;
    insurance_amount: number;
    remaining_amount: number;
    label: string;
}

interface Props {
    customers: CustomerOption[];
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string } | undefined) ?? {});

const customerFilter = ref('');

const PAYMENT_PROOF_ACCEPT = 'image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf';
const paymentProofPreviews = ref<string[]>([]);

const form = useForm({
    order_id: '' as number | '',
    insurance_amount: 0 as number,
    payment_proof: [] as File[],
});

const filteredCustomers = computed(() => {
    const q = customerFilter.value.trim().toLowerCase();
    if (!q) {
        return props.customers;
    }

    return props.customers.filter((row) => {
        return (
            row.customer_name.toLowerCase().includes(q)
            || row.order_number.toLowerCase().includes(q)
            || (row.customer_phone || '').includes(q)
        );
    });
});

const selected = computed(() => {
    if (form.order_id === '' || form.order_id == null) {
        return null;
    }

    return props.customers.find((row) => row.id === Number(form.order_id)) ?? null;
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

watch(selected, (row) => {
    if (!row) {
        return;
    }

    if (!form.insurance_amount || form.insurance_amount <= 0) {
        form.insurance_amount = row.insurance_amount > 0 ? row.insurance_amount : 0;
    }
});

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
    clearPaymentProofPreview();
});

function submit() {
    if (!form.order_id) {
        Swal.fire({
            icon: 'info',
            title: 'اختر العميل',
            text: 'اختر العميل / رقم الطلب من القائمة أولاً.',
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
                    اختر العميل، أدخل المبلغ، وأرفق إيصال الدفع. بدون الإيصال الطلب مش هيترفع. يفضل في نفس الصفحة بانتظار مدير العمال.
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
                    <div class="space-y-2">
                        <Label for="customer_filter" class="text-sm font-medium">بحث سريع</Label>
                        <Input
                            id="customer_filter"
                            v-model="customerFilter"
                            type="search"
                            class="h-11 rounded-xl"
                            placeholder="ابحث بالاسم أو رقم الطلب أو الجوال..."
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="order_id" class="text-sm font-medium">العميل / الطلب</Label>
                        <select
                            id="order_id"
                            v-model="form.order_id"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none ring-slate-200 focus:ring-2"
                        >
                            <option value="">اختر العميل...</option>
                            <option
                                v-for="row in filteredCustomers"
                                :key="row.id"
                                :value="row.id"
                            >
                                {{ row.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.order_id" class="text-xs text-rose-600">{{ form.errors.order_id }}</p>
                        <p v-if="filteredCustomers.length === 0" class="text-xs text-amber-700">
                            لا توجد نتائج مطابقة للبحث.
                        </p>
                    </div>

                    <div
                        v-if="selected"
                        class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-slate-100"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold text-slate-900">{{ selected.customer_name }}</span>
                            <span class="font-semibold tabular-nums text-slate-900" dir="ltr">{{ selected.order_number }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                            <span v-if="selected.customer_phone" dir="ltr">{{ selected.customer_phone }}</span>
                            <span>تأمين مسجّل: {{ formatCurrency(selected.insurance_amount) }}</span>
                            <span>متبقي على الطلب: {{ formatCurrency(selected.remaining_amount) }}</span>
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
