<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Bot } from 'lucide-vue-next';

const props = defineProps<{
    settings: {
        whatsapp_ai_enabled: boolean;
        has_openai_key: boolean;
        whatsapp_ai_model: string;
        whatsapp_ai_system_prompt: string | null;
        whatsapp_ai_purpose: string | null;
        whatsapp_ai_tone: string | null;
        whatsapp_ai_handoff_rules: string | null;
        whatsapp_ai_max_reply_chars: number;
        whatsapp_ai_bot_pause_minutes: number;
        timezone: string;
        has_app_secret: boolean;
        verify_token: string;
        waba_id: string;
        waba_id_locked: boolean;
    };
    channel: {
        phone_number_id: string;
        graph_version: string;
        webhook_url: string;
        quality: Record<string, unknown>;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'بوت واتساب', href: '/settings/whatsapp-ai' },
];

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);

const form = useForm({
    whatsapp_ai_enabled: props.settings.whatsapp_ai_enabled,
    whatsapp_ai_openai_key: '',
    whatsapp_ai_model: props.settings.whatsapp_ai_model,
    whatsapp_ai_system_prompt: props.settings.whatsapp_ai_system_prompt || '',
    whatsapp_ai_purpose: props.settings.whatsapp_ai_purpose || '',
    whatsapp_ai_tone: props.settings.whatsapp_ai_tone || '',
    whatsapp_ai_handoff_rules: props.settings.whatsapp_ai_handoff_rules || '',
    whatsapp_ai_max_reply_chars: props.settings.whatsapp_ai_max_reply_chars,
    whatsapp_ai_bot_pause_minutes: props.settings.whatsapp_ai_bot_pause_minutes,
    timezone: props.settings.timezone,
    app_secret: '',
    verify_token: props.settings.verify_token,
    waba_id: props.settings.waba_id || '',
});

function submit() {
    form.put('/settings/whatsapp-ai', { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="بوت واتساب" />

        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-100 dark:bg-teal-900/30">
                    <Bot class="h-6 w-6 text-teal-700" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">إعدادات بوت واتساب</h1>
                    <p class="text-sm text-zinc-500">خاص بنظام عالم المغامرة — لا يشارك الرقم أو المفاتيح مع أي نظام آخر</p>
                </div>
            </div>

            <p v-if="successMessage" class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ successMessage }}</p>

            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                تفعيل البوت يعني أنه سيرد تلقائياً على رسائل العملاء عبر واتساب ما لم تُحوَّل المحادثة لموظف أو يُوقف البوت.
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="font-semibold">قناة هذا النظام</div>
                <div dir="ltr" class="mt-2 space-y-1 font-mono text-xs text-zinc-600 dark:text-zinc-300">
                    <div>phone_number_id: {{ channel.phone_number_id }}</div>
                    <div>graph: {{ channel.graph_version }}</div>
                    <div>webhook: {{ channel.webhook_url }}</div>
                    <div>verify_token: {{ settings.verify_token || '—' }}</div>
                    <div>waba_id: {{ settings.waba_id || 'missing — أضفه أدناه لجلب القوالب' }}</div>
                    <div>app_secret: {{ settings.has_app_secret ? 'configured' : 'missing — أضفه أدناه أو في .env' }}</div>
                </div>
            </div>

            <form class="space-y-4 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900" @submit.prevent="submit">
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.whatsapp_ai_enabled" type="checkbox" class="size-4 accent-teal-700" />
                    تفعيل البوت الذكي
                </label>

                <div>
                    <Label>OpenAI key {{ settings.has_openai_key ? '(محفوظ — اتركه فارغاً للإبقاء)' : '' }}</Label>
                    <Input v-model="form.whatsapp_ai_openai_key" type="password" autocomplete="new-password" class="mt-1" placeholder="sk-..." />
                </div>
                <div>
                    <Label>Model</Label>
                    <Input v-model="form.whatsapp_ai_model" class="mt-1" />
                </div>
                <div>
                    <Label>System prompt</Label>
                    <Textarea v-model="form.whatsapp_ai_system_prompt" class="mt-1 min-h-32" />
                </div>
                <div>
                    <Label>Purpose</Label>
                    <Textarea v-model="form.whatsapp_ai_purpose" class="mt-1" />
                </div>
                <div>
                    <Label>Tone</Label>
                    <Textarea v-model="form.whatsapp_ai_tone" class="mt-1" />
                </div>
                <div>
                    <Label>Handoff rules</Label>
                    <Textarea v-model="form.whatsapp_ai_handoff_rules" class="mt-1" />
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <Label>max_reply_chars</Label>
                        <Input v-model.number="form.whatsapp_ai_max_reply_chars" type="number" class="mt-1" />
                    </div>
                    <div>
                        <Label>bot_pause_minutes</Label>
                        <Input v-model.number="form.whatsapp_ai_bot_pause_minutes" type="number" class="mt-1" />
                    </div>
                    <div>
                        <Label>Timezone</Label>
                        <Input v-model="form.timezone" class="mt-1" />
                    </div>
                </div>
                <div>
                    <Label>معرّف حساب واتساب للأعمال (WABA ID)</Label>
                    <Input
                        v-model="form.waba_id"
                        class="mt-1"
                        dir="ltr"
                        :disabled="settings.waba_id_locked"
                        placeholder="مثال: 123456789012345"
                    />
                    <p class="mt-1 text-xs text-zinc-500">
                        مطلوب لعرض القوالب المعتمدة. انسخه من Meta Business Suite ← WhatsApp Accounts ← معرّف الحساب.
                        <span v-if="settings.waba_id_locked"> مضبوط حالياً من ملف .env.</span>
                    </p>
                </div>
                <div>
                    <Label>App secret (Webhook HMAC)</Label>
                    <Input v-model="form.app_secret" type="password" class="mt-1" placeholder="إذا كان مضبوطاً في .env سيُستخدم تلقائياً" />
                </div>
                <Button type="submit" class="bg-teal-700 hover:bg-teal-800" :disabled="form.processing">حفظ</Button>
            </form>
        </div>
    </AppLayout>
</template>
