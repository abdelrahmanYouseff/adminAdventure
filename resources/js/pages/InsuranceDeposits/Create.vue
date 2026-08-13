<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDate, formatDateTime } from '@/lib/formatNumber';
import { ArrowRight, Search, ShieldPlus } from 'lucide-vue-next';
import Swal from 'sweetalert2';

interface Candidate {
    id: number;
    order_number: string;
    invoice_number: string | null;
    customer_name: string;
    customer_phone: string | null;
    insurance_amount: number;
    activity_date: string | null;
    warehouse_returned_at: string | null;
    warehouse_returned_by_name: string | null;
}

interface PaginatedCandidates {
    data: Candidate[];
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    candidates: PaginatedCandidates;
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string } | undefined) ?? {});

const searchQuery = ref(props.filters.search || '');
const selectedIds = ref<number[]>([]);

const form = useForm({
    order_ids: [] as number[],
});

const allVisibleSelected = computed(() => {
    const ids = props.candidates.data.map((c) => c.id);
    return ids.length > 0 && ids.every((id) => selectedIds.value.includes(id));
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

function applySearch() {
    router.get(
        '/insurance-deposits/create',
        { search: searchQuery.value || undefined },
        { preserveState: true, replace: true },
    );
}

function toggleOne(id: number) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
        return;
    }

    selectedIds.value = [...selectedIds.value, id];
}

function toggleAllVisible() {
    const ids = props.candidates.data.map((c) => c.id);

    if (allVisibleSelected.value) {
        selectedIds.value = selectedIds.value.filter((id) => !ids.includes(id));
        return;
    }

    selectedIds.value = [...new Set([...selectedIds.value, ...ids])];
}

function submit() {
    if (selectedIds.value.length === 0) {
        Swal.fire({
            icon: 'info',
            title: 'لم يتم الاختيار',
            text: 'اختر عميلاً واحداً على الأقل ممن أُغلق استرجاعهم واعتمد.',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#2563EB',
        });
        return;
    }

    form.order_ids = [...selectedIds.value];
    form.post('/insurance-deposits', {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
        },
    });
}
</script>

<template>
    <Head title="إنشاء طلب استرداد تأمين" />

    <div class="py-8 sm:py-12" dir="rtl">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link
                        href="/insurance-deposits"
                        class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800"
                    >
                        <ArrowRight class="h-4 w-4" />
                        العودة لقائمة الاسترداد
                    </Link>
                    <h1 class="text-2xl font-bold text-slate-900">إنشاء طلب استرداد تأمين</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        يظهر فقط العملاء الذين أُغلق استرجاعهم واعتمد (تعميد أمر العمل + تأكيد المستودع). اختر من تريد إدراجهم في مسار الاسترداد.
                    </p>
                </div>
                <div class="inline-flex items-center gap-2 rounded-2xl bg-slate-50 px-4 py-3 text-slate-700 ring-1 ring-slate-200">
                    <ShieldPlus class="h-5 w-5" />
                    <div>
                        <p class="text-xs">المحددون</p>
                        <p class="text-lg font-bold tabular-nums">{{ selectedIds.length }}</p>
                    </div>
                </div>
            </div>

            <div class="mb-4 flex flex-wrap gap-2">
                <div class="relative min-w-[220px] flex-1">
                    <Search class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="بحث برقم الطلب أو اسم العميل أو الجوال..."
                        class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pr-10 pl-3 text-sm outline-none ring-slate-200 focus:ring-2"
                        @keydown.enter.prevent="applySearch"
                    />
                </div>
                <Button type="button" variant="outline" class="rounded-xl" @click="applySearch">
                    بحث
                </Button>
                <Button
                    type="button"
                    class="rounded-xl bg-emerald-600 hover:bg-emerald-700"
                    :disabled="form.processing || selectedIds.length === 0"
                    @click="submit"
                >
                    إنشاء الطلب ({{ selectedIds.length }})
                </Button>
            </div>

            <p v-if="form.errors.order_ids" class="mb-3 text-sm text-rose-600">{{ form.errors.order_ids }}</p>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-slate-600">
                                <th class="px-4 py-3 text-right font-semibold">
                                    <label class="inline-flex cursor-pointer items-center gap-2">
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                            :checked="allVisibleSelected"
                                            @change="toggleAllVisible"
                                        />
                                        اختيار
                                    </label>
                                </th>
                                <th class="px-4 py-3 text-right font-semibold">رقم الطلب</th>
                                <th class="px-4 py-3 text-right font-semibold">العميل</th>
                                <th class="px-4 py-3 text-right font-semibold">مبلغ التأمين</th>
                                <th class="px-4 py-3 text-right font-semibold">تاريخ الاسترجاع</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="candidates.data.length === 0">
                                <td colspan="5" class="px-4 py-12 text-center text-slate-500">
                                    لا يوجد عملاء باسترجاع مغلق ومعتمد بانتظار إنشاء طلب استرداد.
                                </td>
                            </tr>
                            <tr
                                v-for="item in candidates.data"
                                :key="item.id"
                                class="border-t border-slate-100 hover:bg-slate-50/70"
                                :class="selectedIds.includes(item.id) ? 'bg-emerald-50/40' : ''"
                            >
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                        :checked="selectedIds.includes(item.id)"
                                        @change="toggleOne(item.id)"
                                    />
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-900">
                                    {{ item.order_number }}
                                    <p v-if="item.invoice_number" class="text-xs font-normal text-slate-400">
                                        فاتورة {{ item.invoice_number }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ item.customer_name }}</p>
                                    <p v-if="item.customer_phone" class="text-xs text-slate-400" dir="ltr">
                                        {{ item.customer_phone }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 tabular-nums font-semibold text-slate-900">
                                    {{ formatCurrency(item.insurance_amount) }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>{{ item.warehouse_returned_at ? formatDateTime(item.warehouse_returned_at) : '—' }}</p>
                                    <p v-if="item.warehouse_returned_by_name" class="text-xs text-slate-400">
                                        بواسطة {{ item.warehouse_returned_by_name }}
                                    </p>
                                    <p v-if="item.activity_date" class="text-xs text-slate-400">
                                        النشاط {{ formatDate(item.activity_date) }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="candidates.links.length > 3"
                    class="flex flex-wrap items-center justify-center gap-1 border-t border-slate-100 px-4 py-3"
                >
                    <Link
                        v-for="(link, idx) in candidates.links"
                        :key="idx"
                        :href="link.url || '#'"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium"
                        :class="link.active
                            ? 'bg-slate-900 text-white'
                            : link.url
                                ? 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
                                : 'pointer-events-none text-slate-300'"
                        v-html="link.label"
                        :preserve-state="true"
                    />
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <Button
                    type="button"
                    class="rounded-xl bg-emerald-600 hover:bg-emerald-700"
                    :disabled="form.processing || selectedIds.length === 0"
                    @click="submit"
                >
                    إنشاء طلب الاسترداد ({{ selectedIds.length }})
                </Button>
            </div>
        </div>
    </div>
</template>
