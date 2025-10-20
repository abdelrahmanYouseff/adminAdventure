<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { Package, FileText, Box } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'لوحة التحكم',
        href: '/dashboard',
    },
];

const page = usePage();
const totalProducts = page.props.totalProducts as number | undefined;
const totalInvoices = page.props.totalInvoices as number | undefined;
const totalPackages = page.props.totalPackages as number | undefined;
</script>

<template>
    <Head title="عالم المغامرات - لوحة التحكم" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6">
            <!-- Statistics Cards -->
            <div class="grid gap-6 md:grid-cols-3">
                <!-- Total Products -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">إجمالي المنتجات</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ totalProducts || 0 }}</p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/20 rounded-xl flex items-center justify-center">
                            <Package class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        </div>
                    </div>
                </div>

                <!-- Total Invoices -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">إجمالي الفواتير</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ totalInvoices || 0 }}</p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900/20 rounded-xl flex items-center justify-center">
                            <FileText class="w-6 h-6 text-green-600 dark:text-green-400" />
                        </div>
                    </div>
                </div>

                <!-- Total Packages -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">إجمالي الباقات</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ totalPackages || 0 }}</p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 dark:bg-orange-900/20 rounded-xl flex items-center justify-center">
                            <Box class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="flex-1 bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">نظرة عامة على لوحة التحكم</h2>
                <p class="text-gray-600 dark:text-gray-400">مرحباً بك في لوحة تحكم نظام عالم المغامرات.</p>
            </div>
        </div>
    </AppLayout>
</template>
