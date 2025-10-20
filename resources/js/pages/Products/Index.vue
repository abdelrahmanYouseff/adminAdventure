<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Plus, Edit, Trash2, Check, X } from 'lucide-vue-next';

interface Category {
    id: number;
    category_name: string;
}

interface Product {
    id: number;
    product_name: string;
    description: string | null;
    price: number;
    image: string | null;
    status: string;
    category_id: number | null;
    category?: Category | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    products: Product[];
    categories: Category[];
}

const props = defineProps<Props>();

defineOptions({
    layout: AppLayout,
});

const deleteForm = useForm({});
const editingProduct = ref<number | null>(null);
const editingField = ref<string | null>(null);

const deleteProduct = (productId: number) => {
    if (confirm('هل أنت متأكد من حذف هذا المنتج؟')) {
        deleteForm.delete(`/products/${productId}`);
    }
};

const startEditing = (productId: number, field: string) => {
    editingProduct.value = productId;
    editingField.value = field;
};

const cancelEditing = () => {
    editingProduct.value = null;
    editingField.value = null;
};

const updateField = (productId: number, field: string, value: any) => {
    const updateForm = useForm({
        [field]: value
    });

    updateForm.patch(`/products/${productId}`, {
        onSuccess: () => {
            editingProduct.value = null;
            editingField.value = null;
        },
        onError: () => {
            // Handle error if needed
        }
    });
};

const isEditing = (productId: number, field: string) => {
    return editingProduct.value === productId && editingField.value === field;
};
</script>

<template>
    <Head title="المنتجات" />

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-neutral-800 sm:rounded-lg">
                <div class="p-6 text-neutral-900 dark:text-neutral-100">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-semibold">المنتجات</h1>
                        <div class="flex space-x-2">
                            <Button variant="outline" as-child>
                                <Link href="/categories">
                                    إدارة الفئات
                                </Link>
                            </Button>
                            <Button as-child>
                                <Link href="/products/create">
                                    <Plus class="w-4 h-4 mr-2" />
                                    إضافة منتج جديد
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <!-- Products Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-neutral-200 dark:border-neutral-700">
                            <thead>
                                <tr class="bg-neutral-50 dark:bg-neutral-700">
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">المعرف</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">الاسم</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">الفئة</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">الوصف</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">السعر</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">الحالة</th>
                                    <th class="border border-neutral-200 dark:border-neutral-600 px-4 py-3 text-left font-medium">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="products.length === 0" class="text-center">
                                    <td colspan="7" class="border border-neutral-200 dark:border-neutral-600 px-4 py-8 text-neutral-500">
                                        لا توجد منتجات. اضغط على "إضافة منتج جديد" للبدء.
                                    </td>
                                </tr>
                                <tr v-for="product in products" :key="product.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-700">
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">{{ product.id }}</td>

                                    <!-- Product Name -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div v-if="!isEditing(product.id, 'product_name')"
                                             @click="startEditing(product.id, 'product_name')"
                                             class="font-medium cursor-pointer hover:bg-neutral-100 dark:hover:bg-neutral-600 px-2 py-1 rounded">
                                            {{ product.product_name }}
                                        </div>
                                        <div v-else class="flex items-center space-x-1">
                                            <input v-model="product.product_name"
                                                   @keyup.enter="updateField(product.id, 'product_name', product.product_name)"
                                                   @blur="updateField(product.id, 'product_name', product.product_name)"
                                                   class="flex-1 px-2 py-1 border rounded text-sm" />
                                            <Button size="sm" variant="outline" @click="updateField(product.id, 'product_name', product.product_name)">
                                                <Check class="w-3 h-3" />
                                            </Button>
                                            <Button size="sm" variant="outline" @click="cancelEditing">
                                                <X class="w-3 h-3" />
                                            </Button>
                                        </div>
                                    </td>

                                    <!-- Category -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div v-if="!isEditing(product.id, 'category_id')"
                                             @click="startEditing(product.id, 'category_id')"
                                             class="cursor-pointer hover:bg-neutral-100 dark:hover:bg-neutral-600 px-2 py-1 rounded">
                                            {{ product.category ? product.category.category_name : '—' }}
                                        </div>
                                        <div v-else class="flex items-center space-x-1">
                                            <select v-model="product.category_id"
                                                    @change="updateField(product.id, 'category_id', product.category_id)"
                                                    class="flex-1 px-2 py-1 border rounded text-sm">
                                                <option v-for="cat in props.categories" :key="cat.id" :value="cat.id">
                                                    {{ cat.category_name }}
                                                </option>
                                            </select>
                                            <Button size="sm" variant="outline" @click="cancelEditing">
                                                <X class="w-3 h-3" />
                                            </Button>
                                        </div>
                                    </td>

                                    <!-- Description -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div v-if="!isEditing(product.id, 'description')"
                                             @click="startEditing(product.id, 'description')"
                                             class="cursor-pointer hover:bg-neutral-100 dark:hover:bg-neutral-600 px-2 py-1 rounded">
                                            {{ product.description ? (product.description.length > 50 ? product.description.substring(0, 50) + '...' : product.description) : 'لا يوجد وصف' }}
                                        </div>
                                        <div v-else class="flex items-center space-x-1">
                                            <textarea v-model="product.description"
                                                      @blur="updateField(product.id, 'description', product.description)"
                                                      class="flex-1 px-2 py-1 border rounded text-sm"
                                                      rows="2"></textarea>
                                            <Button size="sm" variant="outline" @click="updateField(product.id, 'description', product.description)">
                                                <Check class="w-3 h-3" />
                                            </Button>
                                            <Button size="sm" variant="outline" @click="cancelEditing">
                                                <X class="w-3 h-3" />
                                            </Button>
                                        </div>
                                    </td>

                                    <!-- Price -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div v-if="!isEditing(product.id, 'price')"
                                             @click="startEditing(product.id, 'price')"
                                             class="cursor-pointer hover:bg-neutral-100 dark:hover:bg-neutral-600 px-2 py-1 rounded">
                                            {{ product.price }} ريال
                                        </div>
                                        <div v-else class="flex items-center space-x-1">
                                            <input v-model="product.price"
                                                   type="number"
                                                   step="0.01"
                                                   @keyup.enter="updateField(product.id, 'price', product.price)"
                                                   @blur="updateField(product.id, 'price', product.price)"
                                                   class="flex-1 px-2 py-1 border rounded text-sm" />
                                            <Button size="sm" variant="outline" @click="updateField(product.id, 'price', product.price)">
                                                <Check class="w-3 h-3" />
                                            </Button>
                                            <Button size="sm" variant="outline" @click="cancelEditing">
                                                <X class="w-3 h-3" />
                                            </Button>
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div v-if="!isEditing(product.id, 'status')"
                                             @click="startEditing(product.id, 'status')"
                                             class="cursor-pointer hover:bg-neutral-100 dark:hover:bg-neutral-600 px-2 py-1 rounded">
                                            <span :class="product.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'" class="px-2 py-1 rounded-full text-xs font-medium">
                                                {{ product.status === 'active' ? 'نشط' : 'غير نشط' }}
                                            </span>
                                        </div>
                                        <div v-else class="flex items-center space-x-1">
                                            <select v-model="product.status"
                                                    @change="updateField(product.id, 'status', product.status)"
                                                    class="flex-1 px-2 py-1 border rounded text-sm">
                                                <option value="active">نشط</option>
                                                <option value="inactive">غير نشط</option>
                                            </select>
                                            <Button size="sm" variant="outline" @click="cancelEditing">
                                                <X class="w-3 h-3" />
                                            </Button>
                                        </div>
                                    </td>

                                    <td class="border border-neutral-200 dark:border-neutral-600 px-4 py-3">
                                        <div class="flex space-x-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="text-red-600 hover:text-red-700"
                                                :disabled="deleteForm.processing"
                                                @click="deleteProduct(product.id)"
                                            >
                                                <Trash2 class="w-4 h-4" />
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
