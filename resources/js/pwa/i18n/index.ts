import { computed, ref } from 'vue';
import {
    LOCALES,
    STORAGE_KEY,
    localeMeta,
    messages,
    type Locale,
    type MessageKey,
} from './messages';

function readStoredLocale(): Locale {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored && LOCALES.includes(stored as Locale)) {
            return stored as Locale;
        }
    } catch {
        // ignore
    }

    return 'ar';
}

const locale = ref<Locale>(readStoredLocale());

export function applyDocumentLocale(next: Locale = locale.value) {
    if (typeof document === 'undefined') {
        return;
    }

    const meta = localeMeta[next];
    document.documentElement.lang = next;
    document.documentElement.dir = meta.dir;
    document.documentElement.dataset.locale = next;
}

export function setLocale(next: Locale) {
    if (!LOCALES.includes(next)) {
        return;
    }

    locale.value = next;

    try {
        localStorage.setItem(STORAGE_KEY, next);
    } catch {
        // ignore
    }

    applyDocumentLocale(next);
}

export function t(key: MessageKey, params?: Record<string, string | number>): string {
    const table = messages[locale.value] ?? messages.ar;
    let text = table[key] ?? messages.ar[key] ?? String(key);

    if (params) {
        for (const [name, value] of Object.entries(params)) {
            text = text.replaceAll(`{${name}}`, String(value));
        }
    }

    return text;
}

export function useI18n() {
    const dir = computed(() => localeMeta[locale.value].dir);
    const isRtl = computed(() => dir.value === 'rtl');

    return {
        locale,
        dir,
        isRtl,
        t,
        setLocale,
        locales: LOCALES,
        localeMeta,
    };
}

applyDocumentLocale();
