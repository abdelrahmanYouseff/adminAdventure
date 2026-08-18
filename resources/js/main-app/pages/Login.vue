<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight, Eye, EyeOff, LoaderCircle, LockKeyhole, Mail, Phone } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

interface Props {
    status?: string | null;
}

defineProps<Props>();

type Mode = 'phone' | 'otp' | 'credentials';

const mode = ref<Mode>('phone');
const phone = ref('');
const phoneError = ref('');
const normalizedPhone = ref('');
const formError = ref('');
const showPassword = ref(false);

const otpLength = 4;
const otpDigits = ref<string[]>(Array.from({ length: otpLength }, () => ''));
const otpError = ref('');
const otpInputRefs = ref<(HTMLInputElement | null)[]>([]);
const resendSeconds = ref(0);
let resendInterval: ReturnType<typeof setInterval> | null = null;

const credentialsForm = useForm({
    login: '',
    password: '',
});

const sendOtpForm = useForm({
    phone: '',
});

const verifyOtpForm = useForm({
    phone: '',
    code: '',
});

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
    otpError.value = '';
    verifyOtpForm.clearErrors();
    stopResendTimer();
}

function goToPhoneMode() {
    mode.value = 'phone';
    resetOtpState();
    formError.value = '';
    phoneError.value = '';
}

function goToCredentialsMode() {
    mode.value = 'credentials';
    formError.value = '';
    credentialsForm.clearErrors();
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
        phoneError.value = 'أدخل رقم جوال سعودي صحيح يبدأ بـ 5.';
        return;
    }

    phoneError.value = '';
    formError.value = '';
    normalizedPhone.value = digits;
    sendOtpForm.phone = digits;

    sendOtpForm.post('/main-app/login/otp/send', {
        preserveScroll: true,
        onSuccess: () => goToOtpMode(),
        onError: (errors) => {
            phoneError.value = errors.phone || 'تعذر إرسال رمز التحقق. حاول مرة أخرى.';
        },
    });
}

function resendOtp() {
    if (resendSeconds.value > 0 || sendOtpForm.processing) return;

    sendOtpForm.phone = normalizedPhone.value;
    sendOtpForm.post('/main-app/login/otp/send', {
        preserveScroll: true,
        onSuccess: () => {
            otpError.value = '';
            verifyOtpForm.clearErrors();
            startResendTimer();
        },
        onError: (errors) => {
            otpError.value = errors.phone || 'تعذر إرسال رمز التحقق. حاول مرة أخرى.';
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
    otpError.value = '';

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
        otpError.value = 'أدخل رمز التحقق المكوّن من 4 أرقام.';
        return;
    }

    verifyOtpForm.phone = normalizedPhone.value;
    verifyOtpForm.code = otpDigits.value.join('');

    verifyOtpForm.post('/main-app/login/otp/verify', {
        preserveScroll: true,
        onError: (errors) => {
            otpError.value = errors.code || errors.phone || 'رمز التحقق غير صحيح.';
        },
    });
}

function submitCredentials() {
    formError.value = '';

    credentialsForm.post('/main-app/login', {
        preserveScroll: true,
        onError: (errors) => {
            formError.value = errors.login || errors.password || 'بيانات الدخول غير صحيحة.';
        },
        onFinish: () => credentialsForm.reset('password'),
    });
}

onBeforeUnmount(() => stopResendTimer());
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

                <div class="relative rounded-[1.75rem] border border-white/15 bg-white/95 p-5 text-slate-900 shadow-2xl shadow-black/30 backdrop-blur">
                    <button
                        v-if="mode === 'otp'"
                        type="button"
                        class="absolute start-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500"
                        aria-label="رجوع"
                        @click="goToPhoneMode"
                    >
                        <ArrowRight class="h-4 w-4" />
                    </button>

                    <template v-if="mode !== 'otp'">
                        <h2 class="text-lg font-bold text-slate-900">تسجيل الدخول</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ mode === 'phone' ? 'أدخل رقم الجوال لإرسال رمز التحقق' : 'ادخل بالبريد أو رقم الجوال وكلمة المرور' }}
                        </p>
                    </template>

                    <p v-if="status" class="mt-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                        {{ status }}
                    </p>

                    <!-- Phone OTP send -->
                    <div v-if="mode === 'phone'" class="mt-5 space-y-4">
                        <p v-if="phoneError" class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">
                            {{ phoneError }}
                        </p>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-600">رقم الجوال</label>
                            <div
                                class="flex h-12 items-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 focus-within:border-teal-400 focus-within:ring-2 focus-within:ring-teal-100"
                                dir="ltr"
                            >
                                <span class="flex h-full shrink-0 items-center border-e border-slate-200 bg-slate-100 px-3 text-sm font-semibold text-slate-600">
                                    +966
                                </span>
                                <Phone class="ms-3 h-4 w-4 text-slate-400" />
                                <input
                                    v-model="phone"
                                    type="tel"
                                    inputmode="numeric"
                                    autocomplete="tel"
                                    placeholder="5XXXXXXXX"
                                    class="h-full w-full bg-transparent px-2 text-sm outline-none"
                                    :disabled="sendOtpForm.processing"
                                    @keyup.enter="submitPhone"
                                />
                            </div>
                        </div>

                        <button
                            type="button"
                            class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-teal-700 text-sm font-bold text-white shadow-lg shadow-teal-900/20 transition hover:bg-teal-800 disabled:opacity-60"
                            :disabled="sendOtpForm.processing"
                            @click="submitPhone"
                        >
                            <LoaderCircle v-if="sendOtpForm.processing" class="h-4 w-4 animate-spin" />
                            {{ sendOtpForm.processing ? 'جاري إرسال الرمز...' : 'إرسال رمز التحقق' }}
                        </button>

                        <button
                            type="button"
                            class="flex h-12 w-full items-center justify-center rounded-2xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="goToCredentialsMode"
                        >
                            الدخول بالبريد أو رقم الجوال وكلمة المرور
                        </button>
                    </div>

                    <!-- OTP verify -->
                    <div v-else-if="mode === 'otp'" class="mt-6 space-y-5 pt-6 text-center">
                        <p class="text-sm leading-relaxed text-slate-600">تم إرسال رمز التحقق إلى</p>
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
                                class="h-12 w-11 rounded-xl border border-slate-200 bg-slate-50 text-center text-lg font-bold text-slate-900 outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-100 sm:h-14 sm:w-12 sm:text-xl"
                                :value="otpDigits[index]"
                                :disabled="verifyOtpForm.processing"
                                @input="onOtpInput(index, $event)"
                                @keydown="onOtpKeydown(index, $event)"
                            />
                        </div>

                        <p v-if="otpError" class="text-xs text-rose-600">{{ otpError }}</p>

                        <button
                            type="button"
                            class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-teal-700 text-sm font-bold text-white shadow-lg shadow-teal-900/20 transition hover:bg-teal-800 disabled:opacity-60"
                            :disabled="verifyOtpForm.processing || !otpComplete"
                            @click="submitOtp"
                        >
                            <LoaderCircle v-if="verifyOtpForm.processing" class="h-4 w-4 animate-spin" />
                            {{ verifyOtpForm.processing ? 'جاري التحقق...' : 'تأكيد الرمز' }}
                        </button>

                        <p v-if="resendSeconds > 0" class="text-sm text-slate-500">
                            إعادة الإرسال بعد {{ resendTimerLabel }}
                        </p>
                        <button
                            v-else
                            type="button"
                            class="text-sm font-semibold text-teal-700 transition hover:underline disabled:opacity-60"
                            :disabled="sendOtpForm.processing"
                            @click="resendOtp"
                        >
                            إعادة إرسال الرمز
                        </button>
                    </div>

                    <!-- Email / phone + password -->
                    <form v-else class="mt-5 space-y-4" @submit.prevent="submitCredentials">
                        <p v-if="formError" class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">
                            {{ formError }}
                        </p>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-600">البريد الإلكتروني أو رقم الجوال</label>
                            <div class="flex h-12 items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 focus-within:border-teal-400 focus-within:ring-2 focus-within:ring-teal-100">
                                <Mail class="h-4 w-4 text-slate-400" />
                                <input
                                    v-model="credentialsForm.login"
                                    type="text"
                                    autocomplete="username"
                                    required
                                    class="w-full bg-transparent text-sm outline-none"
                                    placeholder="name@company.com أو 5XXXXXXXX"
                                    dir="ltr"
                                />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-600">كلمة المرور</label>
                            <div class="flex h-12 items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 focus-within:border-teal-400 focus-within:ring-2 focus-within:ring-teal-100">
                                <LockKeyhole class="h-4 w-4 text-slate-400" />
                                <input
                                    v-model="credentialsForm.password"
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
                        </div>

                        <button
                            type="submit"
                            class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-teal-700 text-sm font-bold text-white shadow-lg shadow-teal-900/20 transition hover:bg-teal-800 disabled:opacity-60"
                            :disabled="credentialsForm.processing"
                        >
                            <LoaderCircle v-if="credentialsForm.processing" class="h-4 w-4 animate-spin" />
                            {{ credentialsForm.processing ? 'جاري الدخول...' : 'دخول التطبيق' }}
                        </button>

                        <button
                            type="button"
                            class="w-full text-center text-sm font-semibold text-teal-700 hover:underline"
                            @click="goToPhoneMode"
                        >
                            الدخول برقم الجوال ورمز التحقق
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
