<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';

interface User {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    created_at: string;
}

interface Props {
    users: User[];
}

defineProps<Props>();

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="Customers" />
    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-neutral-800 sm:rounded-lg">
                <div class="p-6 text-neutral-900 dark:text-neutral-100">
                    <h1 class="text-2xl font-semibold mb-6">Customers</h1>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-neutral-200 dark:border-neutral-700">
                            <thead>
                                <tr class="bg-neutral-50 dark:bg-neutral-700">
                                    <th class="border px-4 py-3 text-left font-medium">ID</th>
                                    <th class="border px-4 py-3 text-left font-medium">Name</th>
                                    <th class="border px-4 py-3 text-left font-medium">Email</th>
                                    <th class="border px-4 py-3 text-left font-medium">Phone</th>
                                    <th class="border px-4 py-3 text-left font-medium">Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="users.length === 0">
                                    <td colspan="5" class="border px-4 py-8 text-neutral-500 text-center">
                                        No customers found.
                                    </td>
                                </tr>
                                <tr v-for="user in users" :key="user.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-700">
                                    <td class="border px-4 py-3">{{ user.id }}</td>
                                    <td class="border px-4 py-3 font-medium">{{ user.name }}</td>
                                    <td class="border px-4 py-3">{{ user.email }}</td>
                                    <td class="border px-4 py-3">{{ user.phone || '—' }}</td>
                                    <td class="border px-4 py-3">{{ new Date(user.created_at).toLocaleString() }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
