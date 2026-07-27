<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed } from 'vue';

interface Props {
    user: User;
    showEmail?: boolean;
    profileStyle?: boolean;
    metaLine?: string;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    profileStyle: false,
    metaLine: undefined,
});

const { getInitials } = useInitials();

const showAvatar = computed(() => props.user.avatar && props.user.avatar !== '');

const displayName = computed(() => (props.user.name?.trim() || props.user.email || '').trim() || '—');
</script>

<template>
    <Avatar
        class="overflow-hidden"
        :class="profileStyle ? 'h-9 w-9 rounded-full' : 'h-8 w-8 rounded-lg'"
    >
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="displayName" />
        <AvatarFallback
            class="bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300"
            :class="profileStyle ? 'rounded-full' : 'rounded-lg'"
        >
            {{ getInitials(displayName) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-start text-sm leading-tight">
        <span class="truncate font-semibold text-gray-800 dark:text-neutral-100">{{ displayName }}</span>
        <span
            v-if="metaLine"
            class="mt-0.5 truncate text-[11px] tracking-wide text-gray-400 group-data-[collapsible=icon]:hidden"
        >
            {{ metaLine }}
        </span>
        <span v-else-if="showEmail" class="truncate text-xs text-muted-foreground">{{ user.email }}</span>
    </div>
</template>
