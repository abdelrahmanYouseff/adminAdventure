<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu'
import Icon from '@/components/Icon.vue'

const props = defineProps({
  orders: Object,
  statistics: Object,
  filters: Object,
})

const showDetailsDialog = ref(false)
const selectedOrder = ref(null)

const goToPage = (page) => {
  const params = new URLSearchParams(window.location.search)
  params.set('page', page)
  router.get(route('orders.index') + '?' + params.toString())
}

const viewOrderDetails = (order) => {
  selectedOrder.value = order
  showDetailsDialog.value = true
}

const updateStatus = (order, status) => {
  if (confirm('Are you sure you want to mark this order as ' + status + '?')) {
    router.patch(route('orders.update-status', order.id), { status }, {
      preserveScroll: true,
      onSuccess: () => {
        if (showDetailsDialog.value) {
          showDetailsDialog.value = false
        }
      }
    })
  }
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'SAR',
  }).format(amount || 0)
}

const formatCurrencyWithSymbol = (amount, currency = 'SAR') => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'SAR',
  }).format(amount || 0)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const formatTime = (date) => {
  return new Date(date).toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

const formatStatus = (status) => {
  const statusMap = {
    pending: 'Pending',
    processing: 'Processing',
    paid: 'Paid',
    cancelled: 'Cancelled',
    refunded: 'Refunded',
  }
  return statusMap[status] || status
}

const formatPaymentMethod = (method) => {
  const methodMap = {
    credit_card: 'Credit Card',
    cash: 'Cash',
    bank_transfer: 'Bank Transfer',
    paypal: 'PayPal',
    noon: 'Noon',
  }
  return methodMap[method] || method || 'N/A'
}

const getStatusBadgeClass = (status) => {
  const classes = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    refunded: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
  }
  return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
}

const getStatusDotClass = (status) => {
  const classes = {
    pending: 'bg-yellow-500',
    processing: 'bg-blue-500',
    paid: 'bg-green-500',
    cancelled: 'bg-red-500',
    refunded: 'bg-orange-500',
  }
  return classes[status] || 'bg-gray-500'
}

const getInitials = (name) => {
  if (!name) return '?'
  return name.split(' ').map(word => word.charAt(0)).join('').toUpperCase().slice(0, 2)
}
</script>

<template>
  <AppLayout title="Orders">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Orders Management
        </h2>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Orders Grid -->
        <div class="mb-6">
          <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-6">Orders</h2>
          
          <!-- Empty State -->
          <div v-if="orders.data.length === 0" class="text-center py-12">
            <Icon name="shopping-bag" class="w-16 h-16 mx-auto text-gray-400 mb-4" />
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No orders found</h3>
            <p class="text-gray-500 dark:text-gray-400">
              Orders will appear here once customers place them
            </p>
          </div>

          <!-- Orders Grid -->
          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <Card 
              v-for="order in orders.data"
              :key="order.id"
              class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-shadow cursor-pointer"
              @click="viewOrderDetails(order)"
            >
              <CardContent class="p-6">
                <!-- Header -->
                <div class="flex items-center justify-between mb-4">
                  <div class="flex items-center">
                    <Avatar class="w-10 h-10 mr-3">
                      <AvatarFallback>
                        {{ getInitials(order.customer_name || order.user?.full_name || order.user?.name) }}
                      </AvatarFallback>
                    </Avatar>
                    <div>
                      <div class="font-medium text-gray-900 dark:text-gray-100">
                        {{ order.customer_name || order.user?.full_name || order.user?.name || 'N/A' }}
                      </div>
                      <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ order.order_number }}
                      </div>
                    </div>
                  </div>
                  <span
                    :class="getStatusBadgeClass(order.status)"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  >
                    <span 
                      class="w-2 h-2 rounded-full mr-1.5"
                      :class="getStatusDotClass(order.status)"
                    ></span>
                    {{ formatStatus(order.status) }}
                  </span>
                </div>

                <!-- Order Details -->
                <div class="space-y-3">
                  <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Amount</span>
                    <span class="font-semibold text-gray-900 dark:text-gray-100">
                      {{ formatCurrencyWithSymbol(order.total_amount, order.currency) }}
                    </span>
                  </div>
                  
                  <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Payment</span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                      {{ formatPaymentMethod(order.payment_method) }}
                    </span>
                  </div>

                  <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Date</span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                      {{ formatDate(order.created_at) }}
                    </span>
                  </div>

                  <div v-if="order.payment_id" class="flex justify-between items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Payment ID</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                      {{ order.payment_id.slice(-8) }}
                    </span>
                  </div>
                </div>

                <!-- Actions -->
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                      {{ formatTime(order.created_at) }}
                    </span>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild @click.stop>
                        <Button variant="ghost" size="sm">
                          <Icon name="more-horizontal" class="w-4 h-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem @click.stop="viewOrderDetails(order)">
                          <Icon name="eye" class="w-4 h-4 mr-2" />
                          View Details
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem 
                          @click.stop="updateStatus(order, 'processing')" 
                          v-if="order.status === 'pending'"
                        >
                          <Icon name="clock" class="w-4 h-4 mr-2" />
                          Mark as Processing
                        </DropdownMenuItem>
                        <DropdownMenuItem 
                          @click.stop="updateStatus(order, 'paid')" 
                          v-if="order.status !== 'paid' && order.status !== 'refunded'"
                        >
                          <Icon name="check" class="w-4 h-4 mr-2" />
                          Mark as Paid
                        </DropdownMenuItem>
                        <DropdownMenuItem 
                          @click.stop="updateStatus(order, 'cancelled')" 
                          v-if="order.status !== 'cancelled' && order.status !== 'paid'"
                        >
                          <Icon name="x" class="w-4 h-4 mr-2" />
                          Cancel Order
                        </DropdownMenuItem>
                        <DropdownMenuItem 
                          @click.stop="updateStatus(order, 'refunded')" 
                          v-if="order.status === 'paid'"
                        >
                          <Icon name="rotate-ccw" class="w-4 h-4 mr-2" />
                          Refund Order
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="orders.data.length > 0" class="flex items-center justify-between mt-6">
          <div class="text-sm text-gray-700 dark:text-gray-300">
            Showing {{ orders.from }} to {{ orders.to }} of {{ orders.total }} results
          </div>
          <div class="flex items-center space-x-2">
            <Button
              v-if="orders.prev_page_url"
              @click="goToPage(orders.current_page - 1)"
              variant="outline"
              size="sm"
            >
              <Icon name="chevron-left" class="w-4 h-4 mr-1" />
              Previous
            </Button>
            <div class="text-sm text-gray-700 dark:text-gray-300">
              Page {{ orders.current_page }} of {{ orders.last_page }}
            </div>
            <Button
              v-if="orders.next_page_url"
              @click="goToPage(orders.current_page + 1)"
              variant="outline"
              size="sm"
            >
              Next
              <Icon name="chevron-right" class="w-4 h-4 ml-1" />
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Order Details Dialog -->
    <Dialog v-model:open="showDetailsDialog">
      <DialogContent class="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Order Details</DialogTitle>
        </DialogHeader>
        <div v-if="selectedOrder" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <Label class="text-gray-500">Order Number</Label>
              <p class="font-medium">{{ selectedOrder.order_number }}</p>
            </div>
            <div>
              <Label class="text-gray-500">Status</Label>
              <p>
                <span
                  :class="getStatusBadgeClass(selectedOrder.status)"
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                >
                  {{ formatStatus(selectedOrder.status) }}
                </span>
              </p>
            </div>
            <div>
              <Label class="text-gray-500">Customer</Label>
              <p class="font-medium">{{ selectedOrder.customer_name || selectedOrder.user?.full_name || 'N/A' }}</p>
            </div>
            <div>
              <Label class="text-gray-500">Total Amount</Label>
              <p class="font-medium">{{ formatCurrencyWithSymbol(selectedOrder.total_amount, selectedOrder.currency) }}</p>
            </div>
            <div>
              <Label class="text-gray-500">Payment Method</Label>
              <p class="font-medium">{{ formatPaymentMethod(selectedOrder.payment_method) }}</p>
            </div>
            <div v-if="selectedOrder.payment_id">
              <Label class="text-gray-500">Payment ID</Label>
              <p class="font-medium text-xs">{{ selectedOrder.payment_id }}</p>
            </div>
            <div>
              <Label class="text-gray-500">Created At</Label>
              <p class="font-medium">{{ formatDate(selectedOrder.created_at) }} {{ formatTime(selectedOrder.created_at) }}</p>
            </div>
            <div v-if="selectedOrder.invoice">
              <Label class="text-gray-500">Invoice</Label>
              <p class="font-medium">{{ selectedOrder.invoice.invoice_number }}</p>
            </div>
          </div>

          <!-- Order Items -->
          <div v-if="selectedOrder.items && selectedOrder.items.length > 0">
            <Label class="text-gray-500 mb-2 block">Order Items</Label>
            <div class="border rounded-lg overflow-hidden">
              <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                  <tr>
                    <th class="text-left py-2 px-3">Item</th>
                    <th class="text-right py-2 px-3">Qty</th>
                    <th class="text-right py-2 px-3">Price</th>
                    <th class="text-right py-2 px-3">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in selectedOrder.items" :key="index" class="border-t">
                    <td class="py-2 px-3">{{ item.name }}</td>
                    <td class="text-right py-2 px-3">{{ item.quantity }}</td>
                    <td class="text-right py-2 px-3">{{ formatCurrencyWithSymbol(item.price, selectedOrder.currency) }}</td>
                    <td class="text-right py-2 px-3">{{ formatCurrencyWithSymbol(item.quantity * item.price, selectedOrder.currency) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Notes -->
          <div v-if="selectedOrder.notes">
            <Label class="text-gray-500">Notes</Label>
            <p class="text-sm bg-gray-50 dark:bg-gray-800 p-3 rounded">{{ selectedOrder.notes }}</p>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
