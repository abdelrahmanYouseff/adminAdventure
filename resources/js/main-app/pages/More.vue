<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ExternalLink, LogOut, MonitorSmartphone } from 'lucide-vue-next';
import MainAppLayout from '../layouts/MainAppLayout.vue';
import { moduleIcon, moduleTone, type AppModule } from '../lib/modules';

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        role: string;
        role_label: string;
    };
    modules: AppModule[];
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
                <a
                    href="/dashboard"
                    class="flex items-center gap-3 rounded-2xl border border-white/70 bg-white p-4 shadow-sm"
                >
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                        <MonitorSmartphone class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-slate-900">لوحة التحكم الكاملة</p>
                        <p class="text-xs text-slate-500">النسخة المكتبية بكل الأدوات</p>
                    </div>
                    <ExternalLink class="h-4 w-4 text-slate-300" />
                </a>

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

            <h2 class="mb-2 text-sm font-bold text-slate-800">اختصار الوحدات</h2>
            <div class="grid grid-cols-3 gap-2.5">
                <Link
                    v-for="mod in modules"
                    :key="mod.key"
                    :href="`/main-app/m/${mod.key}`"
                    class="flex flex-col items-center gap-2 rounded-2xl border border-white/70 bg-white p-3 text-center shadow-sm"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl"
                        :class="moduleTone(mod.tone).icon"
                    >
                        <component :is="moduleIcon(mod.icon)" class="h-4 w-4" />
                    </div>
                    <span class="line-clamp-2 text-[11px] font-semibold leading-tight text-slate-700">
                        {{ mod.title }}
                    </span>
                </Link>
            </div>
        </div>
    </MainAppLayout>
</template>
