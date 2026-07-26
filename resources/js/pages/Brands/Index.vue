<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ArrowRight, Building2, Edit, ImageIcon, Plus, Trash2 } from 'lucide-vue-next';
import Swal from 'sweetalert2';

interface Brand {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    logo_url: string | null;
    is_active: boolean;
    products_count: number;
}

defineProps<{ brands: Brand[] }>();
defineOptions({ layout: AppLayout });

const editingId = ref<number | null>(null);
const editingSlug = ref<string | null>(null);
const logoPreview = ref<string | null>(null);
const form = useForm({
    name: '',
    description: '',
    logo: null as File | null,
    is_active: true,
});
const deleteForm = useForm({});

function resetForm() {
    editingId.value = null;
    editingSlug.value = null;
    logoPreview.value = null;
    form.reset();
    form.clearErrors();
}

function editBrand(brand: Brand) {
    editingId.value = brand.id;
    editingSlug.value = brand.slug;
    form.name = brand.name;
    form.description = brand.description || '';
    form.logo = null;
    form.is_active = brand.is_active;
    logoPreview.value = brand.logo_url;
    form.clearErrors();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function selectLogo(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    form.logo = file;
    logoPreview.value = URL.createObjectURL(file);
}

function submit() {
    const url = editingSlug.value ? route('brands.update', editingSlug.value) : route('brands.store');
    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: resetForm,
    });
}

async function removeBrand(brand: Brand) {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'حذف البراند؟',
        text: brand.products_count > 0
            ? 'يجب نقل المنتجات إلى براند آخر أولاً.'
            : brand.name,
        showCancelButton: brand.products_count === 0,
        showConfirmButton: brand.products_count === 0,
        confirmButtonText: 'حذف',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#dc2626',
    });

    if (result.isConfirmed) {
        deleteForm.delete(route('brands.destroy', brand.slug));
    }
}
</script>

<template>
    <Head title="إدارة البراندات" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6" dir="rtl">
        <div class="flex items-center gap-3">
            <Button variant="outline" size="icon" as-child>
                <Link :href="route('products')"><ArrowRight class="h-4 w-4" /></Link>
            </Button>
            <div>
                <h1 class="text-2xl font-bold sm:text-3xl">إدارة البراندات</h1>
                <p class="mt-1 text-sm text-muted-foreground">إضافة وتعديل البراندات التي تُقسّم المنتجات</p>
            </div>
        </div>

        <form class="grid gap-5 rounded-2xl border bg-card p-5 shadow-sm lg:grid-cols-[180px_1fr_auto]" @submit.prevent="submit">
            <label class="flex h-36 cursor-pointer items-center justify-center overflow-hidden rounded-xl border border-dashed bg-muted/30">
                <img v-if="logoPreview" :src="logoPreview" alt="شعار البراند" class="h-full w-full object-contain p-3" />
                <div v-else class="text-center text-muted-foreground">
                    <ImageIcon class="mx-auto h-8 w-8" />
                    <span class="mt-2 block text-xs">رفع الشعار</span>
                </div>
                <input type="file" accept="image/*" class="hidden" @change="selectLogo" />
            </label>

            <div class="grid content-start gap-4">
                <div class="space-y-1.5">
                    <Label for="brand-name">اسم البراند</Label>
                    <Input id="brand-name" v-model="form.name" required placeholder="مثال: شركة عالم المغامرة" />
                    <p v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</p>
                </div>
                <div class="space-y-1.5">
                    <Label for="brand-description">الوصف</Label>
                    <Input id="brand-description" v-model="form.description" placeholder="وصف مختصر للبراند" />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4" />
                    البراند نشط
                </label>
            </div>

            <div class="flex items-end gap-2 lg:flex-col lg:justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Plus v-if="!editingId" class="ms-2 h-4 w-4" />
                    <Edit v-else class="ms-2 h-4 w-4" />
                    {{ editingId ? 'حفظ التعديل' : 'إضافة البراند' }}
                </Button>
                <Button v-if="editingId" type="button" variant="outline" @click="resetForm">إلغاء</Button>
            </div>
        </form>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article v-for="brand in brands" :key="brand.id" class="flex items-center gap-4 rounded-2xl border bg-card p-4 shadow-sm">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-muted">
                    <img v-if="brand.logo_url" :src="brand.logo_url" :alt="brand.name" class="h-full w-full object-contain p-2" />
                    <Building2 v-else class="h-8 w-8 text-muted-foreground/40" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-bold">{{ brand.name }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ brand.products_count }} منتج</p>
                    <span class="mt-2 inline-block rounded-full px-2 py-0.5 text-[11px]" :class="brand.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                        {{ brand.is_active ? 'نشط' : 'غير نشط' }}
                    </span>
                </div>
                <div class="flex flex-col gap-1">
                    <Button variant="ghost" size="icon" @click="editBrand(brand)"><Edit class="h-4 w-4" /></Button>
                    <Button variant="ghost" size="icon" class="text-red-600" @click="removeBrand(brand)"><Trash2 class="h-4 w-4" /></Button>
                </div>
            </article>
        </div>
    </div>
</template>
