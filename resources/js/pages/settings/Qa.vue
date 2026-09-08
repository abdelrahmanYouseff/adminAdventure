<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

interface Template {
    name: string;
    language: string;
    status: string;
}

const props = defineProps<{
    settings: {
        qa_rating_enabled: boolean;
        qa_rating_delay_amount: number;
        qa_rating_delay_unit: string;
        qa_rating_template_name: string | null;
        qa_rating_template_language: string | null;
        qa_rating_notify_emails: string | null;
    };
    templates: Template[];
    templates_error: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'تقييم الخدمة', href: '/settings/qa' },
];

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success as string | undefined);
const flashError = computed(() => page.props.flash?.error as string | undefined);

const form = useForm({
    qa_rating_enabled: props.settings.qa_rating_enabled,
    qa_rating_delay_amount: props.settings.qa_rating_delay_amount,
    qa_rating_delay_unit: props.settings.qa_rating_delay_unit,
    qa_rating_template_name: props.settings.qa_rating_template_name || '',
    qa_rating_template_language: props.settings.qa_rating_template_language || 'ar',
    qa_rating_notify_emails: props.settings.qa_rating_notify_emails || '',
});

const testForm = useForm({
    phone: '',
});

function submit() {
    form.put('/settings/qa', { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="تقييم الخدمة" />

        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">تقييم الخدمة عبر واتساب</h1>
            <p v-if="flashSuccess" class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ flashSuccess }}</p>
            <p v-if="flashError" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{{ flashError }}</p>
            <p v-if="templates_error" class="text-sm text-orange-600">{{ templates_error }}</p>

            <form class="space-y-4 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900" @submit.prevent="submit">
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.qa_rating_enabled" type="checkbox" class="size-4 accent-teal-700" />
                    تفعيل دعوات التقييم
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label>التأخير بعد إغلاق الحجز</Label>
                        <Input v-model.number="form.qa_rating_delay_amount" type="number" class="mt-1" />
                    </div>
                    <div>
                        <Label>الوحدة</Label>
                        <select v-model="form.qa_rating_delay_unit" class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-zinc-700 dark:bg-transparent">
                            <option value="minutes">دقائق</option>
                            <option value="hours">ساعات</option>
                            <option value="days">أيام</option>
                        </select>
                    </div>
                </div>
                <div>
                    <Label>قالب واتساب</Label>
                    <select v-model="form.qa_rating_template_name" class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-zinc-700 dark:bg-transparent">
                        <option value="">—</option>
                        <option v-for="tpl in templates" :key="tpl.name + tpl.language" :value="tpl.name">
                            {{ tpl.name }} ({{ tpl.language }})
                        </option>
                    </select>
                </div>
                <div>
                    <Label>لغة القالب</Label>
                    <Input v-model="form.qa_rating_template_language" class="mt-1" />
                </div>
                <div>
                    <Label>إيميلات الإشعار عند اكتمال التقييم</Label>
                    <Input v-model="form.qa_rating_notify_emails" class="mt-1" placeholder="a@x.com, b@x.com" />
                </div>
                <Button type="submit" class="bg-teal-700 hover:bg-teal-800" :disabled="form.processing">حفظ</Button>
            </form>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="mb-3 font-semibold">إرسال تجربة</h2>
                <form class="flex gap-2" @submit.prevent="testForm.post('/settings/qa/test')">
                    <Input v-model="testForm.phone" dir="ltr" placeholder="+9665xxxxxxxx" />
                    <Button type="submit" class="bg-teal-700 hover:bg-teal-800" :disabled="testForm.processing">إرسال</Button>
                </form>
                <Button type="button" variant="outline" class="mt-3" @click="router.post('/settings/qa/backfill')">Backfill</Button>
            </div>
        </div>
    </AppLayout>
</template>
