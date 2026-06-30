<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppFooter from '@/components/AppFooter.vue';
import StoreHeader from '@/components/StoreHeader.vue';

interface Profile {
    first_name: string;
    last_name: string;
    phone: string;
    email: string;
    date_of_birth: string | null;
    gender: 'male' | 'female' | null;
}

const props = defineProps<{
    profile: Profile;
}>();

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);

const form = useForm({
    first_name: props.profile.first_name,
    last_name: props.profile.last_name,
    phone: props.profile.phone,
    email: props.profile.email,
    date_of_birth: props.profile.date_of_birth ?? '',
    gender: props.profile.gender as 'male' | 'female' | null,
});

function submit() {
    form.patch(route('store.account.update'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="حسابي — عالم المغامرة">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div
        dir="rtl"
        class="min-h-screen bg-[#f4f6f8] pb-[env(safe-area-inset-bottom,0px)]"
        style="font-family: 'Noto Kufi Arabic', sans-serif"
    >
        <StoreHeader :show-login-button="true" />

        <div class="mx-auto max-w-lg px-3.5 py-8 sm:px-6 sm:py-10">
            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
                <h1 class="mb-6 text-center text-xl font-bold text-neutral-900">حسابي</h1>

                <p v-if="successMessage" class="mb-4 rounded-xl bg-green-50 px-4 py-3 text-center text-sm font-medium text-green-700">
                    {{ successMessage }}
                </p>

                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-neutral-800">الاسم الأول</label>
                        <input
                            v-model="form.first_name"
                            type="text"
                            autocomplete="given-name"
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
                            class="min-h-12 w-full rounded-xl border border-neutral-200 px-3 text-sm text-neutral-900 outline-none transition focus:border-[#b565d8] focus:ring-2 focus:ring-[#b565d8]/20"
                        />
                        <p v-if="form.errors.last_name" class="mt-1.5 text-xs text-red-500">{{ form.errors.last_name }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-neutral-800">رقم الجوال</label>
                        <div
                            class="flex overflow-hidden rounded-xl border border-neutral-200 bg-white focus-within:border-[#b565d8] focus-within:ring-2 focus-within:ring-[#b565d8]/20"
                            dir="ltr"
                        >
                            <span
                                class="flex shrink-0 items-center gap-1.5 border-e border-neutral-200 bg-neutral-50 px-3 text-sm font-semibold text-neutral-600"
                            >
                                <span aria-hidden="true">🇸🇦</span>
                                +966
                            </span>
                            <input
                                v-model="form.phone"
                                type="tel"
                                inputmode="numeric"
                                autocomplete="tel"
                                placeholder="53 581 5072"
                                class="min-h-12 w-full flex-1 bg-transparent px-3 text-sm text-neutral-900 outline-none placeholder:text-neutral-400"
                            />
                        </div>
                        <p v-if="form.errors.phone" class="mt-1.5 text-xs text-red-500">{{ form.errors.phone }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-neutral-800">البريد الإلكتروني</label>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            class="min-h-12 w-full rounded-xl border border-neutral-200 px-3 text-sm text-neutral-900 outline-none transition focus:border-[#b565d8] focus:ring-2 focus:ring-[#b565d8]/20"
                            dir="ltr"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-neutral-800">تاريخ الميلاد</label>
                        <input
                            v-model="form.date_of_birth"
                            type="date"
                            class="min-h-12 w-full rounded-xl border border-neutral-200 px-3 text-sm text-neutral-900 outline-none transition focus:border-[#b565d8] focus:ring-2 focus:ring-[#b565d8]/20"
                            dir="ltr"
                        />
                        <p v-if="form.errors.date_of_birth" class="mt-1.5 text-xs text-red-500">{{ form.errors.date_of_birth }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-neutral-800">الجنس</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                class="min-h-12 rounded-xl border px-3 text-sm font-semibold transition"
                                :class="
                                    form.gender === 'male'
                                        ? 'border-[#b565d8] bg-[#b565d8]/5 text-[#b565d8]'
                                        : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50'
                                "
                                @click="form.gender = 'male'"
                            >
                                ذكر
                            </button>
                            <button
                                type="button"
                                class="min-h-12 rounded-xl border px-3 text-sm font-semibold transition"
                                :class="
                                    form.gender === 'female'
                                        ? 'border-[#b565d8] bg-[#b565d8]/5 text-[#b565d8]'
                                        : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50'
                                "
                                @click="form.gender = 'female'"
                            >
                                أنثى
                            </button>
                        </div>
                        <p v-if="form.errors.gender" class="mt-1.5 text-xs text-red-500">{{ form.errors.gender }}</p>
                    </div>

                    <button
                        type="submit"
                        class="flex min-h-12 w-full items-center justify-center rounded-xl bg-neutral-400 text-base font-bold text-white transition hover:bg-neutral-500 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        حفظ
                    </button>
                </form>
            </div>
        </div>

        <AppFooter />
    </div>
</template>
