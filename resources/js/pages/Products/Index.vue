<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Building2, ImageIcon, Layers3, Plus } from 'lucide-vue-next';
import { formatInteger } from '@/lib/formatNumber';

interface PreviewProduct {
    id: number;
    product_name: string;
    image_url: string | null;
}

interface Brand {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    logo_url: string | null;
    is_active: boolean;
    products_count: number;
    products: PreviewProduct[];
}

defineProps<{ brands: Brand[] }>();
defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="المنتجات حسب البراند" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6" dir="rtl">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">براندات المنتجات</h1>
                <p class="mt-1 text-sm text-muted-foreground">اختر البراند لعرض وإدارة المنتجات الخاصة به</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button as-child>
                    <Link :href="route('products.create')">
                        <Plus class="ms-2 h-4 w-4" />
                        إضافة منتج
                    </Link>
                </Button>
            </div>
        </div>

        <div v-if="brands.length === 0" class="rounded-2xl border border-dashed p-12 text-center">
            <Building2 class="mx-auto h-12 w-12 text-muted-foreground/50" />
            <p class="mt-3 font-semibold">لا توجد براندات بعد</p>
            <Button class="mt-4" as-child><Link :href="route('brands.index')">إضافة أول براند</Link></Button>
        </div>

        <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="brand in brands"
                :key="brand.id"
                :href="route('products.brand.show', brand.slug)"
                class="group overflow-hidden rounded-2xl border bg-card shadow-sm transition hover:-translate-y-1 hover:border-primary/30 hover:shadow-lg"
            >
                <div class="relative flex h-48 items-center justify-center overflow-hidden bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-900">
                    <img v-if="brand.logo_url" :src="brand.logo_url" :alt="brand.name" class="h-full w-full object-contain p-6 transition duration-300 group-hover:scale-105" />
                    <div v-else-if="brand.products.some((product) => product.image_url)" class="grid h-full w-full grid-cols-2">
                        <div v-for="product in brand.products.slice(0, 4)" :key="product.id" class="overflow-hidden border border-background/60 bg-muted">
                            <img v-if="product.image_url" :src="product.image_url" :alt="product.product_name" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" />
                            <div v-else class="flex h-full items-center justify-center"><ImageIcon class="h-7 w-7 text-muted-foreground/40" /></div>
                        </div>
                    </div>
                    <Building2 v-else class="h-16 w-16 text-muted-foreground/30" />
                    <span v-if="!brand.is_active" class="absolute end-3 top-3 rounded-full bg-slate-900/80 px-2.5 py-1 text-xs font-medium text-white">غير نشط</span>
                </div>

                <div class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-bold group-hover:text-primary">{{ brand.name }}</h2>
                            <p v-if="brand.description" class="mt-1 line-clamp-2 text-sm text-muted-foreground">{{ brand.description }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1 rounded-lg bg-muted px-2.5 py-1.5 text-sm font-semibold">
                            <Layers3 class="h-4 w-4" />
                            {{ formatInteger(brand.products_count) }}
                        </div>
                    </div>
                    <div class="mt-4 border-t pt-3 text-sm font-medium text-primary">عرض منتجات البراند</div>
                </div>
            </Link>
        </div>
    </div>
</template>
