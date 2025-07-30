<template>
  <AppLayout :title="`Invoice #${invoice.invoice_number}`">
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <Button @click="$router.back()" variant="outline" size="sm">
            <Icon name="arrow-left" class="w-4 h-4 mr-2" />
            Back
          </Button>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Invoice #{{ invoice.invoice_number }}
          </h2>
        </div>
        <div class="flex items-center space-x-2">
          <Button @click="printInvoice" variant="outline" size="sm">
            <Icon name="printer" class="w-4 h-4 mr-2" />
            Print
          </Button>
          <Button @click="downloadPDF" variant="outline" size="sm">
            <Icon name="download" class="w-4 h-4 mr-2" />
            Download PDF
          </Button>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Invoice Details -->
          <div class="lg:col-span-2">
            <Card>
              <CardHeader>
                <CardTitle>Invoice Details</CardTitle>
              </CardHeader>
              <CardContent>
                <div class="grid grid-cols-2 gap-6">
                  <div>
                    <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Invoice Number</Label>
                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ invoice.invoice_number }}</p>
                  </div>
                  <div>
                    <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</Label>
                    <span
                      :class="getStatusBadgeClass(invoice.status)"
                      class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                    >
                      {{ formatStatus(invoice.status) }}
                    </span>
                  </div>
                  <div>
                    <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Amount</Label>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ formatCurrency(invoice.amount) }}</p>
                  </div>
                  <div>
                    <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Payment Method</Label>
                    <p class="text-lg text-gray-900 dark:text-gray-100">{{ formatPaymentMethod(invoice.payment_method) }}</p>
                  </div>
                  <div>
                    <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Created Date</Label>
                    <p class="text-lg text-gray-900 dark:text-gray-100">{{ formatDate(invoice.created_at) }}</p>
                  </div>
                  <div>
                    <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Due Date</Label>
                    <p class="text-lg text-gray-900 dark:text-gray-100">{{ formatDate(invoice.due_date) }}</p>
                  </div>
                </div>

                <!-- Customer Information -->
                <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                  <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Customer Information</h3>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</Label>
                      <p class="text-lg text-gray-900 dark:text-gray-100">{{ invoice.user?.full_name || invoice.user?.name }}</p>
                    </div>
                    <div>
                      <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</Label>
                      <p class="text-lg text-gray-900 dark:text-gray-100">{{ invoice.user?.email }}</p>
                    </div>
                    <div>
                      <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</Label>
                      <p class="text-lg text-gray-900 dark:text-gray-100">{{ invoice.user?.phone || 'N/A' }}</p>
                    </div>
                    <div>
                      <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Country</Label>
                      <p class="text-lg text-gray-900 dark:text-gray-100">{{ invoice.user?.country || 'N/A' }}</p>
                    </div>
                  </div>
                </div>

                <!-- Rental Information (if exists) -->
                <div v-if="invoice.rental" class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                  <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Rental Information</h3>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Product</Label>
                      <p class="text-lg text-gray-900 dark:text-gray-100">{{ invoice.rental.product?.product_name }}</p>
                    </div>
                    <div>
                      <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Rental Period</Label>
                      <p class="text-lg text-gray-900 dark:text-gray-100">
                        {{ formatDate(invoice.rental.rental_start_date) }} - {{ formatDate(invoice.rental.rental_end_date) }}
                      </p>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          <!-- Actions Sidebar -->
          <div class="lg:col-span-1">
            <Card>
              <CardHeader>
                <CardTitle>Actions</CardTitle>
              </CardHeader>
              <CardContent class="space-y-4">
                <Button
                  v-if="invoice.status === 'pending'"
                  @click="updateStatus('paid')"
                  class="w-full"
                  variant="default"
                >
                  <Icon name="check" class="w-4 h-4 mr-2" />
                  Mark as Paid
                </Button>

                <Button
                  v-if="invoice.status === 'pending'"
                  @click="updateStatus('cancelled')"
                  class="w-full"
                  variant="destructive"
                >
                  <Icon name="x" class="w-4 h-4 mr-2" />
                  Mark as Cancelled
                </Button>

                <Button
                  v-if="invoice.status === 'paid'"
                  @click="updateStatus('pending')"
                  class="w-full"
                  variant="outline"
                >
                  <Icon name="refresh-cw" class="w-4 h-4 mr-2" />
                  Mark as Pending
                </Button>

                <Button
                  @click="resendInvoice"
                  class="w-full"
                  variant="outline"
                >
                  <Icon name="mail" class="w-4 h-4 mr-2" />
                  Resend Invoice
                </Button>
              </CardContent>
            </Card>

            <!-- Payment History -->
            <Card class="mt-6">
              <CardHeader>
                <CardTitle>Payment History</CardTitle>
              </CardHeader>
              <CardContent>
                <div class="space-y-4">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Invoice Created</p>
                      <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDateTime(invoice.created_at) }}</p>
                    </div>
                    <Icon name="check-circle" class="w-5 h-5 text-green-500" />
                  </div>

                  <div v-if="invoice.status === 'paid'" class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Payment Completed</p>
                      <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDateTime(invoice.updated_at) }}</p>
                    </div>
                    <Icon name="check-circle" class="w-5 h-5 text-green-500" />
                  </div>

                  <div v-if="invoice.status === 'cancelled'" class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Invoice Cancelled</p>
                      <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDateTime(invoice.updated_at) }}</p>
                    </div>
                    <Icon name="x-circle" class="w-5 h-5 text-red-500" />
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import Icon from '@/components/Icon.vue'

const props = defineProps({
  invoice: Object,
})

const updateStatus = (status) => {
  router.patch(route('invoices.update-status', props.invoice.id), { status })
}

const printInvoice = () => {
  window.print()
}

const downloadPDF = () => {
  // Implement PDF download functionality
  console.log('Download PDF')
}

const resendInvoice = () => {
  // Implement resend invoice functionality
  console.log('Resend invoice')
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('ar-SA', {
    style: 'currency',
    currency: 'SAR',
  }).format(amount)
}

const formatDate = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('ar-SA', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

const formatDateTime = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleString('ar-SA', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
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
</script>
