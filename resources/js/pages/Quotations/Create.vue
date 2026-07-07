<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    ArrowRight,
    Calendar,
    FileSpreadsheet,
    Mail,
    MapPin,
    Package,
    Phone,
    Plus,
    Receipt,
    ShoppingCart,
    Trash2,
    User,
} from 'lucide-vue-next';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import ProductSearchCombobox from '@/components/ProductSearchCombobox.vue';
import { formatCurrency } from '@/lib/formatNumber';
import { ref, computed, watch } from 'vue';
import type { BreadcrumbItem } from '@/types';

interface Product {
    id: number;
    product_name: string;
    description: string;
    price: number;
}

interface QuotationItem {
    product_id: number;
    product_name: string;
    description: string;
    quantity: number;
    unit_price: number;
    total_price: number;
}

interface Props {
    products: Product[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'عروض الأسعار', href: route('quotations.index') },
    { title: 'إنشاء عرض جديد', href: route('quotations.create') },
];

const form = useForm({
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    customer_address: '',
    valid_until: '',
    notes: '',
    items: [] as QuotationItem[],
});

const selectedProductId = ref<number | null>(null);
const selectedQuantity = ref(1);
const selectedUnitPrice = ref(0);

const subtotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (parseFloat(String(item.total_price)) || 0), 0);
});

const taxAmount = computed(() => subtotal.value * 0.15);
const totalAmount = computed(() => subtotal.value + taxAmount.value);
const itemsCount = computed(() => form.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0));

const selectedProduct = computed(() => {
    if (selectedProductId.value == null) return null;
    return props.products.find(p => p.id === selectedProductId.value) ?? null;
});

const addItem = () => {
    if (selectedProductId.value == null || !selectedProduct.value) return;

    const product = selectedProduct.value;

    form.items.push({
        product_id: product.id,
        product_name: product.product_name,
        description: product.description,
        quantity: selectedQuantity.value,
        unit_price: selectedUnitPrice.value,
        total_price: selectedQuantity.value * selectedUnitPrice.value,
    });

    selectedProductId.value = null;
    selectedQuantity.value = 1;
    selectedUnitPrice.value = 0;
};

const removeItem = (index: number) => {
    form.items.splice(index, 1);
};

const updateItemPrice = (index: number) => {
    const item = form.items[index];
    item.total_price = item.quantity * item.unit_price;
};

const submit = () => {
    form.post(route('quotations.store'));
};

watch(selectedProductId, (newValue) => {
    if (newValue != null) {
        const product = props.products.find(p => p.id === newValue);
        if (product) {
            selectedUnitPrice.value = product.price;
        }
    }
});
</script>

<template>
    <Head title="إنشاء عرض سعر" />

    <AppSidebarLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <FileSpreadsheet class="h-6 w-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">إنشاء عرض سعر</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            أدخل بيانات العميل، أضف المنتجات، ثم احفظ عرض السعر
                        </p>
                    </div>
                </div>
                <Button as-child variant="outline" class="shrink-0 gap-2 self-start">
                    <Link :href="route('quotations.index')">
                        <ArrowRight class="h-4 w-4" />
                        العودة للقائمة
                    </Link>
                </Button>
            </div>

            <!-- Errors -->
            <div
                v-if="Object.keys(form.errors).length > 0"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300"
            >
                <p class="mb-1 font-semibold">يرجى تصحيح الأخطاء التالية:</p>
                <ul class="list-inside list-disc space-y-0.5">
                    <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                </ul>
            </div>

            <form @submit.prevent="submit" class="grid gap-6 lg:grid-cols-3 lg:items-start">
                <!-- Main column -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Customer -->
                    <section class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm">
                        <div class="flex items-center gap-3 border-b border-border/60 bg-muted/30 px-5 py-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                <User class="h-4 w-4" />
                            </div>
                            <div>
                                <h2 class="font-semibold text-foreground">بيانات العميل</h2>
                                <p class="text-xs text-muted-foreground">معلومات التواصل وصلاحية العرض</p>
                            </div>
                        </div>

                        <div class="space-y-5 p-5 sm:p-6">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="customer_name" class="flex items-center gap-1.5">
                                        <User class="h-3.5 w-3.5 text-muted-foreground" />
                                        اسم العميل
                                        <span class="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="customer_name"
                                        v-model="form.customer_name"
                                        placeholder="مثال: أحمد محمد"
                                        class="h-11 rounded-xl"
                                        required
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="customer_email" class="flex items-center gap-1.5">
                                        <Mail class="h-3.5 w-3.5 text-muted-foreground" />
                                        البريد الإلكتروني
                                    </Label>
                                    <Input
                                        id="customer_email"
                                        v-model="form.customer_email"
                                        type="email"
                                        placeholder="example@email.com"
                                        class="h-11 rounded-xl"
                                        dir="ltr"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="customer_phone" class="flex items-center gap-1.5">
                                        <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                                        رقم الجوال
                                    </Label>
                                    <div class="flex h-11 overflow-hidden rounded-xl border border-input bg-background" dir="ltr">
                                        <span class="flex shrink-0 items-center border-e border-input bg-muted/50 px-3 text-sm font-medium text-muted-foreground">
                                            +966
                                        </span>
                                        <Input
                                            id="customer_phone"
                                            v-model="form.customer_phone"
                                            type="tel"
                                            inputmode="numeric"
                                            placeholder="5XXXXXXXX"
                                            class="h-full border-0 shadow-none focus-visible:ring-0"
                                        />
                                    </div>
                                </div>

                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="valid_until" class="flex items-center gap-1.5">
                                        <Calendar class="h-3.5 w-3.5 text-muted-foreground" />
                                        صالح حتى
                                        <span class="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="valid_until"
                                        v-model="form.valid_until"
                                        type="date"
                                        class="h-11 rounded-xl"
                                        required
                                    />
                                </div>

                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="customer_address" class="flex items-center gap-1.5">
                                        <MapPin class="h-3.5 w-3.5 text-muted-foreground" />
                                        العنوان
                                    </Label>
                                    <Textarea
                                        id="customer_address"
                                        v-model="form.customer_address"
                                        placeholder="المدينة، الحي، الشارع..."
                                        rows="2"
                                        class="rounded-xl resize-none"
                                    />
                                </div>

                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="notes" class="flex items-center gap-1.5">
                                        <FileSpreadsheet class="h-3.5 w-3.5 text-muted-foreground" />
                                        ملاحظات
                                    </Label>
                                    <Textarea
                                        id="notes"
                                        v-model="form.notes"
                                        placeholder="شروط خاصة، ملاحظات للعميل..."
                                        rows="2"
                                        class="rounded-xl resize-none"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Products -->
                    <section class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 bg-muted/30 px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    <Package class="h-4 w-4" />
                                </div>
                                <div>
                                    <h2 class="font-semibold text-foreground">بنود العرض</h2>
                                    <p class="text-xs text-muted-foreground">المنتجات والكميات والأسعار</p>
                                </div>
                            </div>
                            <Badge v-if="form.items.length > 0" variant="secondary" class="tabular-nums">
                                {{ form.items.length }} منتج · {{ itemsCount }} وحدة
                            </Badge>
                        </div>

                        <div class="space-y-5 p-5 sm:p-6">
                            <!-- Add item bar -->
                            <div class="rounded-xl border border-dashed border-border bg-muted/20 p-4 sm:p-5">
                                <p class="mb-4 text-sm font-medium text-foreground">إضافة منتج للعرض</p>
                                <div class="grid gap-3 sm:grid-cols-12 sm:items-end">
                                    <div class="space-y-2 sm:col-span-5">
                                        <Label for="product-search" class="text-xs text-muted-foreground">المنتج</Label>
                                        <ProductSearchCombobox
                                            v-model="selectedProductId"
                                            :products="products"
                                            input-id="product-search"
                                        />
                                    </div>
                                    <div class="space-y-2 sm:col-span-2">
                                        <Label for="quantity" class="text-xs text-muted-foreground">الكمية</Label>
                                        <Input
                                            id="quantity"
                                            v-model="selectedQuantity"
                                            type="number"
                                            min="1"
                                            class="h-11 rounded-xl tabular-nums"
                                        />
                                    </div>
                                    <div class="space-y-2 sm:col-span-3">
                                        <Label for="unit_price" class="text-xs text-muted-foreground">سعر الوحدة (ر.س)</Label>
                                        <Input
                                            id="unit_price"
                                            v-model="selectedUnitPrice"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="h-11 rounded-xl tabular-nums"
                                            dir="ltr"
                                        />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <Button
                                            type="button"
                                            class="h-11 w-full gap-2 rounded-xl"
                                            :disabled="selectedProductId == null"
                                            @click="addItem"
                                        >
                                            <Plus class="h-4 w-4" />
                                            إضافة
                                        </Button>
                                    </div>
                                </div>
                                <p v-if="selectedProduct" class="mt-3 text-xs text-muted-foreground">
                                    السعر الافتراضي: <span class="font-medium tabular-nums text-foreground" dir="ltr">{{ formatCurrency(selectedProduct.price) }}</span>
                                </p>
                            </div>

                            <!-- Items table -->
                            <div v-if="form.items.length > 0" class="overflow-hidden rounded-xl border border-border/60">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/40 hover:bg-muted/40">
                                            <TableHead class="font-semibold">المنتج</TableHead>
                                            <TableHead class="hidden font-semibold md:table-cell">الوصف</TableHead>
                                            <TableHead class="w-24 font-semibold text-center">الكمية</TableHead>
                                            <TableHead class="w-32 font-semibold">السعر</TableHead>
                                            <TableHead class="w-28 font-semibold">الإجمالي</TableHead>
                                            <TableHead class="w-12" />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="(item, index) in form.items"
                                            :key="index"
                                            class="group"
                                        >
                                            <TableCell>
                                                <div class="font-medium text-foreground">{{ item.product_name }}</div>
                                                <div class="mt-0.5 line-clamp-1 text-xs text-muted-foreground md:hidden">
                                                    {{ item.description }}
                                                </div>
                                            </TableCell>
                                            <TableCell class="hidden max-w-[200px] truncate text-sm text-muted-foreground md:table-cell">
                                                {{ item.description || '—' }}
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    v-model="item.quantity"
                                                    type="number"
                                                    min="1"
                                                    class="mx-auto h-9 w-16 rounded-lg text-center tabular-nums"
                                                    @input="updateItemPrice(index)"
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    v-model="item.unit_price"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    class="h-9 w-full min-w-[5.5rem] rounded-lg tabular-nums"
                                                    dir="ltr"
                                                    @input="updateItemPrice(index)"
                                                />
                                            </TableCell>
                                            <TableCell class="font-semibold tabular-nums text-foreground" dir="ltr">
                                                {{ formatCurrency(item.total_price) }}
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-8 w-8 rounded-lg text-muted-foreground opacity-70 transition-opacity hover:bg-red-50 hover:text-red-600 group-hover:opacity-100 dark:hover:bg-red-950/30"
                                                    title="حذف البند"
                                                    @click="removeItem(index)"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>

                            <!-- Empty state -->
                            <div
                                v-else
                                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/10 px-6 py-12 text-center"
                            >
                                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted">
                                    <ShoppingCart class="h-7 w-7 text-muted-foreground" />
                                </div>
                                <p class="font-medium text-foreground">لا توجد بنود بعد</p>
                                <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                                    اختر منتجاً من البحث أعلاه واضغط «إضافة» لبناء عرض السعر
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Sidebar summary -->
                <aside class="lg:sticky lg:top-6 lg:col-span-1">
                    <div class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm">
                        <div class="flex items-center gap-3 border-b border-border/60 bg-muted/30 px-5 py-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                <Receipt class="h-4 w-4" />
                            </div>
                            <div>
                                <h2 class="font-semibold text-foreground">ملخص العرض</h2>
                                <p class="text-xs text-muted-foreground">الحسابات قبل الحفظ</p>
                            </div>
                        </div>

                        <div class="space-y-4 p-5">
                            <div class="space-y-3 rounded-xl bg-muted/30 p-4">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">عدد البنود</span>
                                    <span class="font-medium tabular-nums">{{ form.items.length }}</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">إجمالي الوحدات</span>
                                    <span class="font-medium tabular-nums">{{ itemsCount }}</span>
                                </div>
                                <div class="border-t border-border/60 pt-3">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-muted-foreground">المجموع الفرعي</span>
                                        <span class="font-medium tabular-nums" dir="ltr">{{ formatCurrency(subtotal) }}</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-sm">
                                        <span class="text-muted-foreground">ض.ق.م (15%)</span>
                                        <span class="font-medium tabular-nums" dir="ltr">{{ formatCurrency(taxAmount) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between rounded-xl bg-primary/5 px-4 py-4 ring-1 ring-primary/10">
                                <span class="font-semibold text-foreground">الإجمالي النهائي</span>
                                <span class="text-xl font-bold tabular-nums text-primary" dir="ltr">
                                    {{ formatCurrency(totalAmount) }}
                                </span>
                            </div>

                            <div class="space-y-2 pt-1">
                                <Button
                                    type="submit"
                                    class="h-11 w-full gap-2 rounded-xl text-base font-semibold"
                                    :disabled="form.processing || form.items.length === 0"
                                >
                                    <FileSpreadsheet class="h-4 w-4" />
                                    {{ form.processing ? 'جاري الحفظ...' : 'إنشاء عرض السعر' }}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-11 w-full rounded-xl"
                                    @click="$inertia.visit(route('quotations.index'))"
                                >
                                    إلغاء
                                </Button>
                            </div>

                            <p v-if="form.items.length === 0" class="text-center text-xs text-muted-foreground">
                                أضف منتجاً واحداً على الأقل لتفعيل الحفظ
                            </p>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </AppSidebarLayout>
</template>
