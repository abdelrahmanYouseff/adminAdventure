<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed } from 'vue';
import { useAdminTheme } from '@/composables/useAdminTheme';

interface Props {
    user: User;
    showEmail?: boolean;
    profileStyle?: boolean;
    metaLine?: string;
    onDark?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    profileStyle: false,
    metaLine: undefined,
    onDark: false,
});

const { getInitials } = useInitials();
const { isSalla } = useAdminTheme();
const darkChrome = computed(() => Boolean(props.onDark && isSalla.value));

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
            :class="[
                profileStyle ? 'rounded-full' : 'rounded-lg',
                isSalla && onDark
                    ? 'bg-[#7048e8]/25 text-white'
                    : 'bg-[var(--nav-accent-soft)] text-[var(--nav-accent-text)] dark:bg-[var(--nav-accent-soft-dark)] dark:text-[var(--nav-accent)]',
            ]"
        >
            {{ getInitials(displayName) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-start text-sm leading-tight">
        <span :class="darkChrome ? 'truncate font-semibold text-white' : 'truncate font-semibold text-gray-800 dark:text-neutral-100'">{{ displayName }}</span>
        <span
            v-if="metaLine"
            class="mt-0.5 truncate text-[11px] tracking-wide group-data-[collapsible=icon]:hidden"
            :class="darkChrome ? 'text-white/40' : 'text-gray-400'"
        >
            {{ metaLine }}
        </span>
        <span v-else-if="showEmail" class="truncate text-xs text-muted-foreground">{{ user.email }}</span>
    </div>
</template>
