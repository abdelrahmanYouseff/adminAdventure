<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight, Eye, EyeOff, LoaderCircle } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import WorkerLanguageSwitcher from '../components/WorkerLanguageSwitcher.vue';
import { useI18n } from '../i18n';

const { t, isRtl } = useI18n();

type Mode = 'phone' | 'otp' | 'email';

const mode = ref<Mode>('phone');
const phone = ref('');
const phoneError = ref<string | null>(null);
const normalizedPhone = ref('');
const formError = ref<string | null>(null);
const showPassword = ref(false);

const otpLength = 4;
const otpDigits = ref<string[]>(Array.from({ length: otpLength }, () => ''));
const otpError = ref<string | null>(null);
const otpInputRefs = ref<(HTMLInputElement | null)[]>([]);
const resendSeconds = ref(0);
let resendInterval: ReturnType<typeof setInterval> | null = null;

const emailForm = useForm({
    email: '',
    password: '',
});

const sendOtpForm = useForm({
    phone: '',
});

const verifyOtpForm = useForm({
    phone: '',
    code: '',
});

const pageTitle = computed(() => t('login_title'));

const formattedPhone = computed(() =>
    normalizedPhone.value ? `+966 ${normalizedPhone.value}` : '',
);

const resendTimerLabel = computed(() => {
    const seconds = resendSeconds.value;
    const mm = String(Math.floor(seconds / 60)).padStart(2, '0');
    const ss = String(seconds % 60).padStart(2, '0');
    return `${mm}:${ss}`;
});

const otpComplete = computed(() => otpDigits.value.every((d) => d.length === 1));

function normalizePhone(raw: string): string {
    let digits = raw.replace(/\D/g, '');
    if (digits.startsWith('966')) digits = digits.slice(3);
    if (digits.startsWith('0')) digits = digits.slice(1);
    return digits;
}

function stopResendTimer() {
    if (resendInterval) {
        clearInterval(resendInterval);
        resendInterval = null;
    }
    resendSeconds.value = 0;
}

function startResendTimer(seconds = 25) {
    stopResendTimer();
    resendSeconds.value = seconds;
    resendInterval = setInterval(() => {
        if (resendSeconds.value <= 1) {
            stopResendTimer();
        } else {
            resendSeconds.value -= 1;
        }
    }, 1000);
}

function resetOtpState() {
    otpDigits.value = Array.from({ length: otpLength }, () => '');
    otpError.value = null;
    verifyOtpForm.clearErrors();
    stopResendTimer();
}

function goToPhoneMode() {
    mode.value = 'phone';
    resetOtpState();
    formError.value = null;
}

function goToEmailMode() {
    mode.value = 'email';
    formError.value = null;
    emailForm.clearErrors();
}

function goToOtpMode() {
    mode.value = 'otp';
    resetOtpState();
    startResendTimer();
    nextTick(() => otpInputRefs.value[0]?.focus());
}

function submitPhone() {
    const digits = normalizePhone(phone.value);
    if (!/^5\d{8}$/.test(digits)) {
        phoneError.value = t('phone_invalid');
        return;
    }

    phoneError.value = null;
    formError.value = null;
    normalizedPhone.value = digits;
    sendOtpForm.phone = digits;

    sendOtpForm.post('/worker-app/login/otp/send', {
        preserveScroll: true,
        onSuccess: () => goToOtpMode(),
        onError: (errors) => {
            phoneError.value = errors.phone || t('otp_send_failed');
        },
    });
}

function resendOtp() {
    if (resendSeconds.value > 0 || sendOtpForm.processing) return;

    sendOtpForm.phone = normalizedPhone.value;
    sendOtpForm.post('/worker-app/login/otp/send', {
        preserveScroll: true,
        onSuccess: () => {
            otpError.value = null;
            verifyOtpForm.clearErrors();
            startResendTimer();
        },
        onError: (errors) => {
            otpError.value = errors.phone || t('otp_send_failed');
        },
    });
}

function setOtpRef(el: HTMLInputElement | null, index: number) {
    otpInputRefs.value[index] = el;
}

function onOtpInput(index: number, event: Event) {
    const input = event.target as HTMLInputElement;
    const digit = input.value.replace(/\D/g, '').slice(-1);
    otpDigits.value[index] = digit;
    input.value = digit;
    otpError.value = null;

    if (digit && index < otpLength - 1) {
        otpInputRefs.value[index + 1]?.focus();
    }
}

function onOtpKeydown(index: number, event: KeyboardEvent) {
    if (event.key === 'Backspace' && !otpDigits.value[index] && index > 0) {
        otpInputRefs.value[index - 1]?.focus();
    }
    if (event.key === 'Enter' && otpComplete.value) {
        submitOtp();
    }
}

function onOtpPaste(event: ClipboardEvent) {
    event.preventDefault();
    const pasted = event.clipboardData?.getData('text').replace(/\D/g, '').slice(0, otpLength) ?? '';
    if (!pasted) return;

    pasted.split('').forEach((digit, index) => {
        if (index < otpLength) otpDigits.value[index] = digit;
    });

    const focusIndex = Math.min(pasted.length, otpLength - 1);
    nextTick(() => otpInputRefs.value[focusIndex]?.focus());
}

function submitOtp() {
    if (!otpComplete.value) {
        otpError.value = t('otp_incomplete');
        return;
    }

    verifyOtpForm.phone = normalizedPhone.value;
    verifyOtpForm.code = otpDigits.value.join('');

    verifyOtpForm.post('/worker-app/login/otp/verify', {
        preserveScroll: true,
        onError: (errors) => {
            otpError.value = errors.code || errors.phone || t('otp_invalid');
        },
    });
}

function submitEmail() {
    formError.value = null;

    emailForm.post('/worker-app/login', {
        preserveScroll: true,
        onError: (errors) => {
            formError.value = errors.email || errors.password || t('login_failed');
        },
        onFinish: () => {
            emailForm.processing = false;
        },
    });
}

onBeforeUnmount(() => stopResendTimer());
</script>

<template>
    <Head :title="pageTitle" />

    <div class="relative flex min-h-dvh flex-col bg-[#f5f7fb] px-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-[max(1.5rem,env(safe-area-inset-top))]">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -left-20 top-10 h-56 w-56 rounded-full bg-sky-200/50 blur-3xl" />
            <div class="absolute -right-16 bottom-24 h-64 w-64 rounded-full bg-orange-100/60 blur-3xl" />
        </div>

        <div class="relative mx-auto flex w-full max-w-md justify-end">
            <WorkerLanguageSwitcher />
        </div>

        <div class="relative mx-auto flex w-full max-w-md flex-1 flex-col justify-center">
            <div class="mb-10 text-center">
                <img src="/assets/logo.png" :alt="t('app_name')" class="mx-auto mb-4 h-14 w-auto object-contain" />
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ t('worker_app') }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ t('login_subtitle') }}</p>
            </div>

            <div class="relative space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <button
                    v-if="mode === 'otp'"
                    type="button"
                    class="absolute end-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500"
                    :aria-label="t('back')"
                    @click="goToPhoneMode"
                >
                    <ArrowRight v-if="isRtl" class="h-4 w-4" />
                    <ArrowRight v-else class="h-4 w-4 rotate-180" />
                </button>

                <!-- Phone -->
                <div v-if="mode === 'phone'" class="space-y-4">
                    <div
                        v-if="phoneError"
                        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700"
                    >
                        {{ phoneError }}
                    </div>

                    <div class="space-y-2">
                        <label for="phone" class="block text-sm font-medium text-slate-700">{{ t('phone') }}</label>
                        <div
                            class="flex overflow-hidden rounded-xl border border-slate-200 bg-white focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-400/40"
                            dir="ltr"
                        >
                            <span class="flex shrink-0 items-center border-e border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-600">
                                +966
                            </span>
                            <input
                                id="phone"
                                v-model="phone"
                                type="tel"
                                inputmode="numeric"
                                autocomplete="tel"
                                placeholder="5XXXXXXXX"
                                class="h-12 w-full flex-1 bg-transparent px-3 text-base text-slate-900 outline-none placeholder:text-slate-400"
                                :disabled="sendOtpForm.processing"
                                @keyup.enter="submitPhone"
                            />
                        </div>
                    </div>

                    <button
                        type="button"
                        class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-sky-600 text-base font-semibold text-white transition active:scale-[0.98] hover:bg-sky-500 disabled:opacity-60"
                        :disabled="sendOtpForm.processing"
                        @click="submitPhone"
                    >
                        <LoaderCircle v-if="sendOtpForm.processing" class="h-5 w-5 animate-spin" />
                        {{ sendOtpForm.processing ? t('sending_otp') : t('login') }}
                    </button>

                    <button
                        type="button"
                        class="flex h-12 w-full items-center justify-center rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="goToEmailMode"
                    >
                        {{ t('login_with_email') }}
                    </button>
                </div>

                <!-- OTP -->
                <div v-else-if="mode === 'otp'" class="space-y-5 pt-6 text-center">
                    <p class="text-sm leading-relaxed text-slate-600">{{ t('otp_sent_hint') }}</p>
                    <p class="text-lg font-bold text-slate-900" dir="ltr">{{ formattedPhone }}</p>

                    <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3" dir="ltr" @paste="onOtpPaste">
                        <input
                            v-for="(_, index) in otpDigits"
                            :key="index"
                            :ref="(el) => setOtpRef(el as HTMLInputElement | null, index)"
                            type="text"
                            inputmode="numeric"
                            maxlength="1"
                            autocomplete="one-time-code"
                            class="h-12 w-11 rounded-xl border border-slate-200 bg-white text-center text-lg font-bold text-slate-900 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-400/40 sm:h-14 sm:w-12 sm:text-xl"
                            :value="otpDigits[index]"
                            :disabled="verifyOtpForm.processing"
                            @input="onOtpInput(index, $event)"
                            @keydown="onOtpKeydown(index, $event)"
                        />
                    </div>

                    <p v-if="otpError" class="text-sm text-rose-600">{{ otpError }}</p>

                    <button
                        type="button"
                        class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-sky-600 text-base font-semibold text-white transition active:scale-[0.98] hover:bg-sky-500 disabled:opacity-60"
                        :disabled="verifyOtpForm.processing || !otpComplete"
                        @click="submitOtp"
                    >
                        <LoaderCircle v-if="verifyOtpForm.processing" class="h-5 w-5 animate-spin" />
                        {{ verifyOtpForm.processing ? t('verifying_otp') : t('verify_otp') }}
                    </button>

                    <p v-if="resendSeconds > 0" class="text-sm text-slate-500">
                        {{ t('resend_after', { time: resendTimerLabel }) }}
                    </p>
                    <button
                        v-else
                        type="button"
                        class="text-sm font-semibold text-sky-600 transition hover:underline disabled:opacity-60"
                        :disabled="sendOtpForm.processing"
                        @click="resendOtp"
                    >
                        {{ t('resend_otp') }}
                    </button>
                </div>

                <!-- Email fallback -->
                <form v-else class="space-y-4" @submit.prevent="submitEmail">
                    <div
                        v-if="formError || emailForm.errors.email || emailForm.errors.password"
                        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700"
                    >
                        {{ formError || emailForm.errors.email || emailForm.errors.password }}
                    </div>

                    <div class="space-y-2">
                        <label for="email" class="block text-sm font-medium text-slate-700">{{ t('email') }}</label>
                        <input
                            id="email"
                            v-model="emailForm.email"
                            type="email"
                            autocomplete="username"
                            required
                            dir="ltr"
                            class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-base text-slate-900 outline-none ring-sky-400/40 placeholder:text-slate-400 focus:border-sky-400 focus:ring-2"
                            placeholder="worker@example.com"
                        />
                    </div>

                    <div class="space-y-2">
                        <label for="password" class="block text-sm font-medium text-slate-700">{{ t('password') }}</label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="emailForm.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                dir="ltr"
                                class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 pe-12 text-base text-slate-900 outline-none ring-sky-400/40 placeholder:text-slate-400 focus:border-sky-400 focus:ring-2"
                                placeholder="••••••••"
                            />
                            <button
                                type="button"
                                class="absolute end-3 top-1/2 -translate-y-1/2 text-slate-400"
                                @click="showPassword = !showPassword"
                            >
                                <Eye v-if="!showPassword" class="h-5 w-5" />
                                <EyeOff v-else class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="flex h-12 w-full items-center justify-center rounded-xl bg-sky-600 text-base font-semibold text-white transition active:scale-[0.98] hover:bg-sky-500 disabled:opacity-60"
                        :disabled="emailForm.processing"
                    >
                        {{ emailForm.processing ? t('logging_in') : t('login') }}
                    </button>

                    <button
                        type="button"
                        class="w-full text-center text-sm font-semibold text-sky-600 hover:underline"
                        @click="goToPhoneMode"
                    >
                        {{ t('login_with_phone') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
