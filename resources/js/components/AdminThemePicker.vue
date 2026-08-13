<script setup lang="ts">
import { useAdminTheme, type AdminTheme } from '@/composables/useAdminTheme';
import { Check } from 'lucide-vue-next';

const { adminTheme, updateAdminTheme } = useAdminTheme();

const themes: {
    value: AdminTheme;
    title: string;
    description: string;
    previewClass: string;
    accents: string[];
}[] = [
    {
        value: 'default',
        title: 'الافتراضي',
        description: 'الثيم الحالي للنظام بألوان عالم المغامرة',
        previewClass: 'from-teal-50 via-white to-slate-100',
        accents: ['bg-teal-600', 'bg-slate-300', 'bg-emerald-500'],
    },
    {
        value: 'salla',
        title: 'ثيم سلة',
        description: 'واجهة إدارية أنيقة مستوحاة من سلة، بنفس محتوى لوحة التحكم',
        previewClass: 'from-[#f3eeff] via-white to-[#f4f5f8]',
        accents: ['bg-[#6C2BD9]', 'bg-[#C4B5FD]', 'bg-[#A78BFA]'],
    },
];
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2">
        <button
            v-for="theme in themes"
            :key="theme.value"
            type="button"
            class="group relative overflow-hidden rounded-2xl border p-4 text-start transition-all"
            :class="
                adminTheme === theme.value
                    ? 'border-[var(--brand-primary)] bg-white shadow-md ring-2 ring-[var(--brand-primary)]/20 dark:bg-neutral-900'
                    : 'border-neutral-200 bg-white hover:border-neutral-300 hover:shadow-sm dark:border-neutral-700 dark:bg-neutral-900'
            "
            @click="updateAdminTheme(theme.value)"
        >
            <div
                class="mb-4 h-24 overflow-hidden rounded-xl bg-gradient-to-br ring-1 ring-black/5"
                :class="theme.previewClass"
            >
                <div class="flex h-full">
                    <div class="w-10 bg-white/90 p-1.5 shadow-sm">
                        <div class="mb-1.5 h-1.5 w-full rounded-full bg-neutral-200" />
                        <div
                            v-for="(accent, i) in theme.accents"
                            :key="i"
                            class="mb-1 h-1.5 w-full rounded-full opacity-90"
                            :class="accent"
                        />
                    </div>
                    <div class="flex flex-1 flex-col gap-1.5 p-2">
                        <div class="h-3 w-1/2 rounded-md bg-white/80 shadow-sm" />
                        <div class="grid flex-1 grid-cols-2 gap-1.5">
                            <div class="rounded-lg bg-white/90 shadow-sm" />
                            <div class="rounded-lg bg-white/70 shadow-sm" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ theme.title }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-neutral-500 dark:text-neutral-400">
                        {{ theme.description }}
                    </p>
                </div>
                <span
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full transition"
                    :class="
                        adminTheme === theme.value
                            ? 'bg-[var(--brand-primary)] text-white'
                            : 'bg-neutral-100 text-transparent dark:bg-neutral-800'
                    "
                >
                    <Check class="h-3.5 w-3.5" />
                </span>
            </div>
        </button>
    </div>
</template>
