<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Users, Mail, Phone, MapPin, Globe, Trash2, Plus, X, Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';

interface User {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    country: string | null;
    address: string | null;
    created_at: string;
}

interface Props {
    users: User[];
}

defineProps<Props>();
defineOptions({ layout: AppLayout });

// ── Modal state ──────────────────────────────────────────────────────────────
const showModal = ref(false);
const showPassword = ref(false);
const showConfirm  = ref(false);

const form = useForm({
    customer_name:          '',
    email:                  '',
    phone:                  '',
    country:                '',
    password:               '',
    password_confirmation:  '',
});

function openModal() {
    form.reset();
    form.clearErrors();
    showPassword.value = false;
    showConfirm.value  = false;
    showModal.value    = true;
}

function closeModal() {
    showModal.value = false;
}

function submitForm() {
    form.post(route('users.store'), {
        onSuccess: () => closeModal(),
    });
}

// ── Delete ───────────────────────────────────────────────────────────────────
const deleteUser = (user: User) => {
    if (confirm(`هل أنت متأكد من حذف المستخدم "${user.name}"؟ لا يمكن التراجع عن هذا الإجراء.`)) {
        router.delete(route('users.destroy', user.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="المستخدمون" />

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-neutral-800 sm:rounded-lg">
                <div class="p-6 text-neutral-900 dark:text-neutral-100">

                    <!-- Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <Users class="w-8 h-8 text-blue-600" />
                            <h1 class="text-2xl font-semibold">إدارة المستخدمين</h1>
                        </div>
                        <Button @click="openModal" class="flex items-center gap-2">
                            <Plus class="w-4 h-4" />
                            إضافة مستخدم
                        </Button>
                    </div>

                    <!-- Users Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-neutral-200 dark:border-neutral-700">
                            <thead>
                                <tr class="bg-neutral-50 dark:bg-neutral-700">
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-right font-medium">المعرف</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-right font-medium">الاسم</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-right font-medium">البريد الإلكتروني</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-right font-medium">الهاتف</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-right font-medium">البلد</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-right font-medium">تاريخ الإنشاء</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-center font-medium">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="users.length === 0">
                                    <td colspan="7" class="border border-neutral-200 dark:border-neutral-600 px-4 py-8 text-neutral-500 text-center">
                                        لا يوجد مستخدمون حتى الآن.
                                    </td>
                                </tr>
                                <tr v-for="user in users" :key="user.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-700">
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-sm text-neutral-500">#{{ user.id }}</td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 font-medium">{{ user.name }}</td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <Mail class="w-4 h-4 text-neutral-400 shrink-0" />
                                            {{ user.email }}
                                        </div>
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <Phone class="w-4 h-4 text-neutral-400 shrink-0" />
                                            {{ user.phone || '—' }}
                                        </div>
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <Globe class="w-4 h-4 text-neutral-400 shrink-0" />
                                            {{ user.country || '—' }}
                                        </div>
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 tabular-nums text-sm" dir="ltr">
                                        {{ new Date(user.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) }}
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-center">
                                        <Button @click="deleteUser(user)" variant="destructive" size="sm" class="flex items-center gap-1">
                                            <Trash2 class="w-4 h-4" />
                                            حذف
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Add User Modal ──────────────────────────────────────────────────── -->
    <Teleport to="body">
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            @click.self="closeModal"
        >
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeModal" />

            <!-- Dialog -->
            <div class="relative w-full max-w-md bg-white dark:bg-neutral-800 rounded-xl shadow-2xl z-10" dir="rtl">

                <!-- Header -->
                <div class="flex items-center justify-between p-6 border-b border-neutral-200 dark:border-neutral-700">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                            <Plus class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">إضافة مستخدم جديد</h2>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">يستخدم البريد وكلمة المرور لتسجيل الدخول في التطبيق</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="closeModal"
                        class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition-colors"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Form -->
                <form @submit.prevent="submitForm" class="p-6 space-y-4">

                    <!-- Name -->
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

                    <!-- Email -->
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

                    <!-- Phone -->
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

                    <!-- Country -->
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

                    <!-- Password -->
                    <div class="space-y-1.5">
                        <Label for="password">كلمة المرور <span class="text-red-500">*</span></Label>
                        <div class="relative">
                            <Input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="6 أحرف على الأقل"
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
                                <Eye v-if="!showPassword" class="w-4 h-4" />
                                <EyeOff v-else class="w-4 h-4" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="text-xs text-red-500">{{ form.errors.password }}</p>
                    </div>

                    <!-- Confirm Password -->
                    <div class="space-y-1.5">
                        <Label for="password_confirmation">تأكيد كلمة المرور <span class="text-red-500">*</span></Label>
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
                                <Eye v-if="!showConfirm" class="w-4 h-4" />
                                <EyeOff v-else class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3 pt-2">
                        <Button type="submit" :disabled="form.processing" class="flex-1">
                            <span v-if="form.processing">جاري الإنشاء...</span>
                            <span v-else>إنشاء المستخدم</span>
                        </Button>
                        <Button type="button" variant="outline" @click="closeModal" class="flex-1">
                            إلغاء
                        </Button>
                    </div>

                </form>
            </div>
        </div>
    </Teleport>
</template>
