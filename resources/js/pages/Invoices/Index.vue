<template>
  <AppLayout title="Invoices">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Invoices
        </h2>
        <div class="flex items-center space-x-2">
          <Button @click="updateOverdueInvoices" variant="outline" size="sm">
            <Icon name="refresh-cw" class="w-4 h-4 mr-2" />
            Update Overdue
          </Button>
          <Button @click="exportInvoices" variant="outline" size="sm">
            <Icon name="download" class="w-4 h-4 mr-2" />
            Export CSV
          </Button>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Invoices Table -->
        <Card>
          <CardHeader>
            <CardTitle>Invoices List</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="text-left py-3 px-4 font-medium text-gray-900 dark:text-gray-100">Invoice #</th>
                    <th class="text-left py-3 px-4 font-medium text-gray-900 dark:text-gray-100">Customer</th>
                    <th class="text-left py-3 px-4 font-medium text-gray-900 dark:text-gray-100">Amount</th>
                    <th class="text-left py-3 px-4 font-medium text-gray-900 dark:text-gray-100">Status</th>
                    <th class="text-left py-3 px-4 font-medium text-gray-900 dark:text-gray-100">Payment Method</th>
                    <th class="text-left py-3 px-4 font-medium text-gray-900 dark:text-gray-100">Date</th>
                    <th class="text-left py-3 px-4 font-medium text-gray-900 dark:text-gray-100">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="invoice in invoices.data"
                    :key="invoice.id"
                    class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50"
                  >
                    <td class="py-4 px-4">
                      <div class="font-medium text-gray-900 dark:text-gray-100">
                        {{ invoice.invoice_number }}
                      </div>
                    </td>
                    <td class="py-4 px-4">
                      <div class="flex items-center">
                        <Avatar class="w-8 h-8 mr-3">
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
                    <td class="py-4 px-4">
                      <div class="font-medium text-gray-900 dark:text-gray-100">
                        {{ formatCurrency(invoice.amount) }}
                      </div>
                    </td>
                    <td class="py-4 px-4">
                      <span
                        :class="getStatusBadgeClass(invoice.status)"
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                      >
                        {{ formatStatus(invoice.status) }}
                      </span>
                    </td>
                    <td class="py-4 px-4">
                      <span class="text-sm text-gray-600 dark:text-gray-400">
                        {{ formatPaymentMethod(invoice.payment_method) }}
                      </span>
                    </td>
                    <td class="py-4 px-4">
                      <div class="text-sm text-gray-600 dark:text-gray-400">
                        {{ formatDate(invoice.created_at) }}
                      </div>
                    </td>
                    <td class="py-4 px-4">
                      <div class="flex items-center space-x-2">
                        <Button
                          @click="viewInvoice(invoice)"
                          variant="ghost"
                          size="sm"
                        >
                          <Icon name="eye" class="w-4 h-4" />
                        </Button>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                              <Icon name="more-horizontal" class="w-4 h-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent>
                            <DropdownMenuItem @click="viewInvoice(invoice)">
                              <Icon name="eye" class="w-4 h-4 mr-2" />
                              عرض PDF
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="updateStatus(invoice, 'paid')" v-if="invoice.status !== 'paid'">
                              <Icon name="check" class="w-4 h-4 mr-2" />
                              Mark as Paid
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="updateStatus(invoice, 'cancelled')" v-if="invoice.status !== 'cancelled'">
                              <Icon name="x" class="w-4 h-4 mr-2" />
                              Mark as Cancelled
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6 flex items-center justify-between">
              <div class="text-sm text-gray-700 dark:text-gray-300">
                Showing {{ invoices.from }} to {{ invoices.to }} of {{ invoices.total }} results
              </div>
              <div class="flex items-center space-x-2">
                <Button
                  v-if="invoices.prev_page_url"
                  @click="goToPage(invoices.current_page - 1)"
                  variant="outline"
                  size="sm"
                >
                  Previous
                </Button>
                <Button
                  v-if="invoices.next_page_url"
                  @click="goToPage(invoices.current_page + 1)"
                  variant="outline"
                  size="sm"
                >
                  Next
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

const props = defineProps({
  invoices: Object,
})

const goToPage = (page) => {
  const params = new URLSearchParams(window.location.search)
  params.set('page', page)
  router.get(route('invoices.index'), params.toString())
}

const viewInvoice = (invoice) => {
  // Open PDF in new tab
  window.open(route('invoices.pdf', invoice.id), '_blank')
}

const updateStatus = (invoice, status) => {
  router.patch(route('invoices.update-status', invoice.id), { status })
}

const exportInvoices = () => {
  window.open(route('invoices.export'), '_blank')
}

const updateOverdueInvoices = () => {
  router.patch(route('invoices.update-overdue'), {}, {
    onSuccess: () => {
      // Refresh the page to show updated stats
      router.reload()
    }
  })
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'SAR',
  }).format(amount)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const formatStatus = (status) => {
  const statusMap = {
    pending: 'Pending',
    paid: 'Paid',
    cancelled: 'Cancelled',
    overdue: 'Overdue',
  }
  return statusMap[status] || status
}

const formatPaymentMethod = (method) => {
  const methodMap = {
    noon: 'Noon',
    cash: 'Cash',
    bank_transfer: 'Bank Transfer',
  }
  return methodMap[method] || method || 'N/A'
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
    .map(word => word.charAt(0))
    .join('')
    .toUpperCase()
    .slice(0, 2)
}
</script>
