<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { LogOut } from 'lucide-vue-next';
import MainAppLayout from '../layouts/MainAppLayout.vue';

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        role: string;
        role_label: string;
    };
}

defineProps<Props>();

const logoutForm = useForm({});

function logout() {
    logoutForm.post('/main-app/logout');
}
</script>

<template>
    <Head title="المزيد" />

    <MainAppLayout active-nav="more">
        <div class="px-4 pt-5" style="padding-top: max(1.25rem, env(safe-area-inset-top))">
            <header class="mb-5 overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-teal-800 via-teal-700 to-emerald-700 p-5 text-white shadow-lg shadow-teal-900/20">
                <p class="text-xs font-semibold text-teal-100/80">حسابك</p>
                <h1 class="mt-1 text-2xl font-extrabold">{{ user.name }}</h1>
                <p class="mt-1 text-sm text-teal-50/85" dir="ltr">{{ user.email }}</p>
                <span class="mt-3 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold ring-1 ring-white/20">
                    {{ user.role_label }}
                </span>
            </header>

            <section class="mb-4 space-y-2.5">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-2xl border border-rose-100 bg-rose-50/80 p-4 text-start text-rose-700 shadow-sm disabled:opacity-60"
                    :disabled="logoutForm.processing"
                    @click="logout"
                >
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-rose-600">
                        <LogOut class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold">تسجيل الخروج</p>
                        <p class="text-xs text-rose-500/80">إنهاء الجلسة من التطبيق</p>
                    </div>
                </button>
            </section>
        </div>
    </MainAppLayout>
</template>
