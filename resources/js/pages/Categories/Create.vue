<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';

interface Brand {
    id: number;
    name: string;
    slug: string;
}

const props = defineProps<{
    brands: Brand[];
    defaultBrandId: number;
}>();

defineOptions({
    layout: AppLayout,
});

const form = useForm({
    category_name: '',
    brand_id: props.defaultBrandId,
    image: null as File | null,
});

const handleImageChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
        form.image = file;
    }
};

const submit = () => {
    form.post(route('categories.store'), { forceFormData: true });
};
</script>

<template>
    <Head title="إضافة فئة جديدة" />
    <div class="py-12" dir="rtl">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-neutral-800 sm:rounded-lg">
                <div class="p-6 text-neutral-900 dark:text-neutral-100">
                    <div class="mb-6 flex items-center">
                        <Button as-child variant="outline" size="sm" class="ml-2">
                            <Link href="/categories">
                                <ArrowLeft class="ml-1 h-4 w-4" />
                                العودة
                            </Link>
                        </Button>
                        <h1 class="text-2xl font-semibold">إضافة فئة جديدة</h1>
                    </div>
                    <form class="space-y-6" enctype="multipart/form-data" @submit.prevent="submit">
                        <div>
                            <label class="mb-1 block font-medium">البراند</label>
                            <select v-model="form.brand_id" class="w-full rounded border px-3 py-2" required>
                                <option v-for="brand in brands" :key="brand.id" :value="brand.id">
                                    {{ brand.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.brand_id" class="mt-1 text-sm text-red-600">{{ form.errors.brand_id }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block font-medium">اسم الفئة</label>
                            <input v-model="form.category_name" type="text" class="w-full rounded border px-3 py-2" required />
                            <p v-if="form.errors.category_name" class="mt-1 text-sm text-red-600">{{ form.errors.category_name }}</p>
                        </div>

                        <div class="space-y-4 rounded-lg border-2 border-blue-200 bg-blue-50 p-6">
                            <div class="text-center">
                                <h3 class="mb-2 text-xl font-bold text-blue-800">إضافة صورة للقسم</h3>
                                <p class="text-blue-600">اختر صورة لتمثيل هذا القسم (اختياري)</p>
                            </div>

                            <div class="rounded-lg border border-blue-200 bg-white p-4">
                                <label class="mb-3 block font-medium text-gray-700">اختر ملف الصورة:</label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="w-full rounded-lg border-2 border-dashed border-blue-300 bg-blue-50 p-4 hover:border-blue-400 focus:border-blue-500 focus:outline-none"
                                    @change="handleImageChange"
                                />
                                <div class="mt-3 text-center">
                                    <p class="text-sm text-gray-600">الأنواع المدعومة: JPEG, PNG, JPG, GIF</p>
                                    <p class="text-xs text-gray-500">الحد الأقصى لحجم الملف: 2 ميجابايت</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <Button type="submit" :disabled="form.processing">إضافة الفئة</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
