<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Monitor, Moon, Sun } from 'lucide-vue-next';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'فاتح' },
    { value: 'dark', Icon: Moon, label: 'داكن' },
    { value: 'system', Icon: Monitor, label: 'تلقائي' },
] as const;
</script>

<template>
    <div class="inline-flex gap-1 rounded-xl bg-neutral-100 p-1 dark:bg-neutral-800">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            @click="updateAppearance(value)"
            :class="[
                'flex items-center rounded-lg px-3.5 py-1.5 transition-colors',
                appearance === value
                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            <component :is="Icon" class="h-4 w-4" />
            <span class="ms-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
