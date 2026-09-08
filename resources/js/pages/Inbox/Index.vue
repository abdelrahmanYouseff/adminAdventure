<script setup lang="ts">
import InboxShell from '@/layouts/InboxShell.vue';
import { useInboxLocale } from '@/composables/useInboxLocale';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    BotOff,
    ChevronDown,
    Lock,
    Pause,
    UserRound,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface Contact {
    id: number;
    name: string;
    phone: string;
    phone_display: string;
    initials: string;
    subscribed: boolean;
}

interface Conversation {
    id: number;
    status: string;
    contact: Contact;
    assignee: { id: number; name: string } | null;
    assigned_to_me: boolean;
    last_message_at: string | null;
    preview: string | null;
    preview_direction: string | null;
    preview_sender: string | null;
    preview_type: string | null;
    needs_human_agent: boolean;
    bot_paused: boolean;
    bot_dot: 'green' | 'orange' | 'gray';
    window_closed: boolean;
    ai_lead_requirements: string | null;
    ai_enabled: boolean;
}

interface Message {
    id: number;
    direction: 'inbound' | 'outbound';
    sender_type: string;
    message_type: string;
    status: string;
    error_message: string | null;
    created_at: string;
    is_voice: boolean;
    payload: {
        text: string | null;
        caption: string | null;
        media_url: string | null;
        mime_type: string | null;
        filename: string | null;
        template_name: string | null;
        latitude?: number | null;
        longitude?: number | null;
        name?: string | null;
        address?: string | null;
    };
}

interface Booking {
    id: number;
    order_number: string;
    status: string;
    order_type?: string | null;
    vehicle: string;
    expected_return: string | null;
    remaining_amount: number;
    currency: string;
    url: string;
}

interface WaTemplate {
    name: string;
    language: string;
    status: string;
    category: string;
    components: Array<Record<string, unknown>>;
}

const props = defineProps<{
    conversations: {
        data: Conversation[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    counts: Record<string, number>;
    filters: { status: string; filter: string };
    selected: Conversation | null;
    messages: Message[];
    window: { is_open: boolean; expires_at: string | null; remaining_seconds: number } | null;
    agents: { id: number; name: string }[];
    quickReplies: { id: number; title: string; body: string }[];
    compose: 'freeform' | 'template';
    ai: { enabled: boolean; paused_minutes: number };
    display_phone: string;
}>();

const page = usePage();
const { locale } = useInboxLocale();
const t = (key: string) => (locale.value === 'en' ? en[key] : ar[key]) || key;

const ar: Record<string, string> = {
    inbox: 'Inbox',
    newChat: 'New chat',
    open: 'Open',
    pending: 'Pending',
    closed: 'Closed',
    all: 'All',
    mine: 'Mine',
    unassigned: 'Unassigned',
    needsHuman: 'Needs human',
    select: 'Select a conversation',
    you: 'You',
    bot: 'Bot',
    markHuman: 'Mark needs human',
    resumeBot: 'Resume bot',
    botPaused: 'AI bot paused',
    assignMe: 'Assign me',
    unassign: 'Unassign me',
    assignTo: 'Assign to',
    freeform: 'Freeform',
    template: 'Template',
    send: 'Send',
    windowClosed: 'يجب إرسال قالب معتمد أولاً. واتساب يسمح بالنص الحر خلال 24 ساعة من آخر رسالة واردة فقط.',
    pickTemplate: 'اختر قالباً معتمداً',
    templatesEmpty: 'لا توجد قوالب معتمدة',
    templatesLoading: 'جاري جلب القوالب…',
    templatesSettings: 'إعدادات واتساب',
    next: 'Next',
    back: 'Back',
    bookings: 'Bookings',
    quickReplies: 'Quick replies',
    phone: 'Phone number',
    start: 'Start',
    close: 'Close',
    reopen: 'Open',
    setPending: 'Pending',
    justNow: 'الآن',
    empty: 'No conversations yet.',
    noBookings: 'No bookings',
    viewOrder: 'View order',
    viewNode: 'View on Node System',
    enterToSend: 'Press Enter to send',
    expected: 'Expected',
    remaining: 'Remaining',
    previous: 'Previous',
    nextPage: 'Next',
    closeModal: 'Close',
    e164: 'E.164 — مثال ‎+9665xxxxxxxx',
    voice: 'Voice note',
    headerMedia: 'Header media',
    upload: 'Upload',
    recent: 'Last 24 files',
    url: 'URL',
    loading: 'Loading…',
    youPrefix: 'You: ',
    botPrefix: 'Bot: ',
};

const en: Record<string, string> = {
    inbox: 'Inbox',
    newChat: 'New chat',
    open: 'Open',
    pending: 'Pending',
    closed: 'Closed',
    all: 'All',
    mine: 'Mine',
    unassigned: 'Unassigned',
    needsHuman: 'Needs human',
    select: 'Select a conversation',
    you: 'You',
    bot: 'Bot',
    markHuman: 'Mark needs human',
    resumeBot: 'Resume bot',
    botPaused: 'AI bot paused',
    assignMe: 'Assign me',
    unassign: 'Unassign me',
    assignTo: 'Assign to',
    freeform: 'Freeform',
    template: 'Template',
    send: 'Send',
    windowClosed: 'Freeform text is blocked until the customer messages you. Send an approved template first.',
    pickTemplate: 'Pick an approved template',
    templatesEmpty: 'No approved templates on this WhatsApp number',
    templatesLoading: 'Loading templates from Meta…',
    templatesSettings: 'Open WhatsApp settings',
    next: 'Next',
    back: 'Back',
    bookings: 'Bookings',
    quickReplies: 'Quick replies',
    phone: 'Phone number',
    start: 'Start',
    close: 'Close',
    reopen: 'Open',
    setPending: 'Pending',
    justNow: 'Just now',
    empty: 'No conversations yet.',
    noBookings: 'No bookings',
    viewOrder: 'View order',
    viewNode: 'View on Node System',
    enterToSend: 'Press Enter to send',
    expected: 'Expected',
    remaining: 'Remaining',
    previous: 'Previous',
    nextPage: 'Next',
    closeModal: 'Close',
    e164: 'E.164 format, e.g. +9665xxxxxxxx',
    voice: 'Voice note',
    headerMedia: 'Header media',
    upload: 'Upload',
    recent: 'Last 24 files',
    url: 'URL',
    loading: 'Loading…',
    youPrefix: 'You: ',
    botPrefix: 'Bot: ',
};

const nowTick = ref(Date.now());
const body = ref('');
const mode = ref<'freeform' | 'template'>(props.compose === 'template' ? 'template' : 'freeform');
const showNew = ref(false);
const newPhone = ref('');
const showQuick = ref(false);
const showAssign = ref(false);
const showTemplates = ref(false);
const enterToSend = ref(true);
const quickQuery = ref('');
const newQuickTitle = ref('');
const newQuickBody = ref('');
const bookings = ref<Booking[]>([]);
const bookingsLoading = ref(false);
const loadedBookingsFor = ref<number | null>(null);
const threadEl = ref<HTMLElement | null>(null);
const templates = ref<WaTemplate[]>([]);
const templatesLoading = ref(false);
const templatesError = ref('');
const templateStep = ref(1);
const chosenTemplate = ref<WaTemplate | null>(null);
const templateParams = ref<Record<string, string>>({});
const headerLink = ref('');
const headerUuid = ref('');
const recentUploads = ref<{ uuid: string; url: string; name: string | null; mime: string | null }[]>([]);
const sending = ref(false);
const csrf = computed(() => (page.props.csrf_token as string) || '');

watch(
    () => props.compose,
    (value) => {
        if (value === 'template') {
            mode.value = 'template';
        }
    },
);

watch(
    () => props.window?.is_open,
    (open) => {
        if (open === false) {
            mode.value = 'template';
        }
    },
    { immediate: true },
);

const windowClosed = computed(() => props.selected !== null && props.window?.is_open === false);
const composerMode = computed(() => (windowClosed.value ? 'template' : mode.value));
const nodeUrl = computed(() => bookings.value[0]?.url || '/orders');

function visitList(extra: Record<string, string | number> = {}, conversationId?: number | null) {
    const query: Record<string, string | number> = {
        status: props.filters.status,
        filter: props.filters.filter,
        page: props.conversations.current_page,
        ...extra,
    };

    const url = conversationId ? `/inbox/${conversationId}` : '/inbox';

    router.get(url, query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only: ['conversations', 'counts', 'filters', 'selected', 'messages', 'window', 'agents', 'quickReplies', 'ai', 'compose'],
    });
}

function openConversation(id: number) {
    visitList({}, id);
}

function relativeTime(iso: string | null): string {
    void nowTick.value;
    if (!iso) {
        return '';
    }
    const then = new Date(iso).getTime();
    const diff = Date.now() - then;
    if (diff < 45_000) {
        return t('justNow');
    }
    if (diff < 3_600_000) {
        return `${Math.max(1, Math.round(diff / 60_000))}m`;
    }
    if (diff < 86_400_000) {
        return `${Math.max(1, Math.round(diff / 3_600_000))}h`;
    }
    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'en' : 'en', { month: 'short', day: 'numeric' }).format(new Date(iso));
}

function previewText(c: Conversation): string {
    switch (c.preview_type) {
        case 'image':
            return c.preview && c.preview !== 'Photo' ? c.preview : 'Photo';
        case 'document':
            return c.preview || 'Document';
        case 'location':
            return 'Location';
        case 'template':
            return c.preview || 'Template';
        default:
            return c.preview || 'Message';
    }
}

function messageStamp(iso: string): string {
    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'en' : 'en', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(iso));
}

function senderLabel(m: Message): string {
    if (m.direction === 'inbound') {
        return props.selected?.contact.name || 'Contact';
    }
    if (m.sender_type === 'bot') {
        return t('bot');
    }
    if (m.sender_type === 'system') {
        return 'System';
    }
    return t('you');
}

function patchStatus(status: string) {
    if (!props.selected) {
        return;
    }
    router.patch(`/inbox/${props.selected.id}/status`, { status }, { preserveScroll: true });
}

function handoff(action: 'needs_human' | 'resume') {
    if (!props.selected) {
        return;
    }
    router.patch(
        `/inbox/${props.selected.id}/handoff`,
        { action, reason: action === 'needs_human' ? 'manual' : null },
        { preserveScroll: true },
    );
}

function assignMe() {
    if (!props.selected) {
        return;
    }
    router.post(`/inbox/${props.selected.id}/assign`, {}, { preserveScroll: true });
}

function assignTo(userId: number) {
    if (!props.selected) {
        return;
    }
    showAssign.value = false;
    router.post(`/inbox/${props.selected.id}/assign`, { user_id: userId }, { preserveScroll: true });
}

function unassign() {
    if (!props.selected) {
        return;
    }
    router.delete(`/inbox/${props.selected.id}/assign`, { preserveScroll: true });
}

function sendText() {
    if (!props.selected || !body.value.trim() || windowClosed.value || sending.value) {
        return;
    }
    sending.value = true;
    router.post(
        `/inbox/${props.selected.id}/messages`,
        { type: 'text', body: body.value },
        {
            preserveScroll: true,
            onFinish: () => {
                sending.value = false;
            },
            onSuccess: () => {
                body.value = '';
            },
        },
    );
}

function onComposerKey(event: KeyboardEvent) {
    if (event.key === 'Enter' && !event.shiftKey && enterToSend.value) {
        event.preventDefault();
        sendText();
    }
}

function startChat() {
    router.post('/inbox', { phone: newPhone.value }, {
        onSuccess: () => {
            showNew.value = false;
            newPhone.value = '';
            mode.value = 'template';
            showTemplates.value = true;
        },
    });
}

const filteredQuick = computed(() => {
    const q = quickQuery.value.trim().toLowerCase();
    if (!q) {
        return props.quickReplies;
    }
    return props.quickReplies.filter(
        (item) => item.title.toLowerCase().includes(q) || item.body.toLowerCase().includes(q),
    );
});

function useQuick(text: string) {
    body.value = text;
    showQuick.value = false;
    if (!windowClosed.value) {
        mode.value = 'freeform';
    }
}

function createQuick() {
    if (!newQuickTitle.value.trim() || !newQuickBody.value.trim()) {
        return;
    }
    router.post(
        '/inbox/quick-replies',
        { title: newQuickTitle.value, body: newQuickBody.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                newQuickTitle.value = '';
                newQuickBody.value = '';
            },
        },
    );
}

function deleteQuick(id: number) {
    router.delete(`/inbox/quick-replies/${id}`, { preserveScroll: true });
}

async function loadBookings(id: number) {
    if (loadedBookingsFor.value === id) {
        return;
    }
    bookingsLoading.value = true;
    try {
        const res = await fetch(`/inbox/${id}/bookings`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await res.json();
        bookings.value = json.bookings ?? [];
        loadedBookingsFor.value = id;
    } finally {
        bookingsLoading.value = false;
    }
}

async function loadTemplates(force = false) {
    if (templates.value.length && !force) {
        return;
    }
    templatesLoading.value = true;
    templatesError.value = '';
    try {
        const res = await fetch('/inbox/templates', { headers: { Accept: 'application/json' } });
        const json = await res.json();
        if (!res.ok || json.success === false) {
            templatesError.value = json.error || t('templatesEmpty');
            templates.value = [];
            return;
        }
        templates.value = (json.templates ?? []).filter((row: WaTemplate) => row.status === 'APPROVED' || !row.status);
        if (!templates.value.length) {
            templatesError.value = t('templatesEmpty');
        }
    } catch {
        templatesError.value = t('templatesEmpty');
    } finally {
        templatesLoading.value = false;
    }
}

function openTemplates() {
    mode.value = 'template';
    showTemplates.value = true;
    templateStep.value = 1;
    chosenTemplate.value = null;
    loadTemplates();
    loadRecentUploads();
}

function setFreeform() {
    if (windowClosed.value) {
        return;
    }
    mode.value = 'freeform';
    showTemplates.value = false;
}

function chooseTemplate(tpl: WaTemplate) {
    chosenTemplate.value = tpl;
    templateParams.value = {};
    headerLink.value = '';
    headerUuid.value = '';
    const needsParams = templateBodyPlaceholders(tpl).length > 0;
    const needsMedia = templateHasMediaHeader(tpl);
    if (!needsParams && !needsMedia) {
        sendTemplate();
        return;
    }
    templateStep.value = 2;
}

function templateBodyPlaceholders(tpl: WaTemplate): string[] {
    const bodyComp = (tpl.components || []).find((c) => String(c.type).toUpperCase() === 'BODY') as
        | { text?: string }
        | undefined;
    const text = bodyComp?.text || '';
    const named = [...text.matchAll(/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/g)].map((m) => m[1]);
    if (named.length) {
        return named;
    }
    return [...text.matchAll(/\{\{(\d+)\}\}/g)].map((m) => m[1]);
}

function placeholderLabel(ph: string): string {
    return `{{${ph}}}`;
}

function templateHasMediaHeader(tpl: WaTemplate): boolean {
    const header = (tpl.components || []).find((c) => String(c.type).toUpperCase() === 'HEADER') as
        | { format?: string }
        | undefined;
    return ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(String(header?.format || '').toUpperCase());
}

async function sendTemplate() {
    if (!props.selected || !chosenTemplate.value) {
        return;
    }
    const placeholders = templateBodyPlaceholders(chosenTemplate.value);
    const parameters: Record<string, string> | { body: string[] } = placeholders.every((p) => /^\d+$/.test(p))
        ? { body: placeholders.map((p) => templateParams.value[p] || '') }
        : Object.fromEntries(placeholders.map((p) => [p, templateParams.value[p] || '']));

    sending.value = true;
    router.post(
        `/inbox/${props.selected.id}/templates`,
        {
            template_name: chosenTemplate.value.name,
            language: chosenTemplate.value.language,
            parameters,
            header_media: headerLink.value || headerUuid.value
                ? {
                      type: 'image',
                      link: headerLink.value || undefined,
                      upload_uuid: headerUuid.value || undefined,
                  }
                : undefined,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                sending.value = false;
            },
            onSuccess: () => {
                templateStep.value = 1;
                chosenTemplate.value = null;
                templateParams.value = {};
                headerLink.value = '';
                headerUuid.value = '';
                showTemplates.value = false;
                mode.value = props.window?.is_open ? 'freeform' : 'template';
            },
        },
    );
}

async function uploadHeader(file: File) {
    const form = new FormData();
    form.append('file', file);
    const res = await fetch('/inbox/media-uploads', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrf.value,
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: form,
    });
    const json = await res.json();
    headerUuid.value = json.uuid;
    headerLink.value = json.url;
}

async function loadRecentUploads() {
    const res = await fetch('/inbox/media-uploads', { headers: { Accept: 'application/json' } });
    const json = await res.json();
    recentUploads.value = json.data ?? [];
}

function scrollThread() {
    nextTick(() => {
        if (threadEl.value) {
            threadEl.value.scrollTop = threadEl.value.scrollHeight;
        }
    });
}

watch(
    () => props.selected?.id,
    (id) => {
        bookings.value = [];
        loadedBookingsFor.value = null;
        showAssign.value = false;
        if (id) {
            loadBookings(id);
            scrollThread();
        }
    },
    { immediate: true },
);

watch(
    () => props.messages.length,
    () => scrollThread(),
);

watch(composerMode, (value) => {
    if (value === 'template' && (showTemplates.value || windowClosed.value)) {
        loadTemplates();
    }
});

let pollTimer: ReturnType<typeof setInterval> | null = null;
let tickTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    enterToSend.value = window.localStorage.getItem('inbox-enter-to-send') !== '0';
    tickTimer = setInterval(() => {
        nowTick.value = Date.now();
    }, 60_000);
    pollTimer = setInterval(() => {
        visitList({}, props.selected?.id ?? null);
    }, 10_000);
});

onBeforeUnmount(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
    if (tickTimer) {
        clearInterval(tickTimer);
    }
});

watch(enterToSend, (value) => {
    window.localStorage.setItem('inbox-enter-to-send', value ? '1' : '0');
});
</script>

<template>
    <InboxShell>
        <Head :title="t('inbox')" />

        <div class="flex h-full min-h-0 w-full overflow-hidden bg-white dark:bg-zinc-950">
            <aside class="flex h-full w-72 shrink-0 flex-col border-e border-zinc-200 dark:border-zinc-800 xl:w-80">
                <div class="border-b border-zinc-200 px-4 py-4 dark:border-zinc-800">
                    <div class="flex items-center justify-between gap-2">
                        <h1 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ t('inbox') }}</h1>
                        <button
                            type="button"
                            class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700"
                            @click="showNew = true"
                        >
                            {{ t('newChat') }}
                        </button>
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-1 rounded-md bg-zinc-100 p-1 text-xs dark:bg-zinc-800">
                        <button
                            v-for="tab in ['open', 'pending', 'closed']"
                            :key="tab"
                            type="button"
                            class="rounded-md px-2 py-1.5"
                            :class="filters.status === tab ? 'bg-white font-medium shadow-sm dark:bg-zinc-900' : 'text-zinc-500'"
                            @click="visitList({ status: tab, page: 1 }, selected?.id ?? null)"
                        >
                            {{ t(tab) }}
                            <span class="ms-1 text-zinc-400">({{ counts[tab] ?? 0 }})</span>
                        </button>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1">
                        <button
                            v-for="item in ['all', 'mine', 'unassigned', 'needs_human']"
                            :key="item"
                            type="button"
                            class="rounded-full px-2.5 py-1 text-xs"
                            :class="filters.filter === item
                                ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200'
                                : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800'"
                            @click="visitList({ filter: item, page: 1 }, selected?.id ?? null)"
                        >
                            {{ item === 'needs_human' ? t('needsHuman') : t(item) }}
                            <span v-if="item === 'unassigned'" class="ms-0.5">({{ counts.unassigned }})</span>
                            <span v-if="item === 'needs_human'" class="ms-0.5">({{ counts.needs_human }})</span>
                        </button>
                    </div>
                </div>

                <div
                    v-if="conversations.last_page > 1"
                    class="flex items-center justify-between border-b border-zinc-200 px-4 py-2 text-xs text-zinc-500 dark:border-zinc-800"
                >
                    <button type="button" :disabled="conversations.current_page <= 1" @click="visitList({ page: conversations.current_page - 1 }, selected?.id ?? null)">
                        {{ t('previous') }}
                    </button>
                    <span>{{ conversations.current_page }} / {{ conversations.last_page }}</span>
                    <button type="button" :disabled="conversations.current_page >= conversations.last_page" @click="visitList({ page: conversations.current_page + 1 }, selected?.id ?? null)">
                        {{ t('nextPage') }}
                    </button>
                </div>

                <div class="inbox-scroll min-h-0 flex-1 overflow-y-auto">
                    <button
                        v-for="c in conversations.data"
                        :key="c.id"
                        type="button"
                        class="flex w-full gap-3 border-b border-zinc-100 px-3 py-3 text-start transition-opacity duration-[350ms] hover:bg-green-50 dark:hover:bg-green-950/30"
                        :class="[
                            selected?.id === c.id ? 'bg-green-100 dark:bg-green-950/50' : '',
                            c.needs_human_agent ? 'border-s-2 border-s-red-500' : '',
                        ]"
                        @click="openConversation(c.id)"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                            :class="c.needs_human_agent
                                ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200'
                                : 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100'"
                        >
                            {{ c.contact.initials }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ c.contact.name }}</span>
                                <span class="shrink-0 text-[11px] tabular-nums text-zinc-500">{{ relativeTime(c.last_message_at) }}</span>
                            </span>
                            <span class="mt-0.5 block truncate text-xs" :class="c.preview_direction === 'inbound' ? 'font-medium text-zinc-700 dark:text-zinc-200' : 'text-zinc-500'">
                                <span v-if="c.preview_direction === 'outbound'" class="text-zinc-400">{{ c.preview_sender === 'bot' ? t('botPrefix') : t('youPrefix') }}</span>
                                {{ previewText(c) }}
                            </span>
                            <span class="mt-1 flex items-center gap-1.5">
                                <span dir="ltr" class="text-[11px] text-zinc-500">{{ c.contact.phone_display }}</span>
                                <span
                                    v-if="c.assignee"
                                    class="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                                >
                                    {{ c.assigned_to_me ? t('you') : c.assignee.name }}
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] text-amber-700 dark:bg-amber-950 dark:text-amber-200"
                                >
                                    {{ t('unassigned') }}
                                </span>
                            </span>
                        </span>
                        <span class="flex shrink-0 flex-col items-center gap-1">
                            <span
                                v-if="c.bot_dot === 'green'"
                                class="flex h-4 w-4 items-center justify-center rounded-full bg-green-600"
                                title="Bot active"
                            >
                                <Zap class="size-2.5 text-white" />
                            </span>
                            <span
                                v-else-if="c.bot_dot === 'orange'"
                                class="flex h-4 w-4 items-center justify-center rounded-full bg-orange-500"
                                title="Bot paused"
                            >
                                <Pause class="size-2.5 text-white" />
                            </span>
                            <span
                                v-else
                                class="flex h-4 w-4 items-center justify-center rounded-full bg-zinc-300 dark:bg-zinc-600"
                                title="Bot off"
                            >
                                <BotOff class="size-2.5 text-zinc-600 dark:text-zinc-200" />
                            </span>
                            <span
                                v-if="c.needs_human_agent"
                                class="flex h-4 w-4 items-center justify-center rounded-full bg-red-600"
                            >
                                <UserRound class="size-2.5 text-white" />
                            </span>
                            <Lock v-if="c.window_closed" class="size-4 text-red-600" />
                        </span>
                    </button>
                    <p v-if="!conversations.data.length" class="px-4 py-8 text-sm text-zinc-500">{{ t('empty') }}</p>
                </div>
            </aside>

            <section class="flex min-h-0 min-w-0 flex-1 flex-col">
                <template v-if="selected">
                    <header class="flex items-start justify-between gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                        <div class="min-w-0">
                            <h2 class="font-semibold text-zinc-900 dark:text-zinc-50">{{ selected.contact.name }}</h2>
                            <div class="mt-0.5 text-sm text-zinc-500">
                                <span dir="ltr">{{ selected.contact.phone_display }}</span>
                                <span v-if="selected.assignee"> · {{ selected.assignee.name }}</span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span v-if="selected.needs_human_agent" class="inline-flex items-center gap-2 rounded-full bg-red-100 px-2.5 py-1 text-xs text-red-800 dark:bg-red-950 dark:text-red-200">
                                    {{ t('needsHuman') }}
                                    <button type="button" class="rounded-full bg-red-200/80 px-2 py-0.5 text-[11px]" @click="handoff('resume')">
                                        {{ t('resumeBot') }}
                                    </button>
                                </span>
                                <span v-else-if="selected.bot_paused" class="inline-flex items-center gap-2 rounded-full bg-orange-100 px-2.5 py-1 text-xs text-orange-900 dark:bg-orange-950 dark:text-orange-100">
                                    {{ t('botPaused') }}
                                    <button type="button" class="rounded-full bg-orange-200/80 px-2 py-0.5 text-[11px]" @click="handoff('resume')">
                                        {{ t('resumeBot') }}
                                    </button>
                                </span>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded-full border border-zinc-300 px-2.5 py-1 text-xs dark:border-zinc-600"
                                    @click="handoff('needs_human')"
                                >
                                    {{ t('markHuman') }}
                                </button>
                            </div>
                            <p v-if="selected.ai_lead_requirements" class="mt-2 text-xs text-zinc-500">{{ selected.ai_lead_requirements }}</p>
                        </div>
                        <a
                            :href="nodeUrl"
                            class="shrink-0 rounded-md border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 dark:border-zinc-600 dark:text-zinc-200"
                        >
                            {{ t('viewNode') }}
                        </a>
                    </header>

                    <div ref="threadEl" class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-6">
                        <div
                            v-for="m in messages"
                            :key="m.id"
                            class="flex"
                            :class="m.direction === 'outbound' ? 'justify-end' : 'justify-start'"
                        >
                            <div
                                dir="auto"
                                class="w-fit max-w-[min(42rem,85%)] rounded-lg px-3 py-2 text-sm leading-snug whitespace-pre-wrap break-words"
                                :class="m.direction === 'outbound'
                                    ? 'self-end bg-teal-700 text-white'
                                    : 'self-start bg-zinc-200 dark:bg-zinc-800'"
                            >
                                <img
                                    v-if="m.payload.media_url && m.message_type === 'image' && !m.is_voice"
                                    :src="m.payload.media_url"
                                    class="mb-2 max-h-56 rounded-md"
                                    alt=""
                                />
                                <template v-else-if="m.is_voice && m.payload.media_url">
                                    <div class="mb-1 text-xs opacity-80">{{ t('voice') }}</div>
                                    <audio class="mb-1 w-56" controls :src="m.payload.media_url" />
                                </template>
                                <a
                                    v-else-if="m.payload.media_url && m.message_type === 'document'"
                                    :href="m.payload.media_url"
                                    target="_blank"
                                    class="underline"
                                >{{ m.payload.filename || 'Attachment' }}</a>
                                <a
                                    v-if="m.message_type === 'location' && m.payload.latitude"
                                    :href="`https://maps.google.com/?q=${m.payload.latitude},${m.payload.longitude}`"
                                    target="_blank"
                                    class="underline"
                                >{{ m.payload.name || m.payload.address || 'Location' }}</a>
                                <p v-if="m.payload.text">{{ m.payload.text }}</p>
                                <p v-if="m.payload.template_name" class="text-[11px] opacity-80">Template · {{ m.payload.template_name }}</p>
                                <div class="mt-1 text-[11px] opacity-70">
                                    {{ senderLabel(m) }} · {{ messageStamp(m.created_at) }}
                                    <span v-if="m.direction === 'outbound'"> · {{ m.status }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <footer class="border-t border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <div class="flex rounded-md bg-zinc-100 p-1 text-xs dark:bg-zinc-800">
                                <button
                                    type="button"
                                    class="rounded-md px-2.5 py-1"
                                    :class="composerMode === 'freeform' ? 'bg-white font-medium shadow-sm dark:bg-zinc-900' : 'text-zinc-500'"
                                    :disabled="windowClosed"
                                    @click="setFreeform"
                                >{{ t('freeform') }}</button>
                                <button
                                    type="button"
                                    class="rounded-md px-2.5 py-1"
                                    :class="composerMode === 'template' ? 'bg-white font-medium shadow-sm dark:bg-zinc-900' : 'text-zinc-500'"
                                    @click="openTemplates"
                                >{{ t('template') }}</button>
                            </div>

                            <div class="ms-auto flex flex-wrap items-center gap-1.5">
                                <button type="button" class="rounded-md border border-zinc-300 px-2 py-1 text-xs font-medium text-zinc-600 dark:border-zinc-600" @click="patchStatus('closed')">{{ t('close') }}</button>
                                <button type="button" class="rounded-md border border-orange-300 px-2 py-1 text-xs font-medium text-orange-700" @click="patchStatus('pending')">{{ t('setPending') }}</button>
                                <button type="button" class="rounded-md border border-green-300 px-2 py-1 text-xs font-medium text-green-700" @click="patchStatus('open')">{{ t('open') }}</button>
                                <button type="button" class="rounded-md border border-zinc-300 px-2 py-1 text-xs font-medium dark:border-zinc-600" @click="assignMe">{{ t('assignMe') }}</button>
                                <button
                                    v-if="selected.assigned_to_me"
                                    type="button"
                                    class="rounded-md border border-green-300 bg-green-50 px-2 py-1 text-xs font-medium text-green-800"
                                    @click="unassign"
                                >{{ t('unassign') }}</button>
                                <div class="relative">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md border border-zinc-300 px-2 py-1 text-xs font-medium dark:border-zinc-600"
                                        @click="showAssign = !showAssign"
                                    >
                                        {{ t('assignTo') }}
                                        <ChevronDown class="size-3" />
                                    </button>
                                    <div
                                        v-if="showAssign"
                                        class="absolute end-0 z-20 mt-1 w-48 overflow-hidden rounded-md border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                                    >
                                        <button
                                            v-for="agent in agents"
                                            :key="agent.id"
                                            type="button"
                                            class="block w-full px-3 py-2 text-start text-xs hover:bg-green-50 dark:hover:bg-green-950/30"
                                            @click="assignTo(agent.id)"
                                        >
                                            {{ agent.name }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p v-if="windowClosed" class="mb-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                            {{ t('windowClosed') }}
                        </p>

                        <textarea
                            v-model="body"
                            dir="auto"
                            rows="3"
                            class="w-full rounded-md border border-zinc-200 p-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            :disabled="windowClosed || sending"
                            :placeholder="windowClosed ? t('pickTemplate') : ''"
                            @keydown="onComposerKey"
                        />

                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            <label class="flex items-center gap-1.5 text-xs text-green-600">
                                <input v-model="enterToSend" type="checkbox" class="accent-green-600" />
                                {{ t('enterToSend') }}
                            </label>
                            <button
                                type="button"
                                class="rounded-md border border-zinc-300 px-2 py-1 text-xs font-medium dark:border-zinc-600"
                                @click="showQuick = true"
                            >
                                {{ t('quickReplies') }}
                            </button>
                            <button
                                type="button"
                                class="ms-auto rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-50"
                                :disabled="sending || !body.trim() || windowClosed"
                                @click="sendText"
                            >
                                {{ t('send') }}
                            </button>
                        </div>
                    </footer>
                </template>
                <div v-else class="flex flex-1 items-center justify-center text-sm text-zinc-500">
                    {{ t('select') }}
                </div>
            </section>

            <aside
                v-if="selected"
                class="flex h-full w-72 shrink-0 flex-col border-s border-zinc-200 dark:border-zinc-800 xl:w-80"
            >
                <div class="border-b border-zinc-200 px-4 py-4 text-sm font-semibold dark:border-zinc-800">{{ t('bookings') }}</div>
                <div class="inbox-scroll flex-1 overflow-y-auto p-3">
                    <div v-if="bookingsLoading" class="flex flex-col items-center gap-2 py-8 text-xs text-zinc-500">
                        <span class="size-6 animate-spin rounded-full border-2 border-zinc-200 border-t-teal-700" />
                        {{ t('loading') }}
                    </div>
                    <p v-else-if="!bookings.length" class="px-1 py-8 text-sm text-zinc-500">{{ t('noBookings') }}</p>
                    <template v-else>
                        <article
                            v-for="b in bookings"
                            :key="b.id"
                            class="mb-2 rounded-md border border-zinc-200 p-3 dark:border-zinc-800"
                        >
                            <div class="font-medium text-zinc-900 dark:text-zinc-50">{{ b.order_number }}</div>
                            <div class="mt-0.5 text-xs text-zinc-500">{{ b.status }}<span v-if="b.order_type"> · {{ b.order_type }}</span></div>
                            <div class="mt-1 text-sm">{{ b.vehicle }}</div>
                            <div v-if="b.expected_return" dir="ltr" class="mt-1 text-xs text-zinc-500">{{ t('expected') }}: {{ b.expected_return }}</div>
                            <div class="mt-1 text-xs">{{ t('remaining') }}: <span dir="ltr">{{ b.remaining_amount }} {{ b.currency }}</span></div>
                            <a :href="b.url" class="mt-2 inline-block text-xs text-teal-700 hover:underline">{{ t('viewOrder') }}</a>
                        </article>
                    </template>
                </div>
            </aside>
        </div>

        <div v-if="showNew" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showNew = false">
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <h2 class="font-semibold">{{ t('newChat') }}</h2>
                    <button type="button" class="text-zinc-500" @click="showNew = false">{{ t('closeModal') }}</button>
                </div>
                <div class="p-4">
                    <label class="text-xs text-zinc-500">{{ t('phone') }}</label>
                    <input v-model="newPhone" dir="ltr" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" placeholder="+9665xxxxxxxx" />
                    <p class="mt-1 text-[11px] text-zinc-400">{{ t('e164') }}</p>
                </div>
                <div class="flex justify-end gap-2 border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <button type="button" class="rounded-md border border-zinc-300 px-3 py-1.5 text-xs font-medium" @click="showNew = false">{{ t('closeModal') }}</button>
                    <button type="button" class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700" @click="startChat">{{ t('start') }}</button>
                </div>
            </div>
        </div>

        <div v-if="showQuick" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showQuick = false">
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <h2 class="font-semibold">{{ t('quickReplies') }}</h2>
                    <button type="button" class="text-zinc-500" @click="showQuick = false">{{ t('closeModal') }}</button>
                </div>
                <div class="p-4">
                    <input v-model="quickQuery" class="mb-3 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700" placeholder="Search" />
                    <div class="max-h-56 space-y-1 overflow-y-auto">
                        <button
                            v-for="q in filteredQuick"
                            :key="q.id"
                            type="button"
                            class="flex w-full items-start justify-between gap-2 rounded-md px-2 py-2 text-start text-sm hover:bg-green-50 dark:hover:bg-green-950/30"
                            @click="useQuick(q.body)"
                        >
                            <span>
                                <span class="font-medium">{{ q.title }}</span>
                                <span class="mt-0.5 block text-xs text-zinc-500">{{ q.body }}</span>
                            </span>
                            <X class="size-3.5 shrink-0 text-zinc-400" @click.stop="deleteQuick(q.id)" />
                        </button>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <input v-model="newQuickTitle" class="w-28 rounded-md border border-zinc-300 px-2 py-1.5 text-xs dark:border-zinc-700" placeholder="Title" />
                        <input v-model="newQuickBody" class="flex-1 rounded-md border border-zinc-300 px-2 py-1.5 text-xs dark:border-zinc-700" placeholder="Body" />
                        <button type="button" class="rounded-md bg-green-600 px-2 text-xs text-white" @click="createQuick">+</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showTemplates" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showTemplates = false">
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <h2 class="font-semibold">{{ t('template') }}</h2>
                    <button type="button" class="text-zinc-500" @click="showTemplates = false">{{ t('closeModal') }}</button>
                </div>
                <div class="p-4 text-sm">
                    <p v-if="templatesLoading" class="text-xs text-zinc-400">{{ t('templatesLoading') }}</p>
                    <p v-else-if="templatesError" class="text-xs text-amber-800">
                        {{ templatesError }}
                        <a href="/settings/whatsapp-ai" class="mt-1 block text-teal-700 underline">{{ t('templatesSettings') }}</a>
                    </p>
                    <div v-if="templateStep === 1" class="max-h-72 space-y-1 overflow-y-auto">
                        <button
                            v-for="tpl in templates"
                            :key="tpl.name + tpl.language"
                            type="button"
                            class="block w-full rounded-md px-2 py-2 text-start hover:bg-green-50 dark:hover:bg-green-950/30"
                            :disabled="sending"
                            @click="chooseTemplate(tpl)"
                        >
                            <span class="font-medium">{{ tpl.name }}</span>
                            <span class="ms-2 text-xs text-zinc-500">{{ tpl.language }}</span>
                        </button>
                    </div>
                    <div v-else-if="templateStep === 2 && chosenTemplate">
                        <p class="mb-2 text-xs text-zinc-500">{{ chosenTemplate.name }} / {{ chosenTemplate.language }}</p>
                        <div v-for="ph in templateBodyPlaceholders(chosenTemplate)" :key="ph" class="mb-2">
                            <label class="text-[11px] text-zinc-500">{{ placeholderLabel(ph) }}</label>
                            <input v-model="templateParams[ph]" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700" />
                        </div>
                    </div>
                    <div v-else-if="templateStep === 3 && chosenTemplate">
                        <p class="mb-2 text-xs text-zinc-500">{{ t('headerMedia') }}</p>
                        <input v-model="headerLink" class="mb-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-xs dark:border-zinc-700" :placeholder="t('url')" />
                        <label class="mb-2 block text-xs text-zinc-500">
                            {{ t('upload') }}
                            <input type="file" class="mt-1 block text-xs" @change="(e) => { const f = (e.target as HTMLInputElement).files?.[0]; if (f) uploadHeader(f); }" />
                        </label>
                        <p class="mb-1 text-[11px] text-zinc-400">{{ t('recent') }}</p>
                        <div class="mb-2 flex flex-wrap gap-1">
                            <button
                                v-for="u in recentUploads"
                                :key="u.uuid"
                                type="button"
                                class="rounded-md border px-1.5 py-0.5 text-[10px] dark:border-zinc-700"
                                :class="headerUuid === u.uuid ? 'border-green-600' : 'border-zinc-300'"
                                @click="headerUuid = u.uuid; headerLink = u.url"
                            >
                                {{ u.name || u.uuid.slice(0, 8) }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <button type="button" class="rounded-md border border-zinc-300 px-3 py-1.5 text-xs font-medium" @click="showTemplates = false">{{ t('closeModal') }}</button>
                    <button v-if="templateStep === 2 && chosenTemplate && templateHasMediaHeader(chosenTemplate)" type="button" class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white" @click="templateStep = 3; loadRecentUploads()">{{ t('next') }}</button>
                    <button v-else-if="templateStep >= 2 && chosenTemplate" type="button" class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50" :disabled="sending" @click="sendTemplate">{{ t('send') }}</button>
                    <button v-if="templateStep > 1" type="button" class="rounded-md border border-zinc-300 px-3 py-1.5 text-xs font-medium" @click="templateStep = templateStep === 3 ? 2 : 1">{{ t('back') }}</button>
                </div>
            </div>
        </div>
    </InboxShell>
</template>
