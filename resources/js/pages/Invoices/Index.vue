<template>
  <AppLayout title="الفواتير">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
          الفواتير
        </h2>
        <div class="flex items-center space-x-2">
          <Button @click="updateOverdueInvoices" variant="outline" size="sm">
            <Icon name="refresh-cw" class="mr-2 h-4 w-4" />
            تحديث المتأخرة
          </Button>
          <Button @click="exportInvoices" variant="outline" size="sm">
            <Icon name="download" class="mr-2 h-4 w-4" />
            تصدير CSV
          </Button>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <Card>
          <CardHeader>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <CardTitle>
                  {{ selectedBrand ? `فواتير ${selectedBrand.name}` : 'جميع الفواتير' }}
                </CardTitle>
                <p class="mt-1 text-sm text-muted-foreground">
                  {{ selectedBrand
                    ? `عرض فواتير براند ${selectedBrand.name}`
                    : 'قائمة بجميع الفواتير في النظام' }}
                </p>
              </div>
              <div class="flex w-full flex-col gap-2 sm:w-auto sm:min-w-[14rem]">
                <label for="brand-filter" class="text-sm font-medium text-muted-foreground">البراند</label>
                <select
                  id="brand-filter"
                  class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                  :value="selectedBrandId ?? ''"
                  @change="applyBrandFilter($event.target.value)"
                >
                  <option value="">كل البراندات</option>
                  <option v-for="brand in brands" :key="brand.id" :value="brand.id">
                    {{ brand.name }} ({{ formatInteger(brand.invoices_count) }})
                  </option>
                </select>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="px-4 py-3 text-left font-medium text-gray-900 dark:text-gray-100">رقم الفاتورة</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-900 dark:text-gray-100">البراند</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-900 dark:text-gray-100">العميل</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-900 dark:text-gray-100">المبلغ</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-900 dark:text-gray-100">الحالة</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-900 dark:text-gray-100">طريقة الدفع</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-900 dark:text-gray-100">التاريخ</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-900 dark:text-gray-100">إجراءات</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="invoice in invoices.data"
                    :key="invoice.id"
                    class="border-b border-gray-100 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50"
                  >
                    <td class="px-4 py-4">
                      <div class="font-medium text-gray-900 dark:text-gray-100">
                        {{ invoice.invoice_number }}
                      </div>
                    </td>
                    <td class="px-4 py-4 text-gray-900 dark:text-gray-100">
                      {{ invoice.brand?.name || '—' }}
                    </td>
                    <td class="px-4 py-4">
                      <div class="flex items-center">
                        <Avatar class="mr-3 h-8 w-8">
                          <AvatarFallback>
                            {{ getInitials(invoice.user?.full_name || invoice.user?.name) }}
                          </AvatarFallback>
                        </Avatar>
                        <div>
                          <div class="font-medium text-gray-900 dark:text-gray-100">
                            {{ invoice.user?.full_name || invoice.user?.name }}
                          </div>
                          <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ invoice.user?.email }}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="font-medium text-gray-900 dark:text-gray-100">
                        {{ formatCurrency(invoice.amount) }}
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span
                        :class="getStatusBadgeClass(invoice.status)"
                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                      >
                        {{ formatStatus(invoice.status) }}
                      </span>
                    </td>
                    <td class="px-4 py-4">
                      <span class="text-sm text-gray-600 dark:text-gray-400">
                        {{ formatPaymentMethod(invoice.payment_method) }}
                      </span>
                    </td>
                    <td class="px-4 py-4">
                      <div class="text-sm text-gray-600 dark:text-gray-400">
                        {{ formatDate(invoice.created_at) }}
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="flex items-center space-x-2">
                        <Button @click="viewInvoice(invoice)" variant="ghost" size="sm">
                          <Icon name="eye" class="h-4 w-4" />
                        </Button>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                              <Icon name="more-horizontal" class="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent>
                            <DropdownMenuItem @click="viewInvoice(invoice)">
                              <Icon name="eye" class="mr-2 h-4 w-4" />
                              عرض PDF
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="updateStatus(invoice, 'paid')" v-if="invoice.status !== 'paid'">
                              <Icon name="check" class="mr-2 h-4 w-4" />
                              تعيين كمدفوعة
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="updateStatus(invoice, 'cancelled')" v-if="invoice.status !== 'cancelled'">
                              <Icon name="x" class="mr-2 h-4 w-4" />
                              إلغاء الفاتورة
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="invoices.data.length === 0">
                    <td colspan="8" class="px-4 py-8 text-center text-muted-foreground">
                      {{ selectedBrand ? 'لا توجد فواتير لهذا البراند.' : 'لا توجد فواتير بعد.' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-6 flex items-center justify-between">
              <div class="text-sm text-gray-700 dark:text-gray-300">
                عرض {{ formatInteger(invoices.from) }} إلى {{ formatInteger(invoices.to) }} من {{ formatInteger(invoices.total) }} نتيجة
              </div>
              <div class="flex items-center space-x-2">
                <Button
                  v-if="invoices.prev_page_url"
                  @click="goToPage(invoices.current_page - 1)"
                  variant="outline"
                  size="sm"
                >
                  السابق
                </Button>
                <Button
                  v-if="invoices.next_page_url"
                  @click="goToPage(invoices.current_page + 1)"
                  variant="outline"
                  size="sm"
                >
                  التالي
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import Icon from '@/components/Icon.vue'
import { formatCurrency, formatDate, formatInteger } from '@/lib/formatNumber'

const props = defineProps({
  invoices: Object,
  brands: {
    type: Array,
    default: () => [],
  },
  selectedBrandId: {
    type: Number,
    default: null,
  },
})

const selectedBrand = computed(() =>
  props.brands.find((brand) => brand.id === props.selectedBrandId) ?? null,
)

function applyBrandFilter(brandId) {
  router.get(
    route('invoices.index'),
    brandId ? { brand: brandId } : {},
    { preserveState: true, preserveScroll: true, replace: true },
  )
}

const goToPage = (page) => {
  router.get(route('invoices.index'), {
    page,
    ...(props.selectedBrandId ? { brand: props.selectedBrandId } : {}),
  }, { preserveState: true, preserveScroll: true })
}

const viewInvoice = (invoice) => {
  window.open(route('invoices.pdf', invoice.id), '_blank')
}

const updateStatus = (invoice, status) => {
  router.patch(route('invoices.update-status', invoice.id), { status })
}

const exportInvoices = () => {
  const params = new URLSearchParams()
  if (props.selectedBrandId) {
    params.set('brand', String(props.selectedBrandId))
  }
  const query = params.toString()
  window.open(route('invoices.export') + (query ? `?${query}` : ''), '_blank')
}

const updateOverdueInvoices = () => {
  router.patch(route('invoices.update-overdue'), {}, {
    onSuccess: () => {
      router.reload()
    },
  })
}

const formatStatus = (status) => {
  const statusMap = {
    pending: 'قيد الانتظار',
    paid: 'مدفوعة',
    cancelled: 'ملغاة',
    overdue: 'متأخرة',
  }
  return statusMap[status] || status
}

const formatPaymentMethod = (method) => {
  const methodMap = {
    noon: 'Noon',
    cash: 'نقدي',
    bank_transfer: 'تحويل بنكي',
    mock: 'تجريبي',
  }
  return methodMap[method] || method || '—'
}

const getStatusBadgeClass = (status) => {
  const classes = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    overdue: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
  }
  return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
}

const getInitials = (name) => {
  if (!name) return '?'
  return name
    .split(' ')
    .map((word) => word.charAt(0))
    .join('')
    .toUpperCase()
    .slice(0, 2)
}
</script>
