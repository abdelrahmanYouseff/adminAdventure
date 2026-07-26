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

interface Category {
    id: number;
    category_name: string;
    brand_id: number | null;
    image?: string;
}

interface Props {
    category: Category;
    brands: Brand[];
}

const props = defineProps<Props>();

defineOptions({
    layout: AppLayout,
});

const form = useForm({
    _method: 'PUT',
    category_name: props.category.category_name,
    brand_id: props.category.brand_id ?? props.brands[0]?.id ?? null,
    image: null as File | null,
});

const handleImageChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
        form.image = file;
    }
};

const submit = () => {
    form.post(route('categories.update', props.category.id), { forceFormData: true });
};
</script>

<template>
    <Head title="تعديل الفئة" />
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
                        <h1 class="text-2xl font-semibold">تعديل الفئة</h1>
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

                        <div class="rounded-lg border-2 border-dashed border-gray-300 p-6 dark:border-gray-600">
                            <label class="mb-2 block text-lg font-medium">صورة القسم (اختياري)</label>

                            <div v-if="props.category.image" class="mb-4">
                                <p class="mb-2 text-sm text-blue-600 dark:text-blue-400">الصورة الحالية:</p>
                                <img
                                    :src="`/storage/${props.category.image}`"
                                    :alt="props.category.category_name"
                                    class="h-24 w-24 rounded-lg border border-gray-300 object-cover"
                                />
                            </div>

                            <input
                                type="file"
                                accept="image/*"
                                class="w-full rounded-lg border border-gray-300 p-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600"
                                @change="handleImageChange"
                            />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                اختر صورة جديدة للقسم (JPEG, PNG, JPG, GIF - الحد الأقصى 2MB)
                            </p>
                        </div>

                        <div>
                            <Button type="submit" :disabled="form.processing">حفظ التعديلات</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
