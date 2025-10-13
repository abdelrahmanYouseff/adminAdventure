<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { ArrowLeft, Edit, Download, MoreHorizontal, Send, Check, X, Clock } from 'lucide-vue-next';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';

interface QuotationItem {
    id: number;
    product_name: string;
    description: string;
    quantity: number;
    unit_price: number;
    total_price: number;
}

interface Quotation {
    id: number;
    quotation_number: string;
    customer_name: string;
    customer_email: string;
    customer_phone: string;
    customer_address: string;
    valid_until: string;
    notes: string;
    subtotal: number;
    tax_amount: number;
    total_amount: number;
    status: string;
    created_at: string;
    user: {
        name: string;
    };
    items: QuotationItem[];
}

interface Props {
    quotation: Quotation;
}

const props = defineProps<Props>();

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
            return 'Draft';
        case 'sent':
            return 'Sent';
        case 'accepted':
            return 'Accepted';
        case 'rejected':
            return 'Rejected';
        case 'expired':
            return 'Expired';
        default:
            return status;
    }
};

const updateStatus = (status: string) => {
    router.patch(route('quotations.update-status', props.quotation.id), {
        status: status
    });
};
</script>

<template>
    <Head :title="`Quotation ${quotation.quotation_number}`" />

    <AppSidebarLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('quotations.index')">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Quotations
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight">
                            Quotation {{ quotation.quotation_number }}
                        </h1>
                        <p class="text-muted-foreground">
                            Created on {{ new Date(quotation.created_at).toLocaleDateString() }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <Link :href="route('quotations.pdf', quotation.id)">
                        <Button variant="default">
                            <Download class="mr-2 h-4 w-4" />
                            View PDF
                        </Button>
                    </Link>
                    <Badge :variant="getStatusBadgeVariant(quotation.status)">
                        {{ getStatusText(quotation.status) }}
                    </Badge>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline">
                                <MoreHorizontal class="mr-2 h-4 w-4" />
                                Actions
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem as-child>
                                <Link :href="route('quotations.edit', quotation.id)">
                                    <Edit class="mr-2 h-4 w-4" />
                                    Edit
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link :href="route('quotations.pdf', quotation.id)">
                                    <Download class="mr-2 h-4 w-4" />
                                    Download PDF
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link :href="route('quotations.index')">
                                    <ArrowLeft class="mr-2 h-4 w-4" />
                                    Back to List
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="updateStatus('sent')" v-if="quotation.status === 'draft'">
                                <Send class="mr-2 h-4 w-4" />
                                Mark as Sent
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="updateStatus('accepted')" v-if="quotation.status === 'sent'">
                                <Check class="mr-2 h-4 w-4" />
                                Mark as Accepted
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="updateStatus('rejected')" v-if="quotation.status === 'sent'">
                                <X class="mr-2 h-4 w-4" />
                                Mark as Rejected
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Customer Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Customer Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <h3 class="font-semibold">{{ quotation.customer_name }}</h3>
                            <p class="text-sm text-muted-foreground">{{ quotation.customer_email }}</p>
                            <p class="text-sm text-muted-foreground">{{ quotation.customer_phone }}</p>
                        </div>
                        <div v-if="quotation.customer_address">
                            <h4 class="font-medium text-sm">Address:</h4>
                            <p class="text-sm text-muted-foreground">{{ quotation.customer_address }}</p>
                        </div>
                        <div>
                            <h4 class="font-medium text-sm">Valid Until:</h4>
                            <p class="text-sm text-muted-foreground">{{ new Date(quotation.valid_until).toLocaleDateString() }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Quotation Details -->
                <Card>
                    <CardHeader>
                        <CardTitle>Quotation Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-sm">Quotation Number:</span>
                            <span class="font-medium">{{ quotation.quotation_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm">Status:</span>
                            <Badge :variant="getStatusBadgeVariant(quotation.status)">
                                {{ getStatusText(quotation.status) }}
                            </Badge>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm">Created By:</span>
                            <span class="font-medium">{{ quotation.user?.name || 'Unknown' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm">Created Date:</span>
                            <span class="font-medium">{{ new Date(quotation.created_at).toLocaleDateString() }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex justify-between">
                            <span>Subtotal:</span>
                            <span>${{ Number(quotation.subtotal || 0).toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Tax (15%):</span>
                            <span>${{ Number(quotation.tax_amount || 0).toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between text-lg font-bold border-t pt-2">
                            <span>Total:</span>
                                                            <span>${{ Number(quotation.total_amount || 0).toFixed(2) }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Items Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Items</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead class="text-right">Quantity</TableHead>
                                <TableHead class="text-right">Unit Price</TableHead>
                                <TableHead class="text-right">Total</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="item in quotation.items" :key="item.id">
                                <TableCell class="font-medium">
                                    {{ item.product_name }}
                                </TableCell>
                                <TableCell>
                                    {{ item.description }}
                                </TableCell>
                                <TableCell class="text-right">
                                    {{ item.quantity }}
                                </TableCell>
                                <TableCell class="text-right">
                                    ${{ item.unit_price.toFixed(2) }}
                                </TableCell>
                                <TableCell class="text-right font-medium">
                                    ${{ Number(item.total_price || 0).toFixed(2) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Notes -->
            <Card v-if="quotation.notes">
                <CardHeader>
                    <CardTitle>Notes</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">{{ quotation.notes }}</p>
                </CardContent>
            </Card>
        </div>
    </AppSidebarLayout>
</template>
