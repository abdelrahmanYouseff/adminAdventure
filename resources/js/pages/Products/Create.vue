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

interface Category {
    id: number;
    category_name: string;
}

interface Props {
    categories: Category[];
}

const props = defineProps<Props>();

const form = useForm({
    product_name: '',
    price: null as number | null,
    description: '',
    status: 'active',
    image: null as File | null,
    category_id: null as number | null,
});

const imagePreview = ref<string | null>(null);

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        form.image = target.files[0];
        imagePreview.value = URL.createObjectURL(form.image);
    }
};

const submit = () => {
    form.post(route('products.store'), {
        // You can add onSuccess/onError handlers here
    });
};
</script>

<template>
    <Head title="Add New Product" />
    <div class="py-12">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <form @submit.prevent="submit">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-4">
                        <Button as-child variant="outline" size="sm">
                            <Link :href="route('products')">
                                <ArrowLeft class="w-4 h-4 mr-2" />
                                Back to Products
                            </Link>
                        </Button>
                        <h1 class="text-2xl font-semibold">Add New Product</h1>
                    </div>
                    <Button type="submit" :disabled="form.processing">
                        Save Product
                    </Button>
                </div>

                <div class="grid md:grid-cols-3 gap-6">
                    <!-- Left Column: Product Details -->
                    <div class="md:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Product Details</CardTitle>
                                <CardDescription>Fill in the main details of the product.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-6">
                                <div class="space-y-2">
                                    <Label for="product_name">Product Name</Label>
                                    <Input id="product_name" v-model="form.product_name" required />
                                </div>

                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label for="category">Category</Label>
                                        <select v-model="form.category_id" id="category" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none focus:ring-1 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100" required>
                                            <option value="" disabled>Select a category</option>
                                            <option v-for="cat in props.categories" :key="cat.id" :value="cat.id">
                                                {{ cat.category_name }}
                                            </option>
                                        </select>
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
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">Description</Label>
                                    <textarea v-model="form.description" id="description" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none focus:ring-1 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100" rows="3" placeholder="Provide a detailed description of the product..."></textarea>
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

                    <!-- Right Column: Image & Status -->
                    <div class="md:col-span-1 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Product Image</CardTitle>
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
                                        <img :src="imagePreview" alt="Product Image Preview" class="w-full h-auto object-cover" />
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
