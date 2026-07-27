<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    ArrowUpRight,
    ChevronLeft,
    ChevronRight,
    MoreVertical,
    Package,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { formatCurrency, formatDate, formatInteger } from '@/lib/formatNumber';

interface PackageProduct {
    id: number;
    product_name: string;
}

interface PackageItem {
    id: number;
    name: string;
    description: string | null;
    price: number | string;
    image: string | null;
    status: 'active' | 'inactive' | string;
    created_at: string;
    products: PackageProduct[];
}

type StatusTab = 'all' | 'active' | 'inactive';

interface Props {
    packages: {
        data: PackageItem[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        per_page: number;
    };
    filters?: {
        search?: string;
        status?: StatusTab;
        per_page?: number;
    };
    statusCounts?: Record<StatusTab, number>;
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({
        search: '',
        status: 'all',
        per_page: 15,
    }),
    statusCounts: () => ({
        all: 0,
        active: 0,
        inactive: 0,
    }),
});

defineOptions({ layout: AppLayout });

const searchInput = ref(props.filters?.search ?? '');
const statusFilter = ref<StatusTab>(props.filters?.status ?? 'all');
const perPage = ref(props.filters?.per_page || 15);
const selectedIds = ref<number[]>([]);

const statusTabs: { key: StatusTab; label: string }[] = [
    { key: 'all', label: 'الكل' },
    { key: 'active', label: 'نشط' },
    { key: 'inactive', label: 'غير نشط' },
];

const summaryCards = computed(() => [
    {
        key: 'all' as const,
        label: 'إجمالي الباقات',
        value: props.statusCounts.all,
        unit: 'باقة',
        hint: 'عرض كل الباقات',
    },
    {
        key: 'active' as const,
        label: 'نشطة',
        value: props.statusCounts.active,
        unit: 'باقة',
        hint: 'عرض الباقات النشطة',
    },
    {
        key: 'inactive' as const,
        label: 'غير نشطة',
        value: props.statusCounts.inactive,
        unit: 'باقة',
        hint: 'عرض الباقات غير النشطة',
    },
]);

const pageNumbers = computed(() => {
    const total = props.packages.last_page;
    const current = props.packages.current_page;
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
        props.packages.data.length > 0
        && props.packages.data.every((pkg) => selectedIds.value.includes(pkg.id)),
);

watch(
    () => props.filters,
    (filters) => {
        searchInput.value = filters?.search ?? '';
        statusFilter.value = filters?.status ?? 'all';
        perPage.value = filters?.per_page || 15;
        selectedIds.value = [];
    },
);

function applyFilters(pageNum = 1) {
    router.get(
        route('packages.index'),
        {
            search: searchInput.value.trim() || undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            per_page: perPage.value !== 15 ? perPage.value : undefined,
            page: pageNum > 1 ? pageNum : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function setStatusFilter(status: StatusTab) {
    statusFilter.value = status;
    applyFilters(1);
}

function onSearchSubmit() {
    applyFilters(1);
}

function goToPage(pageNum: number) {
    if (pageNum >= 1 && pageNum <= props.packages.last_page) {
        applyFilters(pageNum);
    }
}

function tabCount(tab: StatusTab): number {
    return props.statusCounts?.[tab] ?? 0;
}

function toggleSelectAll() {
    if (allVisibleSelected.value) {
        const visible = new Set(props.packages.data.map((pkg) => pkg.id));
        selectedIds.value = selectedIds.value.filter((id) => !visible.has(id));
        return;
    }

    selectedIds.value = Array.from(new Set([
        ...selectedIds.value,
        ...props.packages.data.map((pkg) => pkg.id),
    ]));
}

function toggleSelect(id: number) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((item) => item !== id);
        return;
    }
    selectedIds.value = [...selectedIds.value, id];
}

function statusLabel(status: string): string {
    return status === 'active' ? 'نشط' : status === 'inactive' ? 'غير نشط' : status;
}

function statusBadgeClass(status: string): string {
    if (status === 'active') {
        return 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50';
    }
    return 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700';
}

function truncateDescription(description: string | null): string {
    if (!description) return '—';
    return description.length > 60 ? `${description.substring(0, 60)}...` : description;
}

function deletePackage(pkg: PackageItem) {
    if (confirm('هل تريد حذف هذه الباقة؟')) {
        router.delete(route('packages.destroy', pkg.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="الباقات" />

    <div class="flex min-w-0 flex-1 flex-col gap-5 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">
                    <Package class="size-6 text-blue-600" />
                    الباقات
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
                    عرض وبحث وفلترة الباقات، أو إضافة باقة جديدة
                </p>
            </div>
            <Link
                href="/packages/create"
                class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 sm:h-11"
            >
                <Plus class="size-4" />
                إضافة باقة
            </Link>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3">
            <button
                v-for="card in summaryCards"
                :key="card.key"
                type="button"
                class="group flex min-w-0 flex-col rounded-2xl border bg-white p-5 text-start transition hover:border-gray-300 hover:shadow-sm active:scale-[0.99] dark:bg-neutral-900 dark:hover:border-neutral-600 sm:p-6"
                :class="
                    statusFilter === card.key
                        ? 'border-blue-300 ring-1 ring-blue-100 dark:border-blue-800 dark:ring-blue-950'
                        : 'border-[#E0E0E0] dark:border-neutral-700'
                "
                @click="setStatusFilter(card.key)"
            >
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-gray-400 dark:text-neutral-500 sm:text-xs">
                    {{ card.label }}
                </p>
                <p class="mt-3 text-2xl font-extrabold tabular-nums tracking-tight text-gray-900 dark:text-white sm:text-[1.75rem]">
                    {{ formatInteger(card.value) }}
                    <span class="ms-1 text-base font-bold text-gray-700 dark:text-neutral-300 sm:text-lg">{{ card.unit }}</span>
                </p>
                <p class="mt-4 flex items-center gap-1.5 text-xs font-medium text-[#5B8A72] dark:text-teal-400/90">
                    <ArrowUpRight class="size-3.5 shrink-0 stroke-[2.25]" />
                    <span>{{ card.hint }}</span>
                </p>
            </button>
        </div>

        <div class="overflow-x-auto">
            <div class="flex min-w-max items-center gap-1 border-b border-gray-200 dark:border-neutral-700">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.key"
                    type="button"
                    class="relative px-3 py-2.5 text-sm font-medium transition-colors sm:px-4"
                    :class="
                        statusFilter === tab.key
                            ? 'text-blue-700 dark:text-blue-300'
                            : 'text-gray-500 hover:text-gray-800 dark:text-neutral-400 dark:hover:text-neutral-200'
                    "
                    @click="setStatusFilter(tab.key)"
                >
                    {{ tab.label }}
                    <span class="ms-1.5 text-xs tabular-nums text-gray-400">({{ formatInteger(tabCount(tab.key)) }})</span>
                    <span
                        v-if="statusFilter === tab.key"
                        class="absolute inset-x-0 -bottom-px h-0.5 rounded-full bg-blue-600"
                    />
                </button>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
            <div class="flex flex-col gap-3 border-b border-gray-100 p-4 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between">
                <form class="w-full max-w-sm" @submit.prevent="onSearchSubmit">
                    <label class="flex h-10 items-center gap-2 rounded-full border border-transparent bg-gray-100 px-3.5 text-gray-400 transition focus-within:border-blue-300 focus-within:bg-white focus-within:ring-2 focus-within:ring-blue-100 dark:bg-neutral-800 dark:focus-within:border-blue-700 dark:focus-within:bg-neutral-950 dark:focus-within:ring-blue-950">
                        <Search class="size-4 shrink-0 stroke-[1.75]" />
                        <input
                            v-model="searchInput"
                            type="search"
                            placeholder="ابحث عن باقة..."
                            class="w-full bg-transparent text-sm text-gray-800 outline-none placeholder:text-gray-400 dark:text-neutral-100"
                        />
                    </label>
                </form>

                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-neutral-400">
                    <span>عرض</span>
                    <select
                        v-model.number="perPage"
                        class="h-8 rounded-md border border-gray-200 bg-white px-2 text-sm font-semibold text-gray-800 outline-none dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
                        @change="applyFilters(1)"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                    <span>من {{ formatInteger(packages.total) }} نتيجة</span>
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
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الباقة</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">المنتجات</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الوصف</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">السعر</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الحالة</th>
                            <th class="px-4 py-3.5 text-end text-[13px] font-semibold text-gray-700 dark:text-neutral-200" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="packages.data.length === 0">
                            <td colspan="7" class="px-4 py-16 text-center text-gray-500 dark:text-neutral-400">
                                لا توجد باقات مطابقة للبحث أو الفلتر الحالي.
                            </td>
                        </tr>
                        <tr
                            v-for="pkg in packages.data"
                            :key="pkg.id"
                            class="border-b border-gray-100 transition hover:bg-gray-50/70 dark:border-neutral-800 dark:hover:bg-neutral-800/40"
                        >
                            <td class="px-4 py-4">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    :checked="selectedIds.includes(pkg.id)"
                                    @change="toggleSelect(pkg.id)"
                                />
                            </td>
                            <td class="px-3 py-4">
                                <div class="flex items-start gap-3">
                                    <img
                                        v-if="pkg.image"
                                        :src="`/storage/${pkg.image}`"
                                        :alt="pkg.name"
                                        class="size-11 shrink-0 rounded-xl object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-[10px] font-medium text-gray-400 dark:bg-neutral-800"
                                    >
                                        بلا صورة
                                    </div>
                                    <div class="flex min-w-0 flex-col items-start gap-0.5">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ pkg.name }}</p>
                                        <p class="text-xs text-gray-400">
                                            #{{ formatInteger(pkg.id) }} · أُنشئت في:
                                            <span dir="ltr">{{ formatDate(pkg.created_at) }}</span>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-4">
                                <div v-if="pkg.products?.length" class="flex max-w-[220px] flex-wrap gap-1">
                                    <span
                                        v-for="product in pkg.products.slice(0, 3)"
                                        :key="product.id"
                                        class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-neutral-800 dark:text-neutral-300"
                                    >
                                        {{ product.product_name }}
                                    </span>
                                    <span
                                        v-if="pkg.products.length > 3"
                                        class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"
                                    >
                                        +{{ formatInteger(pkg.products.length - 3) }}
                                    </span>
                                </div>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-3 py-4 max-w-[240px] text-gray-600 dark:text-neutral-300">
                                {{ truncateDescription(pkg.description) }}
                            </td>
                            <td class="px-3 py-4 font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                {{ formatCurrency(Number(pkg.price) || 0) }}
                            </td>
                            <td class="px-3 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="statusBadgeClass(pkg.status)"
                                >
                                    {{ statusLabel(pkg.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex justify-end">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                type="button"
                                                class="inline-flex size-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                                            >
                                                <MoreVertical class="size-4" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="min-w-40">
                                            <DropdownMenuItem as-child>
                                                <Link :href="route('packages.edit', pkg.id)" class="gap-2">
                                                    <Pencil class="size-4" />
                                                    تعديل
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                class="gap-2 text-red-600 focus:text-red-600"
                                                @click="deletePackage(pkg)"
                                            >
                                                <Trash2 class="size-4" />
                                                حذف
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-100 px-4 py-4 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500 dark:text-neutral-400">
                    عرض {{ formatInteger(packages.from ?? 0) }} - {{ formatInteger(packages.to ?? 0) }} من {{ formatInteger(packages.total) }}
                </p>

                <div v-if="packages.last_page > 1" class="flex items-center justify-center gap-1.5 sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 disabled:opacity-40 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800"
                        :disabled="packages.current_page <= 1"
                        @click="goToPage(packages.current_page - 1)"
                    >
                        <ChevronRight class="size-4" />
                    </button>

                    <template v-for="(item, index) in pageNumbers" :key="`${item}-${index}`">
                        <span v-if="item === 'ellipsis'" class="px-1 text-gray-400">...</span>
                        <button
                            v-else
                            type="button"
                            class="inline-flex size-8 items-center justify-center rounded-lg text-sm font-medium transition"
                            :class="
                                packages.current_page === item
                                    ? 'bg-gray-100 text-gray-900 dark:bg-neutral-700 dark:text-white'
                                    : 'text-gray-500 hover:bg-gray-50 dark:text-neutral-300 dark:hover:bg-neutral-800'
                            "
                            @click="goToPage(item)"
                        >
                            {{ item }}
                        </button>
                    </template>

                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 disabled:opacity-40 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800"
                        :disabled="packages.current_page >= packages.last_page"
                        @click="goToPage(packages.current_page + 1)"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
