<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowRight, Edit, ImageIcon, Plus, Trash2, Upload } from 'lucide-vue-next';
import { formatPrice } from '@/lib/formatNumber';
import Swal from 'sweetalert2';

interface Product {
    id: number;
    product_name: string;
    description: string | null;
    price: number;
    insurance_amount: number;
    image_url: string | null;
    status: string;
    category?: { category_name: string } | null;
}

interface Brand {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    logo_url: string | null;
    products: Product[];
}

const props = defineProps<{ brand: Brand }>();
defineOptions({ layout: AppLayout });

const importForm = useForm<{ file: File | null; brand_id: number }>({
    file: null,
    brand_id: props.brand.id,
});
const deleteForm = useForm({});
const fileInput = ref<HTMLInputElement | null>(null);
const toggling = ref<Set<number>>(new Set());

function importProducts(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    importForm.file = file;
    importForm.post(route('products.import'), {
        forceFormData: true,
        onSuccess: () => importForm.reset('file'),
    });
}

function toggleStatus(product: Product) {
    if (toggling.value.has(product.id)) return;
    toggling.value.add(product.id);
    router.patch(route('products.toggle-status', product.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            product.status = product.status === 'active' ? 'inactive' : 'active';
        },
        onFinish: () => toggling.value.delete(product.id),
    });
}

async function deleteProduct(product: Product) {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'حذف المنتج؟',
        text: product.product_name,
        showCancelButton: true,
        confirmButtonText: 'حذف',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#dc2626',
    });

    if (result.isConfirmed) {
        deleteForm.delete(route('products.destroy', product.id));
    }
}
</script>

<template>
    <Head :title="brand.name" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6" dir="rtl">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Button variant="outline" size="icon" as-child>
                    <Link :href="route('products')"><ArrowRight class="h-4 w-4" /></Link>
                </Button>
                <div>
                    <h1 class="text-2xl font-bold sm:text-3xl">{{ brand.name }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ brand.products.length }} منتج</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="importProducts" />
                <Button variant="outline" :disabled="importForm.processing" @click="fileInput?.click()">
                    <Upload class="ms-2 h-4 w-4" />
                    {{ importForm.processing ? 'جاري الاستيراد...' : 'استيراد منتجات' }}
                </Button>
                <Button as-child>
                    <Link :href="route('products.create', { brand: brand.id })">
                        <Plus class="ms-2 h-4 w-4" />
                        إضافة منتج
                    </Link>
                </Button>
            </div>
        </div>

        <div v-if="brand.products.length === 0" class="rounded-2xl border border-dashed p-12 text-center text-muted-foreground">
            لا توجد منتجات في هذا البراند حتى الآن.
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
            <article v-for="product in brand.products" :key="product.id" class="overflow-hidden rounded-2xl border bg-card shadow-sm">
                <div class="relative flex h-44 items-center justify-center overflow-hidden bg-muted/40">
                    <img v-if="product.image_url" :src="product.image_url" :alt="product.product_name" class="h-full w-full object-cover" />
                    <ImageIcon v-else class="h-12 w-12 text-muted-foreground/30" />
                    <button
                        type="button"
                        class="absolute end-3 top-3 rounded-full px-2.5 py-1 text-xs font-semibold shadow-sm"
                        :class="product.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                        :disabled="toggling.has(product.id)"
                        @click="toggleStatus(product)"
                    >
                        {{ product.status === 'active' ? 'نشط' : 'غير نشط' }}
                    </button>
                </div>
                <div class="p-4">
                    <p class="truncate font-bold">{{ product.product_name }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ product.category?.category_name || 'بدون صنف' }}</p>
                    <p class="mt-3 font-bold text-primary">{{ formatPrice(product.price) }} ر.س</p>
                    <div class="mt-4 flex gap-2 border-t pt-3">
                        <Button variant="outline" size="sm" class="flex-1" as-child>
                            <Link :href="route('products.edit', product.id)">
                                <Edit class="ms-1.5 h-3.5 w-3.5" />
                                تعديل
                            </Link>
                        </Button>
                        <Button variant="ghost" size="icon" class="h-8 w-8 text-red-600" @click="deleteProduct(product)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
