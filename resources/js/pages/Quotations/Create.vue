<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Plus, Trash2, Calculator } from 'lucide-vue-next';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { ref, computed, watch } from 'vue';

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

const form = useForm({
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    customer_address: '',
    valid_until: '',
    notes: '',
    items: [] as QuotationItem[],
});

const selectedProductId = ref<string | null>(null);
const selectedQuantity = ref(1);
const selectedUnitPrice = ref(0);

const subtotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (parseFloat(item.total_price) || 0), 0);
});

const taxAmount = computed(() => {
    return subtotal.value * 0.15; // 15% tax
});

const totalAmount = computed(() => {
    return subtotal.value + taxAmount.value;
});

const addItem = () => {
    if (!selectedProductId.value) return;

    const product = props.products.find(p => p.id === parseInt(selectedProductId.value!));
    if (!product) return;

    const newItem: QuotationItem = {
        product_id: parseInt(selectedProductId.value),
        product_name: product.product_name,
        description: product.description,
        quantity: selectedQuantity.value,
        unit_price: selectedUnitPrice.value,
        total_price: selectedQuantity.value * selectedUnitPrice.value,
    };

    form.items.push(newItem);

    // Reset form
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

// Auto-fill product details when product is selected
watch(selectedProductId, (newValue) => {
    if (newValue) {
        const product = props.products.find(p => p.id === parseInt(newValue));
        if (product) {
            selectedUnitPrice.value = product.price;
        }
    }
});
</script>

<template>
    <Head title="Create Quotation" />

    <AppSidebarLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Create Quotation</h1>
                    <p class="text-muted-foreground">
                        Create a new quotation for your customer
                    </p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Customer Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Customer Information</CardTitle>
                        <CardDescription>
                            Enter the customer details for this quotation
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="customer_name">Customer Name *</Label>
                                <Input
                                    id="customer_name"
                                    v-model="form.customer_name"
                                    placeholder="Enter customer name"
                                    required
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="customer_email">Email</Label>
                                <Input
                                    id="customer_email"
                                    v-model="form.customer_email"
                                    type="email"
                                    placeholder="Enter customer email"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="customer_phone">Phone</Label>
                                <Input
                                    id="customer_phone"
                                    v-model="form.customer_phone"
                                    placeholder="Enter customer phone"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="valid_until">Valid Until *</Label>
                                <Input
                                    id="valid_until"
                                    v-model="form.valid_until"
                                    type="date"
                                    required
                                />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label for="customer_address">Address</Label>
                            <Textarea
                                id="customer_address"
                                v-model="form.customer_address"
                                placeholder="Enter customer address"
                                rows="3"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="notes">Notes</Label>
                            <Textarea
                                id="notes"
                                v-model="form.notes"
                                placeholder="Enter any additional notes"
                                rows="3"
                            />
                        </div>
                    </CardContent>
                </Card>

                <!-- Products Selection -->
                <Card>
                    <CardHeader>
                        <CardTitle>Products</CardTitle>
                        <CardDescription>
                            Select products and quantities for this quotation
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="space-y-2">
                                <Label for="product">Product</Label>
                                <select
                                    v-model="selectedProductId"
                                    class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none focus:ring-1 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100"
                                >
                                    <option value="">Select a product</option>
                                    <option
                                        v-for="product in products"
                                        :key="product.id"
                                        :value="product.id"
                                    >
                                        {{ product.product_name }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label for="quantity">Quantity</Label>
                                <Input
                                    id="quantity"
                                    v-model="selectedQuantity"
                                    type="number"
                                    min="1"
                                    placeholder="Qty"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="unit_price">Unit Price</Label>
                                <Input
                                    id="unit_price"
                                    v-model="selectedUnitPrice"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Price"
                                />
                            </div>
                            <div class="flex items-end">
                                <Button type="button" @click="addItem" :disabled="!selectedProductId">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Add Item
                                </Button>
                            </div>
                        </div>

                        <!-- Selected Items Table -->
                        <div v-if="form.items.length > 0" class="space-y-4">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead>Description</TableHead>
                                        <TableHead>Quantity</TableHead>
                                        <TableHead>Unit Price</TableHead>
                                        <TableHead>Total</TableHead>
                                        <TableHead class="w-[50px]">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="(item, index) in form.items" :key="index">
                                        <TableCell class="font-medium">
                                            {{ item.product_name }}
                                        </TableCell>
                                        <TableCell>
                                            {{ item.description }}
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                v-model="item.quantity"
                                                type="number"
                                                min="1"
                                                class="w-20"
                                                @input="updateItemPrice(index)"
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                v-model="item.unit_price"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="w-24"
                                                @input="updateItemPrice(index)"
                                            />
                                        </TableCell>
                                        <TableCell class="font-medium">
                                            ${{ Number(item.total_price || 0).toFixed(2) }}
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                @click="removeItem(index)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="flex justify-between">
                                <span>Subtotal:</span>
                                <span>${{ Number(subtotal || 0).toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Tax (15%):</span>
                                <span>${{ Number(taxAmount || 0).toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between text-lg font-bold">
                                <span>Total:</span>
                                <span>${{ Number(totalAmount || 0).toFixed(2) }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Submit Button -->
                <div class="flex justify-end space-x-4">
                    <Button type="button" variant="outline" @click="$inertia.visit(route('quotations.index'))">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing || form.items.length === 0">
                        <Calculator class="mr-2 h-4 w-4" />
                        Create Quotation
                    </Button>
                </div>
            </form>
        </div>
    </AppSidebarLayout>
</template>
