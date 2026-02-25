<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Plus, Trash2, ImageIcon, Pencil, Upload, FileSpreadsheet } from 'lucide-vue-next';

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
    created_at: string;
    updated_at: string;
}

interface Props {
    products: Product[];
    categories: Category[];
}

defineProps<Props>();

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string } | undefined) ?? {});
const importForm = useForm<{ file: File | null }>({ file: null });
const fileInput = ref<HTMLInputElement | null>(null);

const triggerFileInput = () => fileInput.value?.click();

const onFileSelected = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;
    importForm.file = file;
    importForm.post(route('products.import'), {
        forceFormData: true,
        onSuccess: () => {
            importForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

defineOptions({
    layout: AppLayout,
});

const deleteForm = useForm({});

const deleteProduct = (productId: number) => {
    if (confirm('هل أنت متأكد من حذف هذا المنتج؟')) {
        deleteForm.delete(`/products/${productId}`);
    }
};

const imageUrl = (product: Product) =>
    product.image_url ?? (product.image ? `/storage/${product.image}` : null);
</script>

<template>
    <Head title="المنتجات" />

    <div class="space-y-6 py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <!-- Flash messages (تظهر بعد الاستيراد أو أي إجراء) -->
            <div v-if="flash.success" class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-green-700 dark:text-green-300 text-sm">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-red-700 dark:text-red-300 text-sm">
                {{ flash.error }}
            </div>

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
                <h1 class="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">المنتجات</h1>
                <div class="flex flex-wrap gap-2">
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        class="hidden"
                        @change="onFileSelected"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="importForm.processing"
                        @click="triggerFileInput"
                    >
                        <Upload class="w-4 h-4 ml-2" />
                        {{ importForm.processing ? 'جاري الاستيراد...' : 'استيراد من Excel' }}
                    </Button>
                    <Button variant="outline" as-child>
                        <Link href="/categories">
                            إدارة الفئات
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link href="/products/create">
                            <Plus class="w-4 h-4 ml-2" />
                            إضافة منتج جديد
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Import format hint -->
            <div class="mb-4 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50 px-4 py-3 text-sm text-neutral-600 dark:text-neutral-400 flex items-center gap-2">
                <FileSpreadsheet class="w-4 h-4 shrink-0" />
                <span>ملف Excel يجب أن يحتوي على: العمود <strong>أ</strong> = اسم المنتج، العمود <strong>ب</strong> = الفئة، العمود <strong>ج</strong> = السعر. الصف الأول يمكن أن يكون عناوين.</span>
            </div>

            <!-- Empty state -->
            <div
                v-if="products.length === 0"
                class="rounded-xl border border-dashed border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-800/50 p-12 text-center"
            >
                <p class="text-neutral-500 dark:text-neutral-400 mb-4">لا توجد منتجات. اضغط على "إضافة منتج جديد" للبدء.</p>
                <Button as-child>
                    <Link href="/products/create">
                        <Plus class="w-4 h-4 ml-2" />
                        إضافة منتج جديد
                    </Link>
                </Button>
            </div>

            <!-- Products grid -->
            <div
                v-else
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
            >
                <Card
                    v-for="product in products"
                    :key="product.id"
                    class="overflow-hidden flex flex-col hover:shadow-md transition-shadow"
                >
                    <!-- Image -->
                    <div class="aspect-square w-full bg-neutral-100 dark:bg-neutral-700 flex items-center justify-center overflow-hidden">
                        <img
                            v-if="imageUrl(product)"
                            :src="imageUrl(product)"
                            :alt="product.product_name"
                            class="w-full h-full object-cover"
                        />
                        <ImageIcon v-else class="w-16 h-16 text-neutral-400 dark:text-neutral-500" />
                    </div>
                    <CardContent class="p-4 flex flex-col flex-1">
                        <h3 class="font-semibold text-lg text-neutral-900 dark:text-neutral-100 line-clamp-2 mb-1">
                            {{ product.product_name }}
                        </h3>
                        <p class="text-emerald-600 dark:text-emerald-400 font-bold text-xl mb-4">
                            {{ Number(product.price).toLocaleString('ar-SA') }} ريال
                        </p>
                        <div class="mt-auto flex gap-2">
                            <Button variant="outline" size="sm" as-child class="flex-1">
                                <Link :href="route('products.edit', product.id)">
                                    <Pencil class="w-3.5 h-3.5 ml-1.5" />
                                    تعديل
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                class="text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-950/30"
                                :disabled="deleteForm.processing"
                                @click="deleteProduct(product.id)"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
