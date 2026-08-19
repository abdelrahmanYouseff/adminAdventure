<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { ArrowRight, ImageIcon, Phone, Save, UploadCloud } from 'lucide-vue-next';

interface Brand {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
    phone: string;
}

const props = defineProps<{ brand: Brand }>();
defineOptions({ layout: AppLayout });

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);

const logoPreview = ref<string | null>(props.brand.logo_url);
const form = useForm({
    logo: null as File | null,
});

const phoneForm = useForm({
    phone: props.brand.phone,
});

function selectLogo(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }

    form.logo = file;
    logoPreview.value = URL.createObjectURL(file);
    form.clearErrors('logo');
}

function submit() {
    form.post(route('settings.quotations.logo', props.brand.slug), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('logo');
        },
    });
}

function submitPhone() {
    phoneForm.post(route('settings.quotations.phone', props.brand.slug), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`إعدادات ${brand.name}`" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6" dir="rtl">
        <div class="flex items-center gap-3">
            <Button variant="outline" size="icon" as-child>
                <Link :href="route('settings.quotations.index')">
                    <ArrowRight class="h-4 w-4" />
                </Link>
            </Button>
            <div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ brand.name }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">لوجو ورقم هاتف الشركة الظاهران في عرض السعر</p>
            </div>
        </div>

        <p
            v-if="successMessage"
            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
        >
            {{ successMessage }}
        </p>

        <form class="mx-auto w-full max-w-xl space-y-5 rounded-2xl border bg-card p-5 shadow-sm sm:p-6" @submit.prevent="submitPhone">
            <div class="flex items-center gap-2">
                <Phone class="h-5 w-5 text-orange-600" />
                <h2 class="text-lg font-bold">رقم هاتف الشركة</h2>
            </div>
            <p class="text-sm text-muted-foreground">
                يظهر هذا الرقم في كل عروض الأسعار (PDF). أي تعديل يُطبَّق فوراً على العروض الحالية والجديدة.
            </p>
            <div class="space-y-2">
                <Label for="company-phone">الرقم الحالي</Label>
                <input
                    id="company-phone"
                    v-model="phoneForm.phone"
                    type="text"
                    dir="ltr"
                    class="flex h-11 w-full rounded-xl border border-input bg-background px-3 text-sm outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring"
                    placeholder="0114101840 - 0559668015"
                />
                <p v-if="phoneForm.errors.phone" class="text-xs text-red-600">{{ phoneForm.errors.phone }}</p>
            </div>
            <Button
                type="submit"
                class="h-11 w-full gap-2 rounded-xl"
                :disabled="phoneForm.processing || !phoneForm.phone.trim() || phoneForm.phone.trim() === brand.phone"
            >
                <Save class="h-4 w-4" />
                {{ phoneForm.processing ? 'جاري الحفظ...' : 'حفظ رقم الهاتف' }}
            </Button>
        </form>

        <form class="mx-auto w-full max-w-xl space-y-5 rounded-2xl border bg-card p-5 shadow-sm sm:p-6" @submit.prevent="submit">
            <div class="space-y-2">
                <Label>اللوجو الحالي</Label>
                <div class="flex h-56 items-center justify-center overflow-hidden rounded-2xl border bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-900">
                    <img
                        v-if="logoPreview"
                        :src="logoPreview"
                        :alt="brand.name"
                        class="h-full w-full object-contain p-6"
                    />
                    <div v-else class="text-center text-muted-foreground">
                        <ImageIcon class="mx-auto h-10 w-10" />
                        <p class="mt-2 text-sm">لا يوجد لوجو بعد</p>
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <Label for="quotation-logo">تحديث اللوجو</Label>
                <label
                    for="quotation-logo"
                    class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-muted/30 px-4 py-6 text-center transition hover:bg-muted/50"
                >
                    <UploadCloud class="h-6 w-6 text-muted-foreground" />
                    <span class="text-sm font-medium">
                        {{ form.logo ? form.logo.name : 'اختر صورة اللوجو' }}
                    </span>
                    <span class="text-xs text-muted-foreground">jpg, png, webp — حتى 4 ميجابايت</span>
                    <input
                        id="quotation-logo"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                        class="hidden"
                        @change="selectLogo"
                    />
                </label>
                <p v-if="form.errors.logo" class="text-xs text-red-600">{{ form.errors.logo }}</p>
            </div>

            <Button type="submit" class="h-11 w-full gap-2 rounded-xl" :disabled="form.processing || !form.logo">
                <Save class="h-4 w-4" />
                {{ form.processing ? 'جاري الحفظ...' : 'حفظ اللوجو' }}
            </Button>
        </form>
    </div>
</template>
