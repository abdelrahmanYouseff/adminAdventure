<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Filter,
    Mail,
    Pencil,
    Phone,
    Plus,
    Search,
    Trash2,
    Users,
    X,
    Eye,
    EyeOff,
} from 'lucide-vue-next';
import { formatDate, formatInteger } from '@/lib/formatNumber';
import type { StaffRole } from '@/types';

interface User {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    country: string | null;
    address: string | null;
    role: StaffRole;
    created_at: string;
}

type RoleTab = 'all' | StaffRole;
type DateFilter = 'all' | '7' | '30';

const roleLabels: Record<StaffRole, string> = {
    admin: 'ادمن',
    general_manager: 'مدير عام',
    manager: 'مسئول',
    accounts: 'حسابات',
    workers_manager: 'مدير العمال',
    worker: 'عامل',
    warehouse_keeper: 'أمين مستودع',
};

const roleBadgeClass: Record<StaffRole, string> = {
    admin: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-100 dark:bg-blue-950/40 dark:text-blue-300 dark:ring-blue-900/50',
    general_manager: 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-300 dark:ring-indigo-900/50',
    manager: 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-100 dark:bg-violet-950/40 dark:text-violet-300 dark:ring-violet-900/50',
    accounts: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50',
    workers_manager: 'bg-cyan-50 text-cyan-700 ring-1 ring-inset ring-cyan-100 dark:bg-cyan-950/40 dark:text-cyan-300 dark:ring-cyan-900/50',
    worker: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/50',
    warehouse_keeper: 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-100 dark:bg-orange-950/40 dark:text-orange-300 dark:ring-orange-900/50',
};

const roleTabs: { key: RoleTab; label: string }[] = [
    { key: 'all', label: 'الكل' },
    { key: 'admin', label: 'ادمن' },
    { key: 'general_manager', label: 'مدير عام' },
    { key: 'manager', label: 'مسئول' },
    { key: 'accounts', label: 'حسابات' },
    { key: 'workers_manager', label: 'مدير العمال' },
    { key: 'warehouse_keeper', label: 'أمين مستودع' },
    { key: 'worker', label: 'عامل' },
];

interface Props {
    users: User[];
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);
const errorMessage = computed(() => page.props.flash?.error as string | undefined);

const activeTab = ref<RoleTab>('all');
const searchQuery = ref('');
const dateFilter = ref<DateFilter>('all');
const showFilters = ref(false);
const currentPage = ref(1);
const perPage = 10;
const selectedIds = ref<number[]>([]);

const showModal = ref(false);
const editingUser = ref<User | null>(null);
const showPassword = ref(false);
const showConfirm = ref(false);
const isEditing = computed(() => editingUser.value !== null);

const form = useForm({
    customer_name: '',
    email: '',
    phone: '',
    country: '',
    role: 'worker' as StaffRole,
    password: '',
    password_confirmation: '',
});

const filteredUsers = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const now = Date.now();

    return props.users.filter((user) => {
        if (activeTab.value !== 'all' && user.role !== activeTab.value) {
            return false;
        }

        if (dateFilter.value !== 'all') {
            const days = Number(dateFilter.value);
            const created = new Date(user.created_at).getTime();
            if (Number.isNaN(created) || now - created > days * 24 * 60 * 60 * 1000) {
                return false;
            }
        }

        if (!query) {
            return true;
        }

        return [
            user.name,
            user.email,
            user.phone ?? '',
            user.country ?? '',
            roleLabels[user.role],
            String(user.id),
        ]
            .join(' ')
            .toLowerCase()
            .includes(query);
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredUsers.value.length / perPage)));

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * perPage;
    return filteredUsers.value.slice(start, start + perPage);
});

const showingFrom = computed(() => {
    if (filteredUsers.value.length === 0) {
        return 0;
    }
    return (currentPage.value - 1) * perPage + 1;
});

const showingTo = computed(() => Math.min(currentPage.value * perPage, filteredUsers.value.length));

const pageNumbers = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    const pages: Array<number | 'ellipsis'> = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);

    if (start > 2) {
        pages.push('ellipsis');
    }
    for (let i = start; i <= end; i += 1) {
        pages.push(i);
    }
    if (end < total - 1) {
        pages.push('ellipsis');
    }
    pages.push(total);
    return pages;
});

const allVisibleSelected = computed(
    () => paginatedUsers.value.length > 0 && paginatedUsers.value.every((user) => selectedIds.value.includes(user.id)),
);

watch([activeTab, searchQuery, dateFilter], () => {
    currentPage.value = 1;
    selectedIds.value = [];
});

watch(totalPages, (pages) => {
    if (currentPage.value > pages) {
        currentPage.value = pages;
    }
});

function tabCount(tab: RoleTab): number {
    if (tab === 'all') {
        return props.users.length;
    }
    return props.users.filter((user) => user.role === tab).length;
}

function toggleSelectAll() {
    if (allVisibleSelected.value) {
        const visibleIds = new Set(paginatedUsers.value.map((user) => user.id));
        selectedIds.value = selectedIds.value.filter((id) => !visibleIds.has(id));
        return;
    }

    const merged = new Set([...selectedIds.value, ...paginatedUsers.value.map((user) => user.id)]);
    selectedIds.value = Array.from(merged);
}

function toggleSelect(id: number) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((item) => item !== id);
        return;
    }
    selectedIds.value = [...selectedIds.value, id];
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

function openCreateModal() {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    form.role = 'worker';
    showPassword.value = false;
    showConfirm.value = false;
    showModal.value = true;
}

function openEditModal(user: User) {
    editingUser.value = user;
    form.clearErrors();
    form.customer_name = user.name;
    form.email = user.email;
    form.phone = user.phone || '';
    form.country = user.country || '';
    form.role = user.role;
    form.password = '';
    form.password_confirmation = '';
    showPassword.value = false;
    showConfirm.value = false;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingUser.value = null;
    form.reset();
    form.clearErrors();
}

function submitForm() {
    if (isEditing.value && editingUser.value) {
        form.put(route('users.update', editingUser.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
        return;
    }

    form.post(route('users.store'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
}

function deleteUser(user: User) {
    if (confirm(`هل أنت متأكد من حذف المستخدم "${user.name}"؟ لا يمكن التراجع عن هذا الإجراء.`)) {
        router.delete(route('users.destroy', user.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="المستخدمون" />

    <div class="flex min-w-0 flex-1 flex-col gap-5 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">
                    <Users class="size-6 text-blue-600" />
                    إدارة المستخدمين
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
                    عرض وإدارة حسابات فريق العمل حسب الدور
                </p>
            </div>
            <Button class="gap-2 self-start rounded-xl" @click="openCreateModal">
                <Plus class="size-4" />
                إضافة مستخدم
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
                    v-for="tab in roleTabs"
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
                    {{ dateFilterLabel(dateFilter) }} · {{ formatInteger(filteredUsers.length) }} نتيجة
                </p>
            </div>

            <div v-if="showFilters" class="border-b border-gray-100 px-4 py-3 dark:border-neutral-800">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="tab in roleTabs"
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
                <table class="w-full min-w-[920px] border-collapse text-sm">
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
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الاسم</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">البريد</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الهاتف</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">البلد</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الدور</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">تاريخ الإنشاء</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">المعرف</th>
                            <th class="px-4 py-3.5 text-end text-[13px] font-semibold text-gray-700 dark:text-neutral-200">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="paginatedUsers.length === 0">
                            <td colspan="9" class="px-4 py-16 text-center text-gray-500 dark:text-neutral-400">
                                لا يوجد مستخدمون مطابقون للبحث أو الفلتر الحالي.
                            </td>
                        </tr>
                        <tr
                            v-for="user in paginatedUsers"
                            :key="user.id"
                            class="border-b border-gray-100 transition hover:bg-gray-50/70 dark:border-neutral-800 dark:hover:bg-neutral-800/40"
                        >
                            <td class="px-4 py-4">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    :checked="selectedIds.includes(user.id)"
                                    @change="toggleSelect(user.id)"
                                />
                            </td>
                            <td class="px-3 py-4">
                                <span class="font-semibold text-gray-900 dark:text-white">{{ user.name }}</span>
                            </td>
                            <td class="px-3 py-4 text-gray-600 dark:text-neutral-300">
                                <div class="flex items-center gap-2" dir="ltr">
                                    <Mail class="size-3.5 shrink-0 text-gray-400" />
                                    <span class="truncate">{{ user.email }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-4 text-gray-600 dark:text-neutral-300">
                                <div class="flex items-center gap-2" dir="ltr">
                                    <Phone class="size-3.5 shrink-0 text-gray-400" />
                                    <span>{{ user.phone || '—' }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-4 text-gray-600 dark:text-neutral-300">
                                {{ user.country || '—' }}
                            </td>
                            <td class="px-3 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="roleBadgeClass[user.role]"
                                >
                                    {{ roleLabels[user.role] }}
                                </span>
                            </td>
                            <td class="px-3 py-4 tabular-nums text-gray-600 dark:text-neutral-300" dir="ltr">
                                {{ formatDate(user.created_at) }}
                            </td>
                            <td class="px-3 py-4 tabular-nums text-gray-500 dark:text-neutral-400" dir="ltr">
                                #{{ formatInteger(user.id) }}
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600 dark:border-neutral-700 dark:hover:border-blue-900 dark:hover:bg-blue-950/40 dark:hover:text-blue-300"
                                        title="تعديل"
                                        @click="openEditModal(user)"
                                    >
                                        <Pencil class="size-3.5 stroke-[1.75]" />
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-neutral-700 dark:hover:border-red-900 dark:hover:bg-red-950/40 dark:hover:text-red-300"
                                        title="حذف"
                                        @click="deleteUser(user)"
                                    >
                                        <Trash2 class="size-3.5 stroke-[1.75]" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-100 px-4 py-4 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500 dark:text-neutral-400">
                    عرض {{ formatInteger(showingFrom) }} - {{ formatInteger(showingTo) }} من {{ formatInteger(filteredUsers.length) }}
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
            @click.self="closeModal"
        >
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeModal" />

            <div class="relative z-10 max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white shadow-2xl dark:bg-neutral-800" dir="rtl">
                <div class="flex items-center justify-between border-b border-neutral-200 p-6 dark:border-neutral-700">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                            <Pencil v-if="isEditing" class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            <Plus v-else class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                                {{ isEditing ? 'تعديل المستخدم' : 'إضافة مستخدم جديد' }}
                            </h2>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ isEditing ? 'يمكنك تعديل البيانات وكلمة المرور عند الحاجة' : 'يستخدم البريد وكلمة المرور لتسجيل الدخول' }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="text-neutral-400 transition-colors hover:text-neutral-600 dark:hover:text-neutral-200"
                        @click="closeModal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form class="space-y-4 p-6" @submit.prevent="submitForm">
                    <div class="space-y-1.5">
                        <Label for="customer_name">الاسم الكامل <span class="text-red-500">*</span></Label>
                        <Input
                            id="customer_name"
                            v-model="form.customer_name"
                            placeholder="أدخل الاسم الكامل"
                            :class="{ 'border-red-500': form.errors.customer_name }"
                        />
                        <p v-if="form.errors.customer_name" class="text-xs text-red-500">{{ form.errors.customer_name }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="email">البريد الإلكتروني <span class="text-red-500">*</span></Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="example@email.com"
                            dir="ltr"
                            :class="{ 'border-red-500': form.errors.email }"
                        />
                        <p v-if="form.errors.email" class="text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="phone">رقم الهاتف</Label>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            type="tel"
                            placeholder="05xxxxxxxx"
                            dir="ltr"
                            :class="{ 'border-red-500': form.errors.phone }"
                        />
                        <p v-if="form.errors.phone" class="text-xs text-red-500">{{ form.errors.phone }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="country">البلد</Label>
                        <Input
                            id="country"
                            v-model="form.country"
                            placeholder="المملكة العربية السعودية"
                            :class="{ 'border-red-500': form.errors.country }"
                        />
                        <p v-if="form.errors.country" class="text-xs text-red-500">{{ form.errors.country }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="role">الدور <span class="text-red-500">*</span></Label>
                        <select
                            id="role"
                            v-model="form.role"
                            class="flex h-10 w-full rounded-md border border-neutral-200 bg-white px-3 py-2 text-sm ring-offset-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 dark:border-neutral-700 dark:bg-neutral-900 dark:ring-offset-neutral-950 dark:focus-visible:ring-neutral-300"
                            :class="{ 'border-red-500': form.errors.role }"
                        >
                            <option value="admin">ادمن</option>
                            <option value="general_manager">مدير عام</option>
                            <option value="manager">مسئول</option>
                            <option value="accounts">حسابات</option>
                            <option value="workers_manager">مدير العمال</option>
                            <option value="warehouse_keeper">أمين مستودع</option>
                            <option value="worker">عامل</option>
                        </select>
                        <p v-if="form.errors.role" class="text-xs text-red-500">{{ form.errors.role }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="password">
                            كلمة المرور
                            <span v-if="!isEditing" class="text-red-500">*</span>
                            <span v-else class="text-xs font-normal text-neutral-500">(اتركها فارغة إن لم ترد تغييرها)</span>
                        </Label>
                        <div class="relative">
                            <Input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                :placeholder="isEditing ? 'كلمة مرور جديدة (اختياري)' : '6 أحرف على الأقل'"
                                dir="ltr"
                                class="pl-10"
                                :class="{ 'border-red-500': form.errors.password }"
                            />
                            <button
                                type="button"
                                tabindex="-1"
                                class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600"
                                @click="showPassword = !showPassword"
                            >
                                <Eye v-if="!showPassword" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="text-xs text-red-500">{{ form.errors.password }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="password_confirmation">
                            تأكيد كلمة المرور
                            <span v-if="!isEditing" class="text-red-500">*</span>
                        </Label>
                        <div class="relative">
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="showConfirm ? 'text' : 'password'"
                                placeholder="أعد كتابة كلمة المرور"
                                dir="ltr"
                                class="pl-10"
                            />
                            <button
                                type="button"
                                tabindex="-1"
                                class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600"
                                @click="showConfirm = !showConfirm"
                            >
                                <Eye v-if="!showConfirm" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <Button type="submit" class="flex-1" :disabled="form.processing">
                            <span v-if="form.processing">{{ isEditing ? 'جاري الحفظ...' : 'جاري الإنشاء...' }}</span>
                            <span v-else>{{ isEditing ? 'حفظ التعديلات' : 'إنشاء المستخدم' }}</span>
                        </Button>
                        <Button type="button" variant="outline" class="flex-1" @click="closeModal">
                            إلغاء
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
