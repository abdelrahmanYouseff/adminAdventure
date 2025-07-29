<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';

interface Rental {
    id: number;
    user: { id: number; name: string; email: string };
    product: { id: number; product_name: string };
    rental_start_date: string;
    rental_end_date: string;
    total_price: string;
    created_at: string;
}

interface Props {
    rentals: Rental[];
}

defineProps<Props>();

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="Orders" />
    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-neutral-800 sm:rounded-lg">
                <div class="p-6 text-neutral-900 dark:text-neutral-100">
                    <h1 class="text-2xl font-semibold mb-6">Orders</h1>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-neutral-200 dark:border-neutral-700">
                            <thead>
                                <tr class="bg-neutral-50 dark:bg-neutral-700">
                                    <th class="border px-4 py-3 text-left font-medium">ID</th>
                                    <th class="border px-4 py-3 text-left font-medium">Customer</th>
                                    <th class="border px-4 py-3 text-left font-medium">Product</th>
                                    <th class="border px-4 py-3 text-left font-medium">Rental Start</th>
                                    <th class="border px-4 py-3 text-left font-medium">Rental End</th>
                                    <th class="border px-4 py-3 text-left font-medium">Total Price</th>
                                    <th class="border px-4 py-3 text-left font-medium">Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="rentals.length === 0">
                                    <td colspan="7" class="border px-4 py-8 text-neutral-500 text-center">
                                        No orders found.
                                    </td>
                                </tr>
                                <tr v-for="rental in rentals" :key="rental.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-700">
                                    <td class="border px-4 py-3">{{ rental.id }}</td>
                                    <td class="border px-4 py-3 font-medium">{{ rental.user.name }}<br><span class="text-xs text-neutral-500">{{ rental.user.email }}</span></td>
                                    <td class="border px-4 py-3">{{ rental.product.product_name }}</td>
                                    <td class="border px-4 py-3">{{ rental.rental_start_date }}</td>
                                    <td class="border px-4 py-3">{{ rental.rental_end_date }}</td>
                                    <td class="border px-4 py-3">${{ rental.total_price }}</td>
                                    <td class="border px-4 py-3">{{ new Date(rental.created_at).toLocaleString() }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
