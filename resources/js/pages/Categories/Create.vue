<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';

defineOptions({
    layout: AppLayout,
});

const form = useForm({
    category_name: '',
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
    <Head title="Add New Category" />
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
                        <h1 class="text-2xl font-semibold">Add New Category</h1>
                    </div>
                    <form @submit.prevent="form.post('/categories')" class="space-y-6" enctype="multipart/form-data">
                        <div>
                            <label class="block mb-1 font-medium">Category Name</label>
                            <input v-model="form.category_name" type="text" class="w-full rounded border px-3 py-2" required />
                        </div>
                        
                        <!-- Image Upload Section -->
                        <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-6 space-y-4">
                            <div class="text-center">
                                <h3 class="text-xl font-bold text-blue-800 mb-2">
                                    📸 إضافة صورة للقسم
                                </h3>
                                <p class="text-blue-600">اختر صورة لتمثيل هذا القسم (اختياري)</p>
                            </div>
                            
                            <div class="bg-white rounded-lg p-4 border border-blue-200">
                                <label class="block mb-3 font-medium text-gray-700">
                                    اختر ملف الصورة:
                                </label>
                                <input 
                                    type="file" 
                                    accept="image/*"
                                    @change="handleImageChange"
                                    class="w-full p-4 border-2 border-dashed border-blue-300 rounded-lg hover:border-blue-400 focus:border-blue-500 focus:outline-none bg-blue-50" 
                                    placeholder="اختر صورة..."
                                />
                                <div class="mt-3 text-center">
                                    <p class="text-sm text-gray-600">
                                        🖼️ الأنواع المدعومة: JPEG, PNG, JPG, GIF
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        الحد الأقصى لحجم الملف: 2 ميجابايت
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <Button type="submit" :disabled="form.processing">
                                Add Category
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
