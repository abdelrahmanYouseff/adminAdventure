<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';

interface Category {
    id: number;
    category_name: string;
    image?: string;
}

interface Props {
    category: Category;
}

const props = defineProps<Props>();

defineOptions({
    layout: AppLayout,
});

const form = useForm({
    category_name: props.category.category_name,
    image: null,
});

const handleImageChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.image = file;
    }
};
</script>

<template>
    <Head title="Edit Category" />
    <div class="py-12">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-neutral-800 sm:rounded-lg">
                <div class="p-6 text-neutral-900 dark:text-neutral-100">
                    <div class="flex items-center mb-6">
                        <Button as-child variant="outline" size="sm" class="mr-2">
                            <Link href="/categories">
                                <ArrowLeft class="w-4 h-4 mr-1" />
                                Back
                            </Link>
                        </Button>
                        <h1 class="text-2xl font-semibold">Edit Category</h1>
                    </div>
                    <form @submit.prevent="form.post(`/categories/${props.category.id}`, { _method: 'put' })" class="space-y-6" enctype="multipart/form-data">
                        <div>
                            <label class="block mb-1 font-medium">Category Name</label>
                            <input v-model="form.category_name" type="text" class="w-full rounded border px-3 py-2" required />
                        </div>
                        
                        <!-- Image Upload Section -->
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6">
                            <label class="block mb-2 font-medium text-lg">
                                📸 صورة القسم (اختياري)
                            </label>
                            
                            <!-- Current Image Display -->
                            <div v-if="props.category.image" class="mb-4">
                                <p class="text-sm text-blue-600 dark:text-blue-400 mb-2">الصورة الحالية:</p>
                                <img 
                                    :src="`/storage/${props.category.image}`" 
                                    :alt="props.category.category_name"
                                    class="w-24 h-24 object-cover rounded-lg border border-gray-300"
                                />
                            </div>
                            
                            <input 
                                type="file" 
                                accept="image/*"
                                @change="handleImageChange"
                                class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                            />
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                اختر صورة جديدة للقسم (JPEG, PNG, JPG, GIF - الحد الأقصى 2MB)
                            </p>
                        </div>
                        
                        <div>
                            <Button type="submit" :disabled="form.processing">
                                Update Category
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
