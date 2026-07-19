<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
});

function submit() {
    form.post(route('pwa.login.store'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="تسجيل الدخول" />

    <div class="relative flex min-h-dvh flex-col bg-[#f5f7fb] px-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-[max(1.5rem,env(safe-area-inset-top))]">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -left-20 top-10 h-56 w-56 rounded-full bg-sky-200/50 blur-3xl" />
            <div class="absolute -right-16 bottom-24 h-64 w-64 rounded-full bg-orange-100/60 blur-3xl" />
        </div>

        <div class="relative mx-auto flex w-full max-w-md flex-1 flex-col justify-center">
            <div class="mb-10 text-center">
                <img src="/assets/logo.png" alt="عالم المغامرة" class="mx-auto mb-4 h-14 w-auto object-contain" />
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">تطبيق العمال</h1>
                <p class="mt-2 text-sm text-slate-500">سجّل الدخول لمتابعة أوامر العمل القائمة</p>
            </div>

            <form class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" @submit.prevent="submit">
                <div class="space-y-2">
                    <label for="email" class="block text-sm font-medium text-slate-700">البريد الإلكتروني</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        required
                        dir="ltr"
                        class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-base text-slate-900 outline-none ring-sky-400/40 placeholder:text-slate-400 focus:border-sky-400 focus:ring-2"
                        placeholder="worker@example.com"
                    />
                    <p v-if="form.errors.email" class="text-sm text-red-600">{{ form.errors.email }}</p>
                </div>

                <div class="space-y-2">
                    <label for="password" class="block text-sm font-medium text-slate-700">كلمة المرور</label>
                    <div class="relative">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            required
                            dir="ltr"
                            class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 pe-12 text-base text-slate-900 outline-none ring-sky-400/40 placeholder:text-slate-400 focus:border-sky-400 focus:ring-2"
                            placeholder="••••••••"
                        />
                        <button
                            type="button"
                            class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                            @click="showPassword = !showPassword"
                        >
                            <Eye v-if="!showPassword" class="h-5 w-5" />
                            <EyeOff v-else class="h-5 w-5" />
                        </button>
                    </div>
                    <p v-if="form.errors.password" class="text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <button
                    type="submit"
                    class="flex h-12 w-full items-center justify-center rounded-xl bg-sky-600 text-base font-semibold text-white transition active:scale-[0.98] hover:bg-sky-500 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'جاري الدخول...' : 'دخول' }}
                </button>
            </form>
        </div>
    </div>
</template>
