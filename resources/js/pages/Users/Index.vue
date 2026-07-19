<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Users, Mail, Phone, Globe, Trash2, Plus, X, Eye, EyeOff, Pencil } from 'lucide-vue-next';
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

const roleLabels: Record<StaffRole, string> = {
    admin: 'ادمن',
    general_manager: 'مدير عام',
    manager: 'مسئول',
    accounts: 'حسابات',
    workers_manager: 'مدير العمال',
    worker: 'عامل',
};

const roleBadgeClass: Record<StaffRole, string> = {
    admin: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    general_manager: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
    manager: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
    accounts: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    workers_manager: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',
    worker: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
};

interface Props {
    users: User[];
}

defineProps<Props>();
defineOptions({ layout: AppLayout });

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);
const errorMessage = computed(() => page.props.flash?.error as string | undefined);

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

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <p
                v-if="successMessage"
                class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300"
            >
                {{ successMessage }}
            </p>
            <p
                v-if="errorMessage"
                class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300"
            >
                {{ errorMessage }}
            </p>

            <div class="overflow-hidden bg-white shadow-sm dark:bg-neutral-800 sm:rounded-lg">
                <div class="p-6 text-neutral-900 dark:text-neutral-100">

                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <Users class="h-8 w-8 text-blue-600" />
                            <h1 class="text-2xl font-semibold">إدارة المستخدمين</h1>
                        </div>
                        <Button class="flex items-center gap-2" @click="openCreateModal">
                            <Plus class="h-4 w-4" />
                            إضافة مستخدم
                        </Button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-neutral-200 dark:border-neutral-700">
                            <thead>
                                <tr class="bg-neutral-50 dark:bg-neutral-700">
                                    <th class="border border-neutral-200 px-4 py-3 text-right font-medium dark:border-neutral-600">المعرف</th>
                                    <th class="border border-neutral-200 px-4 py-3 text-right font-medium dark:border-neutral-600">الاسم</th>
                                    <th class="border border-neutral-200 px-4 py-3 text-right font-medium dark:border-neutral-600">البريد الإلكتروني</th>
                                    <th class="border border-neutral-200 px-4 py-3 text-right font-medium dark:border-neutral-600">الهاتف</th>
                                    <th class="border border-neutral-200 px-4 py-3 text-right font-medium dark:border-neutral-600">البلد</th>
                                    <th class="border border-neutral-200 px-4 py-3 text-right font-medium dark:border-neutral-600">الدور</th>
                                    <th class="border border-neutral-200 px-4 py-3 text-right font-medium dark:border-neutral-600">تاريخ الإنشاء</th>
                                    <th class="border border-neutral-200 px-4 py-3 text-center font-medium dark:border-neutral-600">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="users.length === 0">
                                    <td colspan="8" class="border border-neutral-200 px-4 py-8 text-center text-neutral-500 dark:border-neutral-600">
                                        لا يوجد مستخدمون حتى الآن.
                                    </td>
                                </tr>
                                <tr
                                    v-for="user in users"
                                    :key="user.id"
                                    class="hover:bg-neutral-50 dark:hover:bg-neutral-700"
                                >
                                    <td class="border border-neutral-200 px-4 py-3 text-sm tabular-nums text-neutral-500 dark:border-neutral-600" dir="ltr">
                                        #{{ formatInteger(user.id) }}
                                    </td>
                                    <td class="border border-neutral-200 px-4 py-3 font-medium dark:border-neutral-600">{{ user.name }}</td>
                                    <td class="border border-neutral-200 px-4 py-3 dark:border-neutral-600">
                                        <div class="flex items-center gap-2">
                                            <Mail class="h-4 w-4 shrink-0 text-neutral-400" />
                                            {{ user.email }}
                                        </div>
                                    </td>
                                    <td class="border border-neutral-200 px-4 py-3 dark:border-neutral-600">
                                        <div class="flex items-center gap-2">
                                            <Phone class="h-4 w-4 shrink-0 text-neutral-400" />
                                            {{ user.phone || '—' }}
                                        </div>
                                    </td>
                                    <td class="border border-neutral-200 px-4 py-3 dark:border-neutral-600">
                                        <div class="flex items-center gap-2">
                                            <Globe class="h-4 w-4 shrink-0 text-neutral-400" />
                                            {{ user.country || '—' }}
                                        </div>
                                    </td>
                                    <td class="border border-neutral-200 px-4 py-3 dark:border-neutral-600">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="roleBadgeClass[user.role]"
                                        >
                                            {{ roleLabels[user.role] }}
                                        </span>
                                    </td>
                                    <td class="border border-neutral-200 px-4 py-3 text-sm tabular-nums dark:border-neutral-600" dir="ltr">
                                        {{ formatDate(user.created_at) }}
                                    </td>
                                    <td class="border border-neutral-200 px-4 py-3 dark:border-neutral-600">
                                        <div class="flex items-center justify-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="flex items-center gap-1"
                                                @click="openEditModal(user)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                                تعديل
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                size="sm"
                                                class="flex items-center gap-1"
                                                @click="deleteUser(user)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                                حذف
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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
