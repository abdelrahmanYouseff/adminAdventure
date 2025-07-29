<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ArrowLeft, UploadCloud } from 'lucide-vue-next';

defineOptions({
    layout: AppLayout,
});

interface Product {
    id: number;
    product_name: string;
}

interface Package {
    id: number;
    name: string;
    description: string | null;
    price: number;
    image: string | null;
    status: string;
    products: Product[];
}

interface Props {
    package: Package;
    products: Product[];
}

const props = defineProps<Props>();

const form = useForm({
    _method: 'PUT',
    name: props.package.name,
    price: props.package.price,
    description: props.package.description || '',
    status: props.package.status,
    image: null as File | null,
    product_ids: props.package.products.map(p => p.id),
});

const imagePreview = ref<string | null>(props.package.image ? `/storage/${props.package.image}` : null);

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        form.image = target.files[0];
        imagePreview.value = URL.createObjectURL(form.image);
    }
};

const submit = () => {
    form.post(route('packages.update', props.package.id), {
        // Using `post` with `_method: 'PUT'` to handle file uploads
    });
};
</script>

<template>
    <Head title="Edit Package" />
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
                        <h1 class="text-2xl font-semibold">Edit Package</h1>
                    </div>
                    <Button type="submit" :disabled="form.processing">
                        Update Package
                    </Button>
                </div>

                <div class="grid md:grid-cols-3 gap-6">
                    <!-- Left Column: Package Details -->
                    <div class="md:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Package Details</CardTitle>
                                <CardDescription>Update the main details of the package.</CardDescription>
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
                                    <Label for="products">Products</Label>
                                    <select
                                        multiple
                                        v-model="form.product_ids"
                                        id="products"
                                        class="h-48 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none focus:ring-1 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100"
                                        required
                                    >
                                        <option v-for="product in props.products" :key="product.id" :value="product.id">
                                            {{ product.product_name }}
                                        </option>
                                    </select>
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
