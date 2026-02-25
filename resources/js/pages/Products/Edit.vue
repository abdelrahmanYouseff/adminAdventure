<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ArrowLeft, UploadCloud } from 'lucide-vue-next';

defineOptions({
    layout: AppLayout,
});

interface Category {
    id: number;
    category_name: string;
}

interface Product {
    id: number;
    product_name: string;
    description: string | null;
    price: number;
    image: string | null;
    image_url?: string | null;
    status: string;
    category_id: number | null;
    category?: Category | null;
}

interface Props {
    product: Product;
    categories: Category[];
}

const props = defineProps<Props>();

const form = useForm({
    _method: 'PUT',
    product_name: props.product.product_name,
    price: props.product.price,
    description: props.product.description || '',
    status: props.product.status,
    image: null as File | null,
    category_id: props.product.category_id,
});

const imagePreview = ref<string | null>(
    props.product.image_url ?? (props.product.image ? `/storage/${props.product.image}` : null)
);

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        form.image = target.files[0];
        imagePreview.value = URL.createObjectURL(form.image);
    }
};

const submit = () => {
    form.post(route('products.update', props.product.id), {
        // Using `post` with `_method: 'PUT'` to handle file uploads
    });
};
</script>

<template>
    <Head title="تعديل المنتج" />
    <div class="py-12">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <form @submit.prevent="submit">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <Button as-child variant="outline" size="sm">
                            <Link :href="route('products')">
                                <ArrowLeft class="w-4 h-4 ml-2" />
                                العودة للمنتجات
                            </Link>
                        </Button>
                        <h1 class="text-2xl font-semibold">تعديل المنتج</h1>
                    </div>
                    <Button type="submit" :disabled="form.processing">
                        حفظ التعديلات
                    </Button>
                </div>

                <div class="grid md:grid-cols-3 gap-6">
                    <!-- بيانات المنتج -->
                    <div class="md:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>بيانات المنتج</CardTitle>
                                <CardDescription>تحديث البيانات الرئيسية للمنتج.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-6">
                                <div class="space-y-2">
                                    <Label for="product_name">اسم المنتج</Label>
                                    <Input id="product_name" v-model="form.product_name" required />
                                </div>

                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label for="category">الفئة</Label>
                                        <select v-model="form.category_id" id="category" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none focus:ring-1 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100" required>
                                            <option value="" disabled>اختر فئة</option>
                                            <option v-for="cat in props.categories" :key="cat.id" :value="cat.id">
                                                {{ cat.category_name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="price">السعر</Label>
                                        <div class="relative">
                                            <Input id="price" v-model.number="form.price" type="number" step="0.01" required placeholder="0.00" class="pl-12" />
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <span class="text-neutral-500 sm:text-sm">ر.س</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">الوصف</Label>
                                    <textarea v-model="form.description" id="description" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none focus:ring-1 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100" rows="3" placeholder="أدخل وصفاً تفصيلياً للمنتج..."></textarea>
                                </div>

                                <div class="space-y-2">
                                    <Label for="status">الحالة</Label>
                                    <select v-model="form.status" id="status" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none focus:ring-1 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100">
                                        <option value="active">نشط</option>
                                        <option value="inactive">غير نشط</option>
                                    </select>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- صورة المنتج -->
                    <div class="md:col-span-1 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>صورة المنتج</CardTitle>
                            </CardHeader>
                            <CardContent class="flex flex-col items-center justify-center">
                                <label for="image-upload" class="w-full cursor-pointer">
                                    <div v-if="!imagePreview" class="border-2 border-dashed border-neutral-300 dark:border-neutral-600 rounded-lg p-8 text-center">
                                        <UploadCloud class="mx-auto h-12 w-12 text-neutral-400" />
                                        <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                                            <span class="font-semibold">انقر للرفع</span> أو اسحب وأفلت
                                        </p>
                                        <p class="text-xs text-neutral-500">PNG, JPG, GIF حتى 2 ميجا</p>
                                    </div>
                                    <div v-else class="rounded-lg overflow-hidden">
                                        <img :src="imagePreview" alt="معاينة صورة المنتج" class="w-full h-auto object-cover" />
                                    </div>
                                </label>
                                <input id="image-upload" type="file" @change="handleFileChange" accept="image/*" class="hidden" />
                                <Button v-if="imagePreview" @click="imagePreview = null; form.image = null" variant="link" class="mt-2">
                                    إزالة الصورة
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
