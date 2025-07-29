<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';

interface Category {
    id: number;
    category_name: string;
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
});
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
                    <form @submit.prevent="form.put(`/categories/${props.category.id}`)" class="space-y-6">
                        <div>
                            <label class="block mb-1 font-medium">Category Name</label>
                            <input v-model="form.category_name" type="text" class="w-full rounded border px-3 py-2" required />
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
