<script setup lang="ts">
import { computed } from 'vue';
import { useAdminTheme } from '@/composables/useAdminTheme';

const props = defineProps<{
    admin?: boolean;
}>();

const { isSalla } = useAdminTheme();
const sallaChrome = computed(() => Boolean(props.admin && isSalla.value));
</script>

<template>
    <div class="flex min-w-0 items-center" :class="sallaChrome ? 'gap-3' : 'justify-center'">
        <img
            src="/GaT1PM0T9DJyKdgu7dSRepznpZXRAF5uXqMpAUxZ.avif"
            alt="Logo"
            :class="sallaChrome ? 'h-10 w-10 rounded-xl object-cover ring-1 ring-white/10' : 'h-16 w-16 rounded-md'"
        />
        <div v-if="sallaChrome" class="min-w-0 text-start group-data-[collapsible=icon]:hidden">
            <p class="truncate text-sm font-bold text-white">عالم المغامرة</p>
            <p class="truncate text-[11px] text-white/45">لوحة التحكم</p>
        </div>
    </div>
</template>
