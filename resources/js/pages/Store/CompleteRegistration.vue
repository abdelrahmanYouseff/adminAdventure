<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppFooter from '@/components/AppFooter.vue';
import StoreHeader from '@/components/StoreHeader.vue';

interface Profile {
    first_name: string;
    last_name: string;
    phone: string;
    email: string;
}

const props = defineProps<{
    profile: Profile;
    redirect: string;
}>();

const form = useForm({
    first_name: props.profile.first_name,
    last_name: props.profile.last_name,
    email: props.profile.email,
    redirect: props.redirect,
});

function submit() {
    form.post(route('store.complete-registration.store'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="إكمال التسجيل — عالم المغامرة">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div
        dir="rtl"
        class="min-h-screen bg-[#f4f6f8] pb-[env(safe-area-inset-bottom,0px)]"
        style="font-family: 'Noto Kufi Arabic', sans-serif"
    >
        <StoreHeader />

        <div class="mx-auto max-w-lg px-3.5 py-8 sm:px-6 sm:py-10">
            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-6 text-center">
                    <h1 class="text-xl font-bold text-neutral-900">أهلاً بك في عالم المغامرة</h1>
                    <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                        تم التحقق من رقم جوالك. أكمل بياناتك لإنشاء حسابك والمتابعة.
                    </p>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-neutral-800">الاسم الأول</label>
                        <input
                            v-model="form.first_name"
                            type="text"
                            autocomplete="given-name"
                            placeholder="مثال: أحمد"
                            class="min-h-12 w-full rounded-xl border border-neutral-200 px-3 text-sm text-neutral-900 outline-none transition focus:border-[#b565d8] focus:ring-2 focus:ring-[#b565d8]/20"
                        />
                        <p v-if="form.errors.first_name" class="mt-1.5 text-xs text-red-500">{{ form.errors.first_name }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-neutral-800">الاسم الأخير</label>
                        <input
                            v-model="form.last_name"
                            type="text"
                            autocomplete="family-name"
                            placeholder="مثال: محمد"
                            class="min-h-12 w-full rounded-xl border border-neutral-200 px-3 text-sm text-neutral-900 outline-none transition focus:border-[#b565d8] focus:ring-2 focus:ring-[#b565d8]/20"
                        />
                        <p v-if="form.errors.last_name" class="mt-1.5 text-xs text-red-500">{{ form.errors.last_name }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-neutral-800">رقم الجوال</label>
                        <div
                            class="flex overflow-hidden rounded-xl border border-neutral-200 bg-neutral-50"
                            dir="ltr"
                        >
                            <span
                                class="flex shrink-0 items-center border-e border-neutral-200 bg-neutral-100 px-3 text-sm font-semibold text-neutral-600"
                            >
                                +966
                            </span>
                            <input
                                :value="profile.phone"
                                type="tel"
                                readonly
                                class="min-h-12 w-full flex-1 cursor-not-allowed bg-transparent px-3 text-sm text-neutral-700 outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-neutral-800">البريد الإلكتروني</label>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="example@email.com"
                            class="min-h-12 w-full rounded-xl border border-neutral-200 px-3 text-sm text-neutral-900 outline-none transition focus:border-[#b565d8] focus:ring-2 focus:ring-[#b565d8]/20"
                            dir="ltr"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>

                    <button
                        type="submit"
                        class="flex min-h-12 w-full items-center justify-center rounded-xl bg-[#b565d8] text-base font-bold text-white transition hover:bg-[#a855d8] disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        إنشاء الحساب والمتابعة
                    </button>
                </form>
            </div>
        </div>

        <AppFooter />
    </div>
</template>
