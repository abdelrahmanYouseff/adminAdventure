<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Globe } from 'lucide-vue-next';
import { useI18n } from '../i18n';
import type { Locale } from '../i18n/messages';

const { locale, t, setLocale, locales, localeMeta } = useI18n();
const open = ref(false);
const root = ref<HTMLElement | null>(null);

const currentFlag = computed(() => localeMeta[locale.value].flag);

function toggle() {
    open.value = !open.value;
}

function choose(next: Locale) {
    setLocale(next);
    open.value = false;
}

function onDocumentClick(event: MouseEvent) {
    if (!root.value?.contains(event.target as Node)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="inline-flex h-11 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 text-sm text-slate-700 shadow-sm transition active:scale-[0.98] hover:bg-slate-50"
            :aria-label="t('language')"
            :aria-expanded="open"
            @click.stop="toggle"
        >
            <span class="text-lg leading-none" aria-hidden="true">{{ currentFlag }}</span>
            <Globe class="h-4 w-4 text-slate-500" />
        </button>

        <div
            v-if="open"
            class="absolute end-0 top-[calc(100%+0.4rem)] z-[80] min-w-[11.5rem] overflow-hidden rounded-2xl border border-slate-200 bg-white py-1 shadow-lg"
            role="menu"
        >
            <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                {{ t('language') }}
            </p>
            <button
                v-for="code in locales"
                :key="code"
                type="button"
                class="flex w-full items-center gap-2.5 px-3 py-2.5 text-sm transition"
                :class="locale === code
                    ? 'bg-sky-50 font-semibold text-sky-800'
                    : 'text-slate-700 hover:bg-slate-50'"
                role="menuitem"
                @click.stop="choose(code)"
            >
                <span class="text-lg leading-none" aria-hidden="true">{{ localeMeta[code].flag }}</span>
                <span>{{ t(localeMeta[code].labelKey) }}</span>
            </button>
        </div>
    </div>
</template>
