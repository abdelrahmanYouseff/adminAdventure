<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, LoaderCircle, LockKeyhole, Mail } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    status?: string | null;
}

defineProps<Props>();

const showPassword = ref(false);
const form = useForm({
    email: '',
    password: '',
});

function submit() {
    form.post('/main-app/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="تسجيل الدخول" />

    <div class="relative min-h-dvh overflow-hidden bg-[#0b1f1c] text-white">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_10%,rgba(45,212,191,0.28),transparent_40%),radial-gradient(circle_at_90%_20%,rgba(16,185,129,0.18),transparent_35%),linear-gradient(180deg,#0b1f1c_0%,#12332d_55%,#0f766e_140%)]" />
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/35 to-transparent" />

        <div
            class="relative mx-auto flex min-h-dvh w-full max-w-lg flex-col px-5"
            style="padding-top: max(1.5rem, env(safe-area-inset-top)); padding-bottom: max(1.5rem, env(safe-area-inset-bottom))"
        >
            <div class="flex flex-1 flex-col justify-center py-8">
                <div class="mb-10 text-center">
                    <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-[1.75rem] bg-white/10 p-3 shadow-lg shadow-teal-950/40 ring-1 ring-white/20 backdrop-blur">
                        <img src="/assets/logo.png" alt="عالم المغامرة" class="h-full w-full object-contain" />
                    </div>
                    <p class="text-xs font-semibold tracking-[0.22em] text-teal-200/90 uppercase">Workers Manager</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight">عالم المغامرة</h1>
                    <p class="mt-2 text-sm text-teal-50/75">تطبيق أوامر العمل — لمدير العمال فقط</p>
                </div>

                <div class="rounded-[1.75rem] border border-white/15 bg-white/95 p-5 text-slate-900 shadow-2xl shadow-black/30 backdrop-blur">
                    <h2 class="text-lg font-bold text-slate-900">تسجيل الدخول</h2>
                    <p class="mt-1 text-sm text-slate-500">ادخل بحساب البريد وكلمة المرور</p>

                    <p v-if="status" class="mt-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                        {{ status }}
                    </p>

                    <form class="mt-5 space-y-4" @submit.prevent="submit">
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-600">البريد الإلكتروني</label>
                            <div class="flex h-12 items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 focus-within:border-teal-400 focus-within:ring-2 focus-within:ring-teal-100">
                                <Mail class="h-4 w-4 text-slate-400" />
                                <input
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="username"
                                    required
                                    class="w-full bg-transparent text-sm outline-none"
                                    placeholder="name@company.com"
                                    dir="ltr"
                                />
                            </div>
                            <p v-if="form.errors.email" class="text-xs text-rose-600">{{ form.errors.email }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-600">كلمة المرور</label>
                            <div class="flex h-12 items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 focus-within:border-teal-400 focus-within:ring-2 focus-within:ring-teal-100">
                                <LockKeyhole class="h-4 w-4 text-slate-400" />
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    required
                                    class="w-full bg-transparent text-sm outline-none"
                                    placeholder="••••••••"
                                    dir="ltr"
                                />
                                <button
                                    type="button"
                                    class="rounded-lg p-1 text-slate-400 hover:bg-slate-200/70 hover:text-slate-700"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="text-xs text-rose-600">{{ form.errors.password }}</p>
                        </div>

                        <button
                            type="submit"
                            class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-teal-700 text-sm font-bold text-white shadow-lg shadow-teal-900/20 transition hover:bg-teal-800 disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                            {{ form.processing ? 'جاري الدخول...' : 'دخول التطبيق' }}
                        </button>
                    </form>
                </div>

                <p class="mt-6 text-center text-xs text-teal-100/70">
                    العمال يستخدمون
                    <a href="/worker-app" class="font-semibold text-white underline-offset-2 hover:underline">تطبيق العمال</a>
                </p>
            </div>
        </div>
    </div>
</template>
