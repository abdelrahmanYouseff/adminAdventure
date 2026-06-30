<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Box, LogOut, ShoppingCart, User } from 'lucide-vue-next';

const menuItems = [
    { label: 'الطلبات', href: route('store.orders'), icon: Box },
    {
        label: 'طلبات بانتظار الدفع',
        href: route('store.orders', { payment_status: 'pending' }),
        icon: ShoppingCart,
    },
    { label: 'حسابي', href: route('store.account'), icon: User },
] as const;

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="inline-flex min-h-9 min-w-9 touch-manipulation items-center justify-center rounded-full border border-neutral-200 bg-white text-[#b565d8] transition hover:bg-[#b565d8]/5 active:scale-[0.98]"
                aria-label="قائمة الحساب"
            >
                <User class="h-5 w-5 shrink-0" />
            </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            :side-offset="8"
            dir="rtl"
            class="z-[60] min-w-[15rem] rounded-xl border border-neutral-100 bg-white p-2 text-end shadow-lg"
            style="font-family: 'Noto Kufi Arabic', sans-serif"
        >
            <DropdownMenuItem
                v-for="item in menuItems"
                :key="item.label"
                as-child
                class="rounded-lg p-0 focus:bg-[#b565d8]/5"
            >
                <Link
                    :href="item.href"
                    class="flex w-full items-center justify-start gap-3 px-3 py-2.5 text-sm font-medium text-[#b565d8] outline-none"
                >
                    <component :is="item.icon" class="h-5 w-5 shrink-0" />
                    <span class="flex-1 text-end">{{ item.label }}</span>
                </Link>
            </DropdownMenuItem>

            <DropdownMenuItem as-child class="rounded-lg p-0 focus:bg-[#b565d8]/5">
                <button
                    type="button"
                    class="flex w-full items-center justify-start gap-3 px-3 py-2.5 text-sm font-medium text-[#b565d8] outline-none"
                    @click="logout"
                >
                    <LogOut class="h-5 w-5 shrink-0" />
                    <span class="flex-1 text-end">تسجيل الخروج</span>
                </button>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
