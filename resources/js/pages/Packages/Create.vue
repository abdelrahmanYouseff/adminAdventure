<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ArrowLeft, UploadCloud } from 'lucide-vue-next';
import { onClickOutside } from '@vueuse/core';

defineOptions({
    layout: AppLayout,
});

interface Product {
    id: number;
    product_name: string;
}

interface Props {
    products: Product[];
}

const props = defineProps<Props>();

const form = useForm({
    name: '',
    price: null as number | null,
    description: '',
    status: 'active',
    image: null as File | null,
    product_ids: [] as number[],
});

const imagePreview = ref<string | null>(null);

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        form.image = target.files[0];
        imagePreview.value = URL.createObjectURL(form.image);
    }
};

const dropdownOpen = ref(false);
const dropdownRef = ref(null);
onClickOutside(dropdownRef, () => { dropdownOpen.value = false; });

function toggleDropdown() {
    dropdownOpen.value = !dropdownOpen.value;
}

function selectedProductNames() {
    return props.products
        .filter(p => form.product_ids.includes(p.id))
        .map(p => p.product_name)
        .join(', ');
}

const submit = () => {
    form.post(route('packages.store'), {
        // You can add onSuccess/onError handlers here
    });
};
</script>

<template>
    <Head title="Add New Package" />
    <div class="py-12">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <form @submit.prevent="submit">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-4">
                        <Button as-child variant="outline" size="sm">
                            <Link :href="route('packages.index')">
                                <ArrowLeft class="w-4 h-4 mr-2" />
                                Back to Packages
                            </Link>
                        </Button>
                        <h1 class="text-2xl font-semibold">Add New Package</h1>
                    </div>
                    <Button type="submit" :disabled="form.processing">
                        Save Package
                    </Button>
                </div>

                <div class="grid md:grid-cols-3 gap-6">
                    <!-- Left Column: Package Details -->
                    <div class="md:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Package Details</CardTitle>
                                <CardDescription>Fill in the main details of the package.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-6">
                                <div class="space-y-2">
                                    <Label for="name">Package Name</Label>
                                    <Input id="name" v-model="form.name" required />
                                </div>

                                <div class="space-y-2">
                                    <Label for="price">Price</Label>
                                    <div class="relative">
                                        <Input id="price" v-model.number="form.price" type="number" step="0.01" required placeholder="0.00" class="pl-12" />
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <span class="text-neutral-500 sm:text-sm">SAR</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label>Products</Label>
                                    <div class="relative" ref="dropdownRef">
                                        <button type="button" @click="toggleDropdown" class="w-full flex justify-between items-center border rounded-md px-3 py-2 bg-white dark:bg-neutral-800 border-neutral-300 dark:border-neutral-600 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                            <span class="truncate text-left">{{ selectedProductNames() || 'Select products...' }}</span>
                                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div v-if="dropdownOpen" class="absolute z-10 mt-1 w-full max-h-48 overflow-y-auto bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-lg">
                                            <label v-for="product in props.products" :key="product.id" class="flex items-center space-x-2 px-3 py-2 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    :value="product.id"
                                                    v-model="form.product_ids"
                                                    class="accent-primary h-4 w-4 rounded border-neutral-300 dark:border-neutral-600"
                                                />
                                                <span class="text-sm">{{ product.product_name }}</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">Description</Label>
                                    <textarea v-model="form.description" id="description" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none focus:ring-1 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100" rows="3" placeholder="Provide a detailed description of the package..."></textarea>
                                </div>

                                 <div class="space-y-2">
                                    <Label for="status">Status</Label>
                                    <select v-model="form.status" id="status" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none focus:ring-1 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Right Column: Image -->
                    <div class="md:col-span-1 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Package Image</CardTitle>
                            </CardHeader>
                            <CardContent class="flex flex-col items-center justify-center">
                                <label for="image-upload" class="w-full cursor-pointer">
                                    <div v-if="!imagePreview" class="border-2 border-dashed border-neutral-300 dark:border-neutral-600 rounded-lg p-8 text-center">
                                        <UploadCloud class="mx-auto h-12 w-12 text-neutral-400" />
                                        <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                                            <span class="font-semibold">Click to upload</span> or drag and drop
                                        </p>
                                        <p class="text-xs text-neutral-500">PNG, JPG, GIF up to 2MB</p>
                                    </div>
                                    <div v-else class="rounded-lg overflow-hidden">
                                        <img :src="imagePreview" alt="Package Image Preview" class="w-full h-auto object-cover" />
                                    </div>
                                </label>
                                <input id="image-upload" type="file" @change="handleFileChange" accept="image/*" class="hidden" />
                                <Button v-if="imagePreview" @click="imagePreview = null; form.image = null" variant="link" class="mt-2">
                                    Remove Image
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
