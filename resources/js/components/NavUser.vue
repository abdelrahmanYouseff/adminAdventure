<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { type User } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import UserMenuContent from './UserMenuContent.vue';

const page = usePage();
const user = page.props.auth?.user as User | null;
const { isMobile, state } = useSidebar();

const now = ref(new Date());
let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    timer = setInterval(() => {
        now.value = new Date();
    }, 30_000);
});

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
    }
});

const timeLabel = computed(() =>
    new Intl.DateTimeFormat('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(now.value),
);

const dateLabel = computed(() =>
    new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    })
        .format(now.value)
        .toUpperCase(),
);
</script>

<template>
    <SidebarMenu v-if="user">
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="h-auto rounded-xl px-2 py-2.5 hover:bg-gray-50 data-[state=open]:bg-gray-50 dark:hover:bg-neutral-900 dark:data-[state=open]:bg-neutral-900"
                    >
                        <UserInfo
                            :user="user"
                            profile-style
                            :meta-line="`${timeLabel}  ${dateLabel}`"
                        />
                        <ChevronLeft class="ms-auto size-4 shrink-0 text-gray-400 group-data-[collapsible=icon]:hidden" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="isMobile ? 'bottom' : state === 'collapsed' ? 'left' : 'bottom'"
                    align="end"
                    :side-offset="4"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
