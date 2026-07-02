<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { MessageCircle, Plus, Trash2 } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Recipient {
    id: number;
    phone: string;
    display_phone: string;
    label: string | null;
    is_active: boolean;
}

const props = defineProps<{
    recipients: Recipient[];
    whatsapp_configured: boolean;
    sender_phone: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'إعدادات واتساب الطلبات', href: '/settings/whatsapp' },
];

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);

const form = useForm({
    phone: '',
    label: '',
});

function submitAdd() {
    form.post(route('settings.whatsapp.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
        },
    });
}

function toggleActive(recipient: Recipient) {
    router.patch(route('settings.whatsapp.update', recipient.id), {
        is_active: !recipient.is_active,
    }, { preserveScroll: true });
}

function deleteRecipient(recipient: Recipient) {
    if (!confirm(`حذف الرقم ${recipient.display_phone}؟`)) {
        return;
    }

    router.delete(route('settings.whatsapp.destroy', recipient.id), {
        preserveScroll: true,
    });
}

const activeCount = computed(() => props.recipients.filter((r) => r.is_active).length);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="إعدادات واتساب الطلبات" />

        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30">
                    <MessageCircle class="h-6 w-6 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">إعدادات واتساب الطلبات</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        الأرقام التي تستقبل إشعار تفاصيل الطلب — من أي رقم تضيفه هنا فقط
                    </p>
                </div>
            </div>
            <Badge :variant="whatsapp_configured ? 'default' : 'destructive'">
                {{ whatsapp_configured ? 'واتساب متصل' : 'واتساب غير مفعّل' }}
            </Badge>
        </div>

        <p v-if="successMessage" class="rounded-xl bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:bg-green-900/20 dark:text-green-300">
            {{ successMessage }}
        </p>

        <div class="rounded-xl border border-blue-100 bg-blue-50/80 px-4 py-3 text-sm text-blue-900 dark:border-blue-900/40 dark:bg-blue-900/20 dark:text-blue-200">
            <span class="font-semibold">رقم الإرسال:</span>
            <span dir="ltr" class="ms-1 font-mono">{{ sender_phone }}</span>
            <span class="mt-1 block text-blue-800/80 dark:text-blue-300/90">
                هذا رقم النشاط التجاري الذي تُرسل منه الرسائل — لا يمكن إضافته كمستلم.
            </span>
        </div>

        <div class="grid gap-6 lg:grid-cols-5">
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:col-span-2">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">إضافة رقم جديد</h2>

                <form class="space-y-4" @submit.prevent="submitAdd">
                    <div>
                        <Label for="phone" class="mb-2 block">رقم الجوال</Label>
                        <div class="flex overflow-hidden rounded-xl border border-gray-200 dark:border-gray-600" dir="ltr">
                            <span class="flex shrink-0 items-center border-e border-gray-200 bg-gray-50 px-3 text-sm font-semibold text-gray-600 dark:border-gray-600 dark:bg-gray-900/40">
                                +966
                            </span>
                            <Input
                                id="phone"
                                v-model="form.phone"
                                type="tel"
                                inputmode="numeric"
                                placeholder="5XXXXXXXX"
                                class="border-0 shadow-none focus-visible:ring-0"
                            />
                        </div>
                        <p v-if="form.errors.phone" class="mt-1.5 text-xs text-red-500">{{ form.errors.phone }}</p>
                    </div>

                    <div>
                        <Label for="label" class="mb-2 block">ملاحظة (اختياري)</Label>
                        <Input
                            id="label"
                            v-model="form.label"
                            type="text"
                            placeholder="مثال: مدير المتجر"
                        />
                    </div>

                    <Button type="submit" class="w-full gap-2" :disabled="form.processing">
                        <Plus class="h-4 w-4" />
                        إضافة الرقم
                    </Button>
                </form>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:col-span-3">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">الأرقام المسجّلة</h2>
                    <span class="text-sm text-gray-500">{{ activeCount }} نشط</span>
                </div>

                <div v-if="recipients.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-500 dark:border-gray-600">
                    لا توجد أرقام. أضف رقماً لاستقبال إشعارات الطلبات.
                </div>

                <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li
                        v-for="recipient in recipients"
                        :key="recipient.id"
                        class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white" dir="ltr">{{ recipient.display_phone }}</p>
                            <p v-if="recipient.label" class="mt-0.5 text-sm text-gray-500">{{ recipient.label }}</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                :variant="recipient.is_active ? 'default' : 'outline'"
                                @click="toggleActive(recipient)"
                            >
                                {{ recipient.is_active ? 'نشط' : 'موقوف' }}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="text-red-600 hover:text-red-700"
                                @click="deleteRecipient(recipient)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
        </div>
    </AppLayout>
</template>
