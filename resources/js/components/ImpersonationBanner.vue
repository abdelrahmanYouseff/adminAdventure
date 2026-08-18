<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Undo2 } from 'lucide-vue-next';

interface ImpersonationState {
    active: boolean;
    admin_name: string | null;
    as_name: string | null;
    as_role: string | null;
}

const page = usePage();

const impersonation = computed(() => {
    const value = page.props.impersonation as ImpersonationState | null | undefined;
    return value?.active ? value : null;
});

function stopImpersonation() {
    router.post(route('impersonation.stop'));
}
</script>

<template>
    <div
        v-if="impersonation"
        class="relative z-[80] flex items-center justify-between gap-3 bg-amber-500 px-3 py-2 text-amber-950 sm:px-4"
        dir="rtl"
    >
        <p class="min-w-0 text-xs font-semibold sm:text-sm">
            دخول سريع كـ
            <span class="font-extrabold">{{ impersonation.as_name }}</span>
            <span v-if="impersonation.as_role" class="font-medium opacity-80">
                ({{ impersonation.as_role }})
            </span>
        </p>
        <button
            type="button"
            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-amber-950 px-2.5 py-1.5 text-xs font-bold text-white transition hover:bg-black sm:px-3"
            @click="stopImpersonation"
        >
            <Undo2 class="size-3.5" />
            العودة للأدمن
        </button>
    </div>
</template>
