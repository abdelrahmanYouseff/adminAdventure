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
    CreditCard,
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
import AppLayout from '@/layouts/AppLayout.vue';
import ProductSearchCombobox from '@/components/ProductSearchCombobox.vue';
import { formatCurrency } from '@/lib/formatNumber';
import { ref, computed, watch } from 'vue';

interface Product {
    id: number;
    product_name: string;
    description: string | null;
    price: number | string;
}

interface OrderItem {
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

defineOptions({ layout: AppLayout });

const form = useForm({
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    address: '',
    activity_date: '',
    currency: 'SAR',
    payment_method: 'cash',
    status: 'paid',
    notes: '',
    items: [] as OrderItem[],
});

const selectedProductId = ref<number | null>(null);
const selectedQuantity = ref(1);
const selectedUnitPrice = ref(0);

const subtotal = computed(() =>
    form.items.reduce((sum, item) => sum + (Number(item.total_price) || 0), 0),
);
const itemsCount = computed(() =>
    form.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0),
);

const selectedProduct = computed(() => {
    if (selectedProductId.value == null) return null;
    return props.products.find((p) => p.id === selectedProductId.value) ?? null;
});

function addItem() {
    if (selectedProductId.value == null || !selectedProduct.value) return;

    const product = selectedProduct.value;
    const existing = form.items.find((item) => item.product_id === product.id);

    if (existing) {
        existing.quantity += Number(selectedQuantity.value) || 1;
        existing.total_price = existing.quantity * existing.unit_price;
    } else {
        form.items.push({
            product_id: product.id,
            product_name: product.product_name,
            description: product.description || '',
            quantity: Number(selectedQuantity.value) || 1,
            unit_price: Number(selectedUnitPrice.value) || 0,
            total_price: (Number(selectedQuantity.value) || 1) * (Number(selectedUnitPrice.value) || 0),
        });
    }

    selectedProductId.value = null;
    selectedQuantity.value = 1;
    selectedUnitPrice.value = 0;
}

function removeItem(index: number) {
    form.items.splice(index, 1);
}

function updateItemPrice(index: number) {
    const item = form.items[index];
    item.total_price = Number(item.quantity) * Number(item.unit_price);
}

function submit() {
    form.post(route('orders.store'));
}

watch(selectedProductId, (newValue) => {
    if (newValue != null) {
        const product = props.products.find((p) => p.id === newValue);
        if (product) {
            selectedUnitPrice.value = Number(product.price) || 0;
        }
    }
});
</script>

<template>
    <Head title="إضافة طلب" />

    <div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <ShoppingCart class="h-6 w-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">إضافة طلب جديد</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        أدخل بيانات العميل، أضف المنتجات، ثم احفظ الطلب من النظام
                    </p>
                </div>
            </div>
            <Button as-child variant="outline" class="shrink-0 gap-2 self-start">
                <Link :href="route('orders.index')">
                    <ArrowRight class="h-4 w-4" />
                    العودة للطلبات
                </Link>
            </Button>
        </div>

        <div
            v-if="Object.keys(form.errors).length > 0"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300"
        >
            <p class="mb-1 font-semibold">يرجى تصحيح الأخطاء التالية:</p>
            <ul class="list-inside list-disc space-y-0.5">
                <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
            </ul>
        </div>

        <form class="grid gap-6 lg:grid-cols-3 lg:items-start" @submit.prevent="submit">
            <div class="space-y-6 lg:col-span-2">
                <section class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm">
                    <div class="flex items-center gap-3 border-b border-border/60 bg-muted/30 px-5 py-4">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                            <User class="h-4 w-4" />
                        </div>
                        <div>
                            <h2 class="font-semibold">بيانات العميل</h2>
                            <p class="text-xs text-muted-foreground">معلومات التواصل والفعالية</p>
                        </div>
                    </div>

                    <div class="space-y-5 p-5 sm:p-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2 sm:col-span-2">
                                <Label for="customer_name">
                                    اسم العميل <span class="text-red-500">*</span>
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
                                <Input
                                    id="customer_phone"
                                    v-model="form.customer_phone"
                                    type="tel"
                                    placeholder="05XXXXXXXX"
                                    class="h-11 rounded-xl"
                                    dir="ltr"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="activity_date" class="flex items-center gap-1.5">
                                    <Calendar class="h-3.5 w-3.5 text-muted-foreground" />
                                    تاريخ الفعالية
                                </Label>
                                <Input
                                    id="activity_date"
                                    v-model="form.activity_date"
                                    type="date"
                                    class="h-11 rounded-xl"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="payment_method" class="flex items-center gap-1.5">
                                    <CreditCard class="h-3.5 w-3.5 text-muted-foreground" />
                                    طريقة الدفع
                                </Label>
                                <select
                                    id="payment_method"
                                    v-model="form.payment_method"
                                    class="flex h-11 w-full rounded-xl border border-input bg-background px-3 text-sm"
                                >
                                    <option value="cash">نقدي</option>
                                    <option value="bank_transfer">تحويل بنكي</option>
                                    <option value="credit_card">بطاقة ائتمان</option>
                                    <option value="noon">Noon</option>
                                    <option value="paypal">PayPal</option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <Label for="status">حالة الطلب</Label>
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="flex h-11 w-full rounded-xl border border-input bg-background px-3 text-sm"
                                >
                                    <option value="paid">مدفوع (يظهر في أوامر العمل)</option>
                                    <option value="pending">قيد الانتظار</option>
                                    <option value="processing">قيد المعالجة</option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <Label for="currency">العملة</Label>
                                <select
                                    id="currency"
                                    v-model="form.currency"
                                    class="flex h-11 w-full rounded-xl border border-input bg-background px-3 text-sm"
                                >
                                    <option value="SAR">ريال سعودي (SAR)</option>
                                    <option value="USD">دولار (USD)</option>
                                    <option value="EUR">يورو (EUR)</option>
                                </select>
                            </div>

                            <div class="space-y-2 sm:col-span-2">
                                <Label for="address" class="flex items-center gap-1.5">
                                    <MapPin class="h-3.5 w-3.5 text-muted-foreground" />
                                    عنوان / موقع التركيب
                                </Label>
                                <Textarea
                                    id="address"
                                    v-model="form.address"
                                    placeholder="المدينة، الحي، الشارع أو رابط الموقع..."
                                    rows="2"
                                    class="resize-none rounded-xl"
                                />
                            </div>

                            <div class="space-y-2 sm:col-span-2">
                                <Label for="notes">ملاحظات</Label>
                                <Textarea
                                    id="notes"
                                    v-model="form.notes"
                                    placeholder="ملاحظات داخلية على الطلب..."
                                    rows="2"
                                    class="resize-none rounded-xl"
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 bg-muted/30 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                <Package class="h-4 w-4" />
                            </div>
                            <div>
                                <h2 class="font-semibold">منتجات الطلب</h2>
                                <p class="text-xs text-muted-foreground">الألعاب / المعدات والكميات</p>
                            </div>
                        </div>
                        <Badge v-if="form.items.length > 0" variant="secondary" class="tabular-nums">
                            {{ form.items.length }} منتج · {{ itemsCount }} وحدة
                        </Badge>
                    </div>

                    <div class="space-y-5 p-5 sm:p-6">
                        <div class="rounded-xl border border-dashed border-border bg-muted/20 p-4 sm:p-5">
                            <p class="mb-4 text-sm font-medium">إضافة منتج للطلب</p>
                            <div class="grid gap-3 sm:grid-cols-12 sm:items-end">
                                <div class="space-y-2 sm:col-span-5">
                                    <Label class="text-xs text-muted-foreground">المنتج</Label>
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
                                    <Label for="unit_price" class="text-xs text-muted-foreground">سعر الوحدة</Label>
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
                        </div>

                        <div v-if="form.items.length > 0" class="overflow-hidden rounded-xl border border-border/60">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-muted/40 hover:bg-muted/40">
                                        <TableHead class="font-semibold">المنتج</TableHead>
                                        <TableHead class="w-24 text-center font-semibold">الكمية</TableHead>
                                        <TableHead class="w-32 font-semibold">السعر</TableHead>
                                        <TableHead class="w-28 font-semibold">الإجمالي</TableHead>
                                        <TableHead class="w-12" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="(item, index) in form.items" :key="item.product_id">
                                        <TableCell class="font-medium">{{ item.product_name }}</TableCell>
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
                                        <TableCell class="font-semibold tabular-nums" dir="ltr">
                                            {{ formatCurrency(item.total_price) }}
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 text-muted-foreground hover:bg-red-50 hover:text-red-600"
                                                @click="removeItem(index)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <div
                            v-else
                            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border px-6 py-12 text-center"
                        >
                            <ShoppingCart class="mb-3 h-8 w-8 text-muted-foreground" />
                            <p class="font-medium">لا توجد منتجات بعد</p>
                            <p class="mt-1 text-sm text-muted-foreground">اختر منتجاً من البحث وأضفه للطلب</p>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="lg:sticky lg:top-6 lg:col-span-1">
                <div class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm">
                    <div class="flex items-center gap-3 border-b border-border/60 bg-muted/30 px-5 py-4">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                            <Receipt class="h-4 w-4" />
                        </div>
                        <div>
                            <h2 class="font-semibold">ملخص الطلب</h2>
                            <p class="text-xs text-muted-foreground">قبل الحفظ</p>
                        </div>
                    </div>

                    <div class="space-y-4 p-5">
                        <div class="space-y-3 rounded-xl bg-muted/30 p-4 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">عدد المنتجات</span>
                                <span class="font-medium tabular-nums">{{ form.items.length }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">إجمالي الوحدات</span>
                                <span class="font-medium tabular-nums">{{ itemsCount }}</span>
                            </div>
                            <div class="flex justify-between border-t border-border/60 pt-3">
                                <span class="text-muted-foreground">طريقة الدفع</span>
                                <span class="font-medium">
                                    {{
                                        {
                                            cash: 'نقدي',
                                            bank_transfer: 'تحويل بنكي',
                                            credit_card: 'بطاقة',
                                            noon: 'Noon',
                                            paypal: 'PayPal',
                                        }[form.payment_method] || form.payment_method
                                    }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between rounded-xl bg-primary/5 px-4 py-4 ring-1 ring-primary/10">
                            <span class="font-semibold">الإجمالي</span>
                            <span class="text-xl font-bold tabular-nums text-primary" dir="ltr">
                                {{ formatCurrency(subtotal) }}
                            </span>
                        </div>

                        <p
                            v-if="form.status === 'paid'"
                            class="rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300"
                        >
                            عند الحفظ كـ «مدفوع» سيظهر الطلب تلقائياً في أوامر العمل.
                        </p>

                        <div class="space-y-2">
                            <Button
                                type="submit"
                                class="h-11 w-full gap-2 rounded-xl text-base font-semibold"
                                :disabled="form.processing || form.items.length === 0"
                            >
                                <ShoppingCart class="h-4 w-4" />
                                {{ form.processing ? 'جاري الحفظ...' : 'إنشاء الطلب' }}
                            </Button>
                            <Button as-child type="button" variant="outline" class="h-11 w-full rounded-xl">
                                <Link :href="route('orders.index')">إلغاء</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </aside>
        </form>
    </div>
</template>
