<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Plus, Edit, Trash2 } from 'lucide-vue-next';

interface Package {
    id: number;
    name: string;
    description: string | null;
    price: number;
    image: string | null;
    status: string;
    created_at: string;
    updated_at: string;
    products: { id: number; product_name: string }[];
}

interface Props {
    packages: Package[];
}

const props = defineProps<Props>();

defineOptions({
    layout: AppLayout,
});

const deleteForm = useForm({});

const deletePackage = (packageId: number) => {
    if (confirm('Are you sure you want to delete this package?')) {
        deleteForm.delete(`/packages/${packageId}`);
    }
};
</script>

<template>
    <Head title="Packages" />

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-neutral-800 sm:rounded-lg">
                <div class="p-6 text-neutral-900 dark:text-neutral-100">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-semibold">Packages</h1>
                        <Button as-child>
                            <Link href="/packages/create">
                                <Plus class="w-4 h-4 mr-2" />
                                Add New Package
                            </Link>
                        </Button>
                    </div>

                    <!-- Packages Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-neutral-200 dark:border-neutral-700">
                            <thead>
                                <tr class="bg-neutral-50 dark:bg-neutral-700">
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">ID</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">Image</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">Name</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">Products</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">Description</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">Price</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">Status</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="packages.length === 0" class="text-center">
                                    <td colspan="7" class="border border-neutral-200 dark:border-neutral-600 px-4 py-8 text-neutral-500">
                                        No packages found. Click "Add New Package" to get started.
                                    </td>
                                </tr>
                                <tr v-for="pkg in packages" :key="pkg.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-700">
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">{{ pkg.id }}</td>

                                    <!-- Image -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <img v-if="pkg.image"
                                             :src="`/storage/${pkg.image}`"
                                             :alt="pkg.name"
                                             class="w-12 h-12 object-cover rounded" />
                                        <div v-else class="w-12 h-12 bg-neutral-200 dark:bg-neutral-600 rounded flex items-center justify-center">
                                            <span class="text-neutral-500 text-xs">No Image</span>
                                        </div>
                                    </td>

                                    <!-- Name -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 font-medium">
                                        {{ pkg.name }}
                                    </td>

                                    <!-- Products -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <ul v-if="pkg.products && pkg.products.length > 0" class="list-disc list-inside">
                                            <li v-for="product in pkg.products" :key="product.id">{{ product.product_name }}</li>
                                        </ul>
                                        <span v-else>—</span>
                                    </td>

                                    <!-- Description -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        {{ pkg.description ? (pkg.description.length > 50 ? pkg.description.substring(0, 50) + '...' : pkg.description) : 'No description' }}
                                    </td>

                                    <!-- Price -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        SAR {{ pkg.price }}
                                    </td>

                                    <!-- Status -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <span :class="[
                                            'px-2 py-1 rounded-full text-xs font-medium',
                                            pkg.status === 'active'
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                        ]">
                                            {{ pkg.status }}
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="flex space-x-2">
                                            <Button as-child size="sm" variant="outline">
                                                <Link :href="`/packages/${pkg.id}/edit`">
                                                    <Edit class="w-3 h-3 mr-1" />
                                                    Edit
                                                </Link>
                                            </Button>
                                            <Button size="sm" variant="outline" @click="deletePackage(pkg.id)" class="text-red-600 hover:text-red-700">
                                                <Trash2 class="w-3 h-3 mr-1" />
                                                Delete
                                            </Button>
                                        </div>
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
