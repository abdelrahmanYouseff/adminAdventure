<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime, formatInteger } from '@/lib/formatNumber';

interface Customer {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    created_at: string;
}

interface Props {
    customers: Customer[];
}

defineProps<Props>();

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="العملاء" />
    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-neutral-800 sm:rounded-lg">
                <div class="p-6 text-neutral-900 dark:text-neutral-100">
                    <h1 class="text-2xl font-semibold mb-6">العملاء</h1>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-neutral-200 dark:border-neutral-700">
                            <thead>
                                <tr class="bg-neutral-50 dark:bg-neutral-700">
                                    <th class="border px-4 py-3 text-right font-medium">المعرف</th>
                                    <th class="border px-4 py-3 text-right font-medium">الاسم</th>
                                    <th class="border px-4 py-3 text-right font-medium">البريد الإلكتروني</th>
                                    <th class="border px-4 py-3 text-right font-medium">الهاتف</th>
                                    <th class="border px-4 py-3 text-right font-medium">تاريخ التسجيل</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="customers.length === 0">
                                    <td colspan="5" class="border px-4 py-8 text-neutral-500 text-center">
                                        لا يوجد عملاء.
                                    </td>
                                </tr>
                                <tr v-for="customer in customers" :key="customer.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-700">
                                    <td class="border px-4 py-3 tabular-nums" dir="ltr">{{ formatInteger(customer.id) }}</td>
                                    <td class="border px-4 py-3 font-medium">{{ customer.name }}</td>
                                    <td class="border px-4 py-3">{{ customer.email }}</td>
                                    <td class="border px-4 py-3">{{ customer.phone || '—' }}</td>
                                    <td class="border px-4 py-3 tabular-nums" dir="ltr">{{ formatDateTime(customer.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
