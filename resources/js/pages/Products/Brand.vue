<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowRight, Edit, ImageIcon, Plus, Search, Trash2, Upload } from 'lucide-vue-next';
import { formatInteger, formatPrice } from '@/lib/formatNumber';
import Swal from 'sweetalert2';

interface Product {
    id: number;
    product_name: string;
    description: string | null;
    price: number;
    image_url: string | null;
    status: string;
    category_id?: number | null;
    category?: { id?: number; category_name: string } | null;
}

interface CategoryOption {
    id: number;
    category_name: string;
    brand_id: number;
    products_count: number;
}

interface Brand {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    logo_url: string | null;
    products: Product[];
}

interface Props {
    brand: Brand;
    categories?: CategoryOption[];
    selectedCategoryId?: number | null;
    totalProducts?: number;
}

const props = withDefaults(defineProps<Props>(), {
    categories: () => [],
    selectedCategoryId: null,
    totalProducts: 0,
});

defineOptions({ layout: AppLayout });

const importForm = useForm<{ file: File | null; brand_id: number }>({
    file: null,
    brand_id: props.brand.id,
});
const deleteForm = useForm({});
const fileInput = ref<HTMLInputElement | null>(null);
const toggling = ref<Set<number>>(new Set());
const searchQuery = ref('');

const selectedCategory = computed(
    () => props.categories.find((category) => category.id === props.selectedCategoryId) ?? null,
);

const filteredProducts = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) {
        return props.brand.products;
    }

    return props.brand.products.filter((product) =>
        [
            product.product_name,
            product.description ?? '',
            product.category?.category_name ?? '',
        ]
            .join(' ')
            .toLowerCase()
            .includes(query),
    );
});

const productsCountLabel = computed(() => {
    if (selectedCategory.value) {
        return `${formatInteger(props.brand.products.length)} منتج في «${selectedCategory.value.category_name}»`;
    }

    return `${formatInteger(props.totalProducts || props.brand.products.length)} منتج`;
});

function applyCategoryFilter(categoryId: number | null) {
    router.get(
        route('products.brand.show', props.brand.slug),
        {
            category: categoryId || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

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
                    <p class="mt-1 text-sm text-muted-foreground">{{ productsCountLabel }}</p>
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

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <label class="flex h-10 w-full max-w-sm items-center gap-2 rounded-full border border-transparent bg-gray-100 px-3.5 text-gray-400 transition focus-within:border-blue-300 focus-within:bg-white focus-within:ring-2 focus-within:ring-blue-100 dark:bg-neutral-800 dark:focus-within:border-blue-700 dark:focus-within:bg-neutral-950 dark:focus-within:ring-blue-950">
                <Search class="size-4 shrink-0 stroke-[1.75]" />
                <input
                    v-model="searchQuery"
                    type="search"
                    placeholder="ابحث عن منتج..."
                    class="w-full bg-transparent text-sm text-gray-800 outline-none placeholder:text-gray-400 dark:text-neutral-100"
                />
            </label>

            <select
                class="h-10 w-full rounded-full border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 outline-none transition hover:bg-gray-50 focus:border-blue-300 focus:ring-2 focus:ring-blue-100 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-300 sm:w-auto sm:min-w-[14rem]"
                :value="selectedCategoryId ?? ''"
                @change="applyCategoryFilter(($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
            >
                <option value="">كل الأصناف</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                    {{ category.category_name }} ({{ formatInteger(category.products_count) }})
                </option>
            </select>
        </div>

        <div v-if="categories.length > 0" class="overflow-x-auto">
            <div class="flex min-w-max items-center gap-1 border-b border-gray-200 dark:border-neutral-700">
                <button
                    type="button"
                    class="relative px-3 py-2.5 text-sm font-medium transition-colors sm:px-4"
                    :class="
                        !selectedCategoryId
                            ? 'text-blue-700 dark:text-blue-300'
                            : 'text-gray-500 hover:text-gray-800 dark:text-neutral-400 dark:hover:text-neutral-200'
                    "
                    @click="applyCategoryFilter(null)"
                >
                    الكل
                    <span class="ms-1.5 text-xs tabular-nums text-gray-400">({{ formatInteger(totalProducts) }})</span>
                    <span
                        v-if="!selectedCategoryId"
                        class="absolute inset-x-0 -bottom-px h-0.5 rounded-full bg-blue-600"
                    />
                </button>
                <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    class="relative px-3 py-2.5 text-sm font-medium transition-colors sm:px-4"
                    :class="
                        selectedCategoryId === category.id
                            ? 'text-blue-700 dark:text-blue-300'
                            : 'text-gray-500 hover:text-gray-800 dark:text-neutral-400 dark:hover:text-neutral-200'
                    "
                    @click="applyCategoryFilter(category.id)"
                >
                    {{ category.category_name }}
                    <span class="ms-1.5 text-xs tabular-nums text-gray-400">({{ formatInteger(category.products_count) }})</span>
                    <span
                        v-if="selectedCategoryId === category.id"
                        class="absolute inset-x-0 -bottom-px h-0.5 rounded-full bg-blue-600"
                    />
                </button>
            </div>
        </div>

        <div
            v-if="filteredProducts.length === 0"
            class="rounded-2xl border border-dashed p-12 text-center text-muted-foreground"
        >
            <template v-if="brand.products.length === 0 && selectedCategoryId">
                لا توجد منتجات في هذا الصنف.
            </template>
            <template v-else-if="brand.products.length === 0">
                لا توجد منتجات في هذا البراند حتى الآن.
            </template>
            <template v-else>
                لا توجد منتجات مطابقة للبحث.
            </template>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
            <article v-for="product in filteredProducts" :key="product.id" class="overflow-hidden rounded-2xl border bg-card shadow-sm">
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
