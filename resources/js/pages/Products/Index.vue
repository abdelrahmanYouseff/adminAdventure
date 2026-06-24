<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Plus, Trash2, ImageIcon, Edit, Upload, FileSpreadsheet } from 'lucide-vue-next';
import { formatPrice } from '@/lib/formatNumber';

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

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string } | undefined) ?? {});

const importForm = useForm<{ file: File | null }>({ file: null });
const deleteForm = useForm({});
const fileInput = ref<HTMLInputElement | null>(null);
const toggling = ref<Set<number>>(new Set());

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

const deleteProduct = (productId: number) => {
    if (confirm('هل أنت متأكد من حذف هذا المنتج؟')) {
        deleteForm.delete(`/products/${productId}`);
    }
};

const imageUrl = (product: Product) =>
    product.image_url ?? (product.image ? `/storage/${product.image}` : null);

function toggleStatus(product: Product) {
    if (toggling.value.has(product.id)) return;
    toggling.value.add(product.id);

    router.patch(
        `/products/${product.id}/toggle-status`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                product.status = product.status === 'active' ? 'inactive' : 'active';
            },
            onFinish: () => {
                toggling.value.delete(product.id);
            },
        },
    );
}

</script>

<template>
    <Head title="المنتجات" />

    <div class="py-8 sm:py-12" dir="rtl">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div v-if="flash.success" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                {{ flash.error }}
            </div>

            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <div class="border-b border-neutral-200 px-5 py-5 dark:border-neutral-700 sm:px-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h1 class="text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100 sm:text-2xl">
                                المنتجات
                            </h1>
                            <p class="mt-1 text-sm leading-relaxed text-neutral-500 dark:text-neutral-400">
                                جدول بكل المنتجات: الاسم، السعر، الصورة، والحالة.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 shrink-0">
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
                                size="sm"
                                :disabled="importForm.processing"
                                @click="triggerFileInput"
                            >
                                <Upload class="size-4 ms-2" />
                                {{ importForm.processing ? 'جاري الاستيراد...' : 'استيراد Excel' }}
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                                <Link href="/categories">إدارة الأصناف</Link>
                            </Button>
                            <Button size="sm" as-child>
                                <Link href="/products/create">
                                    <Plus class="size-4 ms-2" />
                                    إضافة منتج
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <div class="mt-4 flex items-start gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-xs leading-relaxed text-neutral-600 dark:border-neutral-600 dark:bg-neutral-800/50 dark:text-neutral-400">
                        <FileSpreadsheet class="mt-0.5 size-4 shrink-0" />
                        <span>استيراد CSV/Excel: العمود <strong>أ</strong> اسم المنتج، <strong>ب</strong> الفئة، <strong>ج</strong> السعر.</span>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="overflow-x-auto">
                        <Table class="text-sm">
                            <TableHeader>
                                <TableRow class="hover:bg-transparent">
                                    <TableHead class="w-[5.5rem] text-center text-xs font-semibold text-neutral-600 dark:text-neutral-300">
                                        الصورة
                                    </TableHead>
                                    <TableHead class="min-w-[10rem] text-start text-xs font-semibold text-neutral-600 dark:text-neutral-300">
                                        الاسم
                                    </TableHead>
                                    <TableHead class="w-[8rem] text-center text-xs font-semibold text-neutral-600 dark:text-neutral-300">
                                        السعر
                                    </TableHead>
                                    <TableHead class="w-[7.5rem] text-center text-xs font-semibold text-neutral-600 dark:text-neutral-300">
                                        الحالة
                                    </TableHead>
                                    <TableHead class="w-[8.5rem] text-center text-xs font-semibold text-neutral-600 dark:text-neutral-300">
                                        إجراءات
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="products.length === 0" class="hover:bg-transparent">
                                    <TableCell colspan="5" class="h-32 text-center align-middle">
                                        <p class="text-neutral-500 dark:text-neutral-400">
                                            لا توجد منتجات بعد. اضغط «إضافة منتج» للبدء.
                                        </p>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-for="product in products" :key="product.id">
                                    <TableCell class="align-middle">
                                        <div class="flex justify-center">
                                            <div
                                                v-if="imageUrl(product)"
                                                class="size-14 shrink-0 overflow-hidden rounded-lg border border-neutral-200 bg-neutral-50 dark:border-neutral-600 dark:bg-neutral-800"
                                            >
                                                <img
                                                    :src="imageUrl(product)!"
                                                    :alt="product.product_name"
                                                    class="size-full object-cover"
                                                />
                                            </div>
                                            <div
                                                v-else
                                                class="flex size-14 shrink-0 flex-col items-center justify-center gap-0.5 rounded-lg border border-dashed border-neutral-200 bg-neutral-50 text-neutral-400 dark:border-neutral-600 dark:bg-neutral-800/80 dark:text-neutral-500"
                                            >
                                                <ImageIcon class="size-5 shrink-0" />
                                                <span class="max-w-[3rem] text-center text-[9px] leading-tight">بدون صورة</span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell class="align-middle text-start">
                                        <span class="font-semibold text-neutral-900 dark:text-neutral-100">
                                            {{ product.product_name }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="align-middle text-center">
                                        <span class="font-bold tabular-nums text-[#3b89d2]">
                                            {{ formatPrice(product.price) }}
                                        </span>
                                        <span class="mr-1 text-xs text-neutral-500">ر.س</span>
                                    </TableCell>
                                    <TableCell class="align-middle text-center">
                                        <button
                                            type="button"
                                            class="inline-flex min-h-8 min-w-[4.5rem] items-center justify-center rounded-full px-3 text-xs font-semibold transition disabled:opacity-50"
                                            :class="product.status === 'active'
                                                ? 'bg-blue-100 text-[#2f6eb0] hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300'
                                                : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-400'"
                                            :disabled="toggling.has(product.id)"
                                            @click="toggleStatus(product)"
                                        >
                                            {{ product.status === 'active' ? 'نشط' : 'غير نشط' }}
                                        </button>
                                    </TableCell>
                                    <TableCell class="align-middle">
                                        <div class="flex items-center justify-center gap-2">
                                            <Button variant="outline" size="sm" class="h-9" as-child>
                                                <Link :href="route('products.edit', product.id)">
                                                    <Edit class="size-4" />
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="h-9 text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/30"
                                                :disabled="deleteForm.processing"
                                                @click="deleteProduct(product.id)"
                                            >
                                                <Trash2 class="size-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
