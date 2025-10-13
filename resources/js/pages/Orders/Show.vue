<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

import { CheckCircle, Clock, XCircle, RefreshCw, ArrowLeft, Printer, Download } from 'lucide-vue-next';

interface Order {
    id: number;
    order_number: string;
    total_amount: string;
    currency: string;
    status: string;
    payment_method: string;
    payment_id: string;
    notes: string;
    items: Array<{
        name: string;
        amount: string;
        quantity: number;
    }>;
    created_at: string;
    updated_at: string;
    user: {
        id: number;
        name: string;
        full_name: string;
        email: string;
        phone: string;
        country: string;
        address: string;
    };
    invoice: {
        id: number;
        invoice_number: string;
        amount: string;
        status: string;
    };
}

interface Props {
    order: Order;
}

const props = defineProps<Props>();

defineOptions({ layout: AppLayout });

const getStatusColor = (status: string) => {
    switch (status) {
        case 'paid': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'pending': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'cancelled': return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        case 'refunded': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'paid': return CheckCircle;
        case 'pending': return Clock;
        case 'cancelled': return XCircle;
        case 'refunded': return RefreshCw;
        default: return Clock;
    }
};

const updateStatus = (status: string) => {
    router.patch(route('orders.update-status', props.order.id), { status }, {
        onSuccess: () => {
            // Status will be updated via Inertia
        }
    });
};
</script>

<template>
    <Head title="Order Details" />
    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('orders.index')" class="text-neutral-600 hover:text-neutral-900">
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                    <h1 class="text-2xl font-semibold">Order Details</h1>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline">
                        <Download class="h-4 w-4 mr-2" />
                        Download PDF
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Order Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Order Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Order Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-neutral-500">Order Number</label>
                                    <p class="text-lg font-semibold">{{ order.order_number }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-neutral-500">Status</label>
                                    <div class="mt-1">
                                        <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', getStatusColor(order.status)]">
                                            <component :is="getStatusIcon(order.status)" class="w-3 h-3 mr-1" />
                                            {{ order.status }}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-neutral-500">Total Amount</label>
                                    <p class="text-lg font-semibold text-green-600">{{ order.total_amount }} {{ order.currency }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-neutral-500">Payment Method</label>
                                    <p class="text-lg">{{ order.payment_method }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-neutral-500">Payment ID</label>
                                    <p class="text-sm font-mono">{{ order.payment_id }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-neutral-500">Created At</label>
                                    <p class="text-sm">{{ new Date(order.created_at).toLocaleString() }}</p>
                                </div>
                            </div>

                            <div v-if="order.notes" class="mt-4">
                                <label class="text-sm font-medium text-neutral-500">Notes</label>
                                <p class="text-sm mt-1">{{ order.notes }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Order Items -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Order Items</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div v-for="(item, index) in order.items" :key="index" class="flex justify-between items-center p-4 border rounded-lg">
                                    <div>
                                        <h4 class="font-medium">{{ item.name }}</h4>
                                        <p class="text-sm text-neutral-500">Quantity: {{ item.quantity }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold">{{ item.amount }} {{ order.currency }}</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Invoice Information -->
                    <Card v-if="order.invoice">
                        <CardHeader>
                            <CardTitle>Invoice Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-neutral-500">Invoice Number</label>
                                    <Link :href="route('invoices.show', order.invoice.id)" class="text-blue-600 hover:underline">
                                        {{ order.invoice.invoice_number }}
                                    </Link>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-neutral-500">Invoice Amount</label>
                                    <p class="text-lg font-semibold">{{ order.invoice.amount }} {{ order.currency }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-neutral-500">Invoice Status</label>
                                    <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', getStatusColor(order.invoice.status)]">
                                        {{ order.invoice.status }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Customer Information -->
                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Customer Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-sm font-medium text-neutral-500">Name</label>
                                    <p class="font-medium">{{ order.user.name || order.user.full_name }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-neutral-500">Email</label>
                                    <p class="text-sm">{{ order.user.email }}</p>
                                </div>
                                <div v-if="order.user.phone">
                                    <label class="text-sm font-medium text-neutral-500">Phone</label>
                                    <p class="text-sm">{{ order.user.phone }}</p>
                                </div>
                                <div v-if="order.user.country">
                                    <label class="text-sm font-medium text-neutral-500">Country</label>
                                    <p class="text-sm">{{ order.user.country }}</p>
                                </div>
                                <div v-if="order.user.address">
                                    <label class="text-sm font-medium text-neutral-500">Address</label>
                                    <p class="text-sm">{{ order.user.address }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Status Update -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Update Status</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2">
                                <Button
                                    v-if="order.status !== 'paid'"
                                    @click="updateStatus('paid')"
                                    class="w-full justify-start"
                                    variant="outline"
                                >
                                    <CheckCircle class="h-4 w-4 mr-2 text-green-600" />
                                    Mark as Paid
                                </Button>
                                <Button
                                    v-if="order.status !== 'cancelled'"
                                    @click="updateStatus('cancelled')"
                                    class="w-full justify-start"
                                    variant="outline"
                                >
                                    <XCircle class="h-4 w-4 mr-2 text-red-600" />
                                    Mark as Cancelled
                                </Button>
                                <Button
                                    v-if="order.status !== 'refunded'"
                                    @click="updateStatus('refunded')"
                                    class="w-full justify-start"
                                    variant="outline"
                                >
                                    <RefreshCw class="h-4 w-4 mr-2 text-blue-600" />
                                    Mark as Refunded
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </div>
</template>
