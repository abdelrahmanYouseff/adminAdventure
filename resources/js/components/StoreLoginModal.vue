<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, computed, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ArrowRight, LoaderCircle, User, X } from 'lucide-vue-next';

const open = defineModel<boolean>('open', { default: false });

const mode = ref<'phone' | 'otp' | 'email'>('phone');
const phone = ref('');
const phoneError = ref('');
const normalizedPhone = ref('');
const otpLength = 4;
const otpDigits = ref<string[]>(Array.from({ length: otpLength }, () => ''));
const otpError = ref('');
const otpInputRefs = ref<(HTMLInputElement | null)[]>([]);
const resendSeconds = ref(0);

let resendInterval: ReturnType<typeof setInterval> | null = null;

const form = useForm({
    email: '',
    password: '',
    remember: false,
    redirect: '/home',
});

const sendOtpForm = useForm({
    phone: '',
});

const verifyOtpForm = useForm({
    phone: '',
    code: '',
    redirect: '/home',
});

const formattedPhone = computed(() => {
    if (!normalizedPhone.value) {
        return '';
    }
    return `+966 ${normalizedPhone.value}`;
});

const resendTimerLabel = computed(() => {
    const seconds = resendSeconds.value;
    const mm = String(Math.floor(seconds / 60)).padStart(2, '0');
    const ss = String(seconds % 60).padStart(2, '0');
    return `${mm}:${ss}`;
});

const otpComplete = computed(() => otpDigits.value.every((d) => d.length === 1));

function normalizePhone(raw: string): string {
    let digits = raw.replace(/\D/g, '');
    if (digits.startsWith('966')) {
        digits = digits.slice(3);
    }
    if (digits.startsWith('0')) {
        digits = digits.slice(1);
    }
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

function resetModal() {
    mode.value = 'phone';
    phone.value = '';
    phoneError.value = '';
    normalizedPhone.value = '';
    form.clearErrors();
    sendOtpForm.clearErrors();
    resetOtpState();
}

function close() {
    open.value = false;
}

watch(open, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
    if (!isOpen) {
        resetModal();
    }
});

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && open.value) {
        close();
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
    stopResendTimer();
});

function showEmailMode() {
    mode.value = 'email';
    form.clearErrors();
}

function goToPhoneMode() {
    mode.value = 'phone';
    resetOtpState();
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
        phoneError.value = 'أدخل رقم جوال صحيحاً';
        return;
    }

    phoneError.value = '';
    normalizedPhone.value = digits;
    sendOtpForm.phone = digits;

    sendOtpForm.post(route('store.otp.send'), {
        preserveScroll: true,
        onSuccess: () => goToOtpMode(),
        onError: () => {
            phoneError.value = sendOtpForm.errors.phone ?? 'تعذر إرسال رمز التحقق';
        },
    });
}

function resendOtp() {
    if (resendSeconds.value > 0 || sendOtpForm.processing) {
        return;
    }

    sendOtpForm.phone = normalizedPhone.value;
    sendOtpForm.post(route('store.otp.send'), {
        preserveScroll: true,
        onSuccess: () => {
            otpError.value = '';
            verifyOtpForm.clearErrors();
            startResendTimer();
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
    if (!pasted) {
        return;
    }

    pasted.split('').forEach((digit, index) => {
        if (index < otpLength) {
            otpDigits.value[index] = digit;
        }
    });

    const focusIndex = Math.min(pasted.length, otpLength - 1);
    nextTick(() => otpInputRefs.value[focusIndex]?.focus());
}

function submitOtp() {
    if (!otpComplete.value) {
        otpError.value = 'أدخل رمز التحقق المكوّن من 4 أرقام';
        return;
    }

    verifyOtpForm.phone = normalizedPhone.value;
    verifyOtpForm.code = otpDigits.value.join('');

    verifyOtpForm.post(route('store.otp.verify'), {
        preserveScroll: true,
        onSuccess: () => close(),
        onError: () => {
            otpError.value = verifyOtpForm.errors.code ?? verifyOtpForm.errors.phone ?? 'رمز التحقق غير صحيح';
        },
    });
}

function submitEmail() {
    form.post(route('login'), {
        preserveScroll: true,
        onSuccess: () => close(),
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[200] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="store-login-title"
        >
            <button
                type="button"
                class="absolute inset-0 bg-black/75"
                aria-label="إغلاق"
                @click="close"
            />

            <div
                class="relative z-10 w-full max-w-[min(100%-2rem,26rem)] overflow-hidden rounded-2xl bg-white shadow-2xl"
                dir="rtl"
            >
                <button
                    type="button"
                    class="absolute start-4 top-4 z-10 flex h-8 w-8 items-center justify-center rounded-full text-red-400 transition hover:bg-red-50 hover:text-red-500"
                    aria-label="إغلاق"
                    @click="close"
                >
                    <X class="h-5 w-5" />
                </button>

                <button
                    v-if="mode === 'otp'"
                    type="button"
                    class="absolute end-4 top-4 z-10 flex h-9 w-9 items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-500 transition hover:bg-neutral-50"
                    aria-label="رجوع"
                    @click="goToPhoneMode"
                >
                    <ArrowRight class="h-5 w-5" />
                </button>

                <div class="px-6 pb-6 pt-10 text-center" style="font-family: 'Noto Kufi Arabic', sans-serif">
                    <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-neutral-100">
                        <User class="h-10 w-10 text-neutral-400" stroke-width="1.5" />
                    </div>
                    <h2 id="store-login-title" class="text-xl font-bold text-neutral-900">تسجيل الدخول</h2>

                    <!-- رقم الجوال -->
                    <div v-if="mode === 'phone'" class="mt-6 space-y-4 text-start">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-neutral-800">رقم الجوال</label>
                            <div
                                class="flex overflow-hidden rounded-xl border border-neutral-200 bg-white focus-within:border-[#b565d8] focus-within:ring-2 focus-within:ring-[#b565d8]/20"
                                dir="ltr"
                            >
                                <span
                                    class="flex shrink-0 items-center border-e border-neutral-200 bg-neutral-50 px-3 text-sm font-semibold text-neutral-600"
                                >
                                    +966
                                </span>
                                <input
                                    v-model="phone"
                                    type="tel"
                                    inputmode="numeric"
                                    autocomplete="tel"
                                    placeholder="051 234 5678"
                                    class="min-h-12 w-full flex-1 bg-transparent px-3 text-sm text-neutral-900 outline-none placeholder:text-neutral-400"
                                    :disabled="sendOtpForm.processing"
                                    @keyup.enter="submitPhone"
                                />
                            </div>
                            <p v-if="phoneError" class="mt-1.5 text-xs text-red-500">{{ phoneError }}</p>
                        </div>

                        <button
                            type="button"
                            class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#b565d8] text-base font-bold text-white transition hover:bg-[#a855d8] disabled:opacity-60"
                            :disabled="sendOtpForm.processing"
                            @click="submitPhone"
                        >
                            <LoaderCircle v-if="sendOtpForm.processing" class="h-5 w-5 animate-spin" />
                            دخول
                        </button>

                        <button
                            type="button"
                            class="flex min-h-12 w-full items-center justify-center rounded-xl border-2 border-[#b565d8] bg-white text-sm font-bold text-[#b565d8] transition hover:bg-[#b565d8]/5"
                            @click="showEmailMode"
                        >
                            تسجيل الدخول بالبريد الإلكتروني
                        </button>
                    </div>

                    <!-- رمز التحقق OTP -->
                    <div v-else-if="mode === 'otp'" class="mt-6 space-y-5 text-center">
                        <p class="text-sm leading-relaxed text-neutral-600">
                            رقم التحقق مطلوب لاكمال العملية لقد تم إرسال رمز التحقق في رسالة إليكم
                        </p>
                        <p class="text-lg font-bold text-neutral-900" dir="ltr">{{ formattedPhone }}</p>

                        <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3" dir="ltr" @paste="onOtpPaste">
                            <input
                                v-for="(_, index) in otpDigits"
                                :key="index"
                                :ref="(el) => setOtpRef(el as HTMLInputElement | null, index)"
                                type="text"
                                inputmode="numeric"
                                maxlength="1"
                                autocomplete="one-time-code"
                                class="h-12 w-11 rounded-xl border border-neutral-200 bg-white text-center text-lg font-bold text-neutral-900 outline-none transition focus:border-[#b565d8] focus:ring-2 focus:ring-[#b565d8]/20 sm:h-14 sm:w-12 sm:text-xl"
                                :value="otpDigits[index]"
                                :disabled="verifyOtpForm.processing"
                                @input="onOtpInput(index, $event)"
                                @keydown="onOtpKeydown(index, $event)"
                            />
                        </div>

                        <p v-if="otpError" class="text-xs text-red-500">{{ otpError }}</p>

                        <button
                            type="button"
                            class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#b565d8] text-base font-bold text-white transition hover:bg-[#a855d8] disabled:opacity-60"
                            :disabled="verifyOtpForm.processing || !otpComplete"
                            @click="submitOtp"
                        >
                            <LoaderCircle v-if="verifyOtpForm.processing" class="h-5 w-5 animate-spin" />
                            تحقق
                        </button>

                        <p v-if="resendSeconds > 0" class="text-sm text-neutral-500">
                            يمكنك إعادة الإرسال بعد {{ resendTimerLabel }}
                        </p>
                        <button
                            v-else
                            type="button"
                            class="text-sm font-semibold text-[#b565d8] transition hover:underline disabled:opacity-60"
                            :disabled="sendOtpForm.processing"
                            @click="resendOtp"
                        >
                            إعادة إرسال الرمز
                        </button>
                    </div>

                    <!-- البريد الإلكتروني -->
                    <div v-else class="mt-6 space-y-4 text-start">
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
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-neutral-800">كلمة المرور</label>
                            <input
                                v-model="form.password"
                                type="password"
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="min-h-12 w-full rounded-xl border border-neutral-200 px-3 text-sm text-neutral-900 outline-none transition focus:border-[#b565d8] focus:ring-2 focus:ring-[#b565d8]/20"
                                dir="ltr"
                                @keyup.enter="submitEmail"
                            />
                            <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-500">{{ form.errors.password }}</p>
                        </div>

                        <button
                            type="button"
                            class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#b565d8] text-base font-bold text-white transition hover:bg-[#a855d8] disabled:opacity-60"
                            :disabled="form.processing"
                            @click="submitEmail"
                        >
                            <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin" />
                            دخول
                        </button>

                        <button
                            type="button"
                            class="w-full text-center text-sm font-semibold text-[#b565d8] transition hover:underline"
                            @click="goToPhoneMode"
                        >
                            العودة لتسجيل الدخول برقم الجوال
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
