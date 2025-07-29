<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Users, Mail, Phone, MapPin, Globe } from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    country: string | null;
    address: string | null;
    created_at: string;
}

interface Props {
    users: User[];
}

defineProps<Props>();

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="Users" />
    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-neutral-800 sm:rounded-lg">
                <div class="p-6 text-neutral-900 dark:text-neutral-100">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <Users class="w-8 h-8 text-blue-600" />
                            <h1 class="text-2xl font-semibold">Users Management</h1>
                        </div>
                    </div>

                    <!-- Users Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-neutral-200 dark:border-neutral-700">
                            <thead>
                                <tr class="bg-neutral-50 dark:bg-neutral-700">
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">ID</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">Full Name</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">Email</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">Phone</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">Country</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">Address</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="users.length === 0">
                                    <td colspan="7" class="border border-neutral-200 dark:border-neutral-600 px-4 py-8 text-neutral-500 text-center">
                                        No users found.
                                    </td>
                                </tr>
                                <tr v-for="user in users" :key="user.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-700">
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">{{ user.id }}</td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 font-medium">{{ user.name }}</td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <Mail class="w-4 h-4 text-neutral-400" />
                                            {{ user.email }}
                                        </div>
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <Phone class="w-4 h-4 text-neutral-400" />
                                            {{ user.phone || '—' }}
                                        </div>
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <Globe class="w-4 h-4 text-neutral-400" />
                                            {{ user.country || '—' }}
                                        </div>
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <MapPin class="w-4 h-4 text-neutral-400" />
                                            <span class="max-w-xs truncate">{{ user.address || '—' }}</span>
                                        </div>
                                    </td>
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        {{ new Date(user.created_at).toLocaleString() }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
