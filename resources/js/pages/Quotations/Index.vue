<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Plus, MoreHorizontal, Eye, Edit, Download, Trash2 } from 'lucide-vue-next';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';

interface Quotation {
    id: number;
    quotation_number: string;
    customer_name: string;
    customer_email: string;
    total_amount: number;
    status: string;
    valid_until: string;
    created_at: string;
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

const deleteQuotation = (id: number) => {
    if (confirm('Are you sure you want to delete this quotation?')) {
        router.delete(route('quotations.destroy', id));
    }
};
</script>

<template>
    <Head title="Quotations" />

    <AppSidebarLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Quotations</h1>
                    <p class="text-muted-foreground">
                        Manage and track your quotation requests
                    </p>
                </div>
                <Link :href="route('quotations.create')">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        New Quotation
                    </Button>
                </Link>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Quotations</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ props.quotations.total }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Draft</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ props.quotations.data.filter(q => q.status === 'draft').length }}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Sent</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ props.quotations.data.filter(q => q.status === 'sent').length }}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Accepted</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ props.quotations.data.filter(q => q.status === 'accepted').length }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Quotations Table -->
            <Card>
                <CardHeader>
                    <CardTitle>All Quotations</CardTitle>
                    <CardDescription>
                        A list of all quotations in your system
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Quotation #</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Amount</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Valid Until</TableHead>
                                <TableHead>Created By</TableHead>
                                <TableHead>Created At</TableHead>
                                <TableHead class="w-[50px]">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="quotation in props.quotations.data" :key="quotation.id">
                                <TableCell class="font-medium">
                                    {{ quotation.quotation_number }}
                                </TableCell>
                                <TableCell>
                                    <div>
                                        <div class="font-medium">{{ quotation.customer_name }}</div>
                                        <div class="text-sm text-muted-foreground">{{ quotation.customer_email }}</div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    ${{ Number(quotation.total_amount || 0).toFixed(2) }}
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="getStatusBadgeVariant(quotation.status)">
                                        {{ getStatusText(quotation.status) }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    {{ new Date(quotation.valid_until).toLocaleDateString() }}
                                </TableCell>
                                <TableCell>
                                    {{ quotation.user?.name || 'Unknown' }}
                                </TableCell>
                                <TableCell>
                                    {{ new Date(quotation.created_at).toLocaleDateString() }}
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
                                                <Link :href="route('quotations.pdf', quotation.id)">
                                                    <Eye class="mr-2 h-4 w-4" />
                                                    View PDF
                                                </Link>
                                            </DropdownMenuItem>
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
                                            <DropdownMenuItem @click="deleteQuotation(quotation.id)" class="text-red-600">
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppSidebarLayout>
</template>
