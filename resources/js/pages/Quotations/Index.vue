<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Plus, MoreHorizontal, Eye, Edit, Download, Trash2 } from 'lucide-vue-next';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { formatDate, formatInteger, formatPrice } from '@/lib/formatNumber';

interface Brand {
    id: number;
    name: string;
    slug: string;
    quotations_count: number;
}

interface Quotation {
    id: number;
    quotation_number: string;
    customer_name: string;
    customer_email: string;
    total_amount: number;
    status: string;
    valid_until: string;
    created_at: string;
    brand?: Brand | null;
    user: {
        name: string;
    };
}

interface Props {
    quotations: {
        data: Quotation[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    brands: Brand[];
    selectedBrandId: number | null;
}

const props = defineProps<Props>();

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);

const selectedBrand = computed(() =>
    props.brands.find((brand) => brand.id === props.selectedBrandId) ?? null,
);

onMounted(() => {
    const pdfId = page.props.flash?.open_pdf as number | undefined;
    if (pdfId) {
        window.open(quotationPdfUrl(pdfId), '_blank');
    }
});

function applyBrandFilter(brandId: string) {
    router.get(
        route('quotations.index'),
        brandId ? { brand: brandId } : {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'draft':
            return 'secondary';
        case 'sent':
            return 'default';
        case 'accepted':
            return 'default';
        case 'rejected':
            return 'destructive';
        case 'expired':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const getStatusText = (status: string) => {
    switch (status) {
        case 'draft':
            return 'مسودة';
        case 'sent':
            return 'مرسل';
        case 'accepted':
            return 'مقبول';
        case 'rejected':
            return 'مرفوض';
        case 'expired':
            return 'منتهي الصلاحية';
        default:
            return status;
    }
};

const deleteQuotation = (id: number) => {
    if (confirm('هل أنت متأكد من حذف هذا العرض؟')) {
        router.delete(route('quotations.destroy', id));
    }
};

function quotationPdfUrl(id: number): string {
    return `/quotations/${id}/pdf?v=${Date.now()}`;
}
</script>

<template>
    <Head title="عروض الأسعار" />

    <AppSidebarLayout>
        <div class="space-y-6">
            <p
                v-if="successMessage"
                class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300"
            >
                {{ successMessage }}
            </p>

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">عروض الأسعار</h1>
                    <p class="text-muted-foreground">
                        إدارة ومتابعة طلبات عروض الأسعار
                    </p>
                </div>
                <Link :href="route('quotations.create')">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        عرض جديد
                    </Button>
                </Link>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">إجمالي العروض</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold tabular-nums">{{ formatInteger(props.quotations.total) }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">مسودة</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold tabular-nums">
                            {{ formatInteger(props.quotations.data.filter(q => q.status === 'draft').length) }}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">مرسل</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold tabular-nums">
                            {{ formatInteger(props.quotations.data.filter(q => q.status === 'sent').length) }}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">مقبول</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold tabular-nums">
                            {{ formatInteger(props.quotations.data.filter(q => q.status === 'accepted').length) }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Quotations Table -->
            <Card>
                <CardHeader>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <CardTitle>
                                {{ selectedBrand ? `عروض ${selectedBrand.name}` : 'جميع العروض' }}
                            </CardTitle>
                            <CardDescription>
                                {{ selectedBrand
                                    ? `عرض عروض الأسعار الخاصة ببراند ${selectedBrand.name}`
                                    : 'قائمة بجميع عروض الأسعار في النظام' }}
                            </CardDescription>
                        </div>
                        <div class="flex w-full flex-col gap-2 sm:w-auto sm:min-w-[14rem]">
                            <label for="brand-filter" class="text-sm font-medium text-muted-foreground">البراند</label>
                            <select
                                id="brand-filter"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                :value="selectedBrandId ?? ''"
                                @change="applyBrandFilter(($event.target as HTMLSelectElement).value)"
                            >
                                <option value="">كل البراندات</option>
                                <option v-for="brand in brands" :key="brand.id" :value="brand.id">
                                    {{ brand.name }} ({{ formatInteger(brand.quotations_count) }})
                                </option>
                            </select>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>رقم العرض</TableHead>
                                <TableHead>البراند</TableHead>
                                <TableHead>العميل</TableHead>
                                <TableHead>المبلغ</TableHead>
                                <TableHead>الحالة</TableHead>
                                <TableHead>صالح حتى</TableHead>
                                <TableHead>أنشأه</TableHead>
                                <TableHead>تاريخ الإنشاء</TableHead>
                                <TableHead class="w-[50px]">الإجراءات</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="quotation in props.quotations.data" :key="quotation.id">
                                <TableCell class="font-medium">
                                    {{ quotation.quotation_number }}
                                </TableCell>
                                <TableCell>
                                    {{ quotation.brand?.name || '—' }}
                                </TableCell>
                                <TableCell>
                                    <div>
                                        <div class="font-medium">{{ quotation.customer_name }}</div>
                                        <div class="text-sm text-muted-foreground">{{ quotation.customer_email }}</div>
                                    </div>
                                </TableCell>
                                <TableCell class="tabular-nums" dir="ltr">
                                    {{ formatPrice(quotation.total_amount) }} ر.س
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="getStatusBadgeVariant(quotation.status)">
                                        {{ getStatusText(quotation.status) }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="tabular-nums" dir="ltr">
                                    {{ formatDate(quotation.valid_until) }}
                                </TableCell>
                                <TableCell>
                                    {{ quotation.user?.name || 'غير معروف' }}
                                </TableCell>
                                <TableCell class="tabular-nums" dir="ltr">
                                    {{ formatDate(quotation.created_at) }}
                                </TableCell>
                                <TableCell>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" class="h-8 w-8 p-0">
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem as-child>
                                                <a
                                                    :href="quotationPdfUrl(quotation.id)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="relative flex cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground"
                                                >
                                                    <Eye class="ms-2 h-4 w-4" />
                                                    عرض PDF
                                                </a>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem as-child>
                                                <Link :href="route('quotations.edit', quotation.id)">
                                                    <Edit class="ms-2 h-4 w-4" />
                                                    تعديل
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem as-child>
                                                <a
                                                    :href="quotationPdfUrl(quotation.id)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="relative flex cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground"
                                                >
                                                    <Download class="ms-2 h-4 w-4" />
                                                    تحميل PDF
                                                </a>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="deleteQuotation(quotation.id)" class="text-red-600">
                                                <Trash2 class="ms-2 h-4 w-4" />
                                                حذف
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="props.quotations.data.length === 0">
                                <TableCell colspan="9" class="h-24 text-center text-muted-foreground">
                                    {{ selectedBrand ? 'لا توجد عروض أسعار لهذا البراند.' : 'لا توجد عروض أسعار بعد.' }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppSidebarLayout>
</template>
