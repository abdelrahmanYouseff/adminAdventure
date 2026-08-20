<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, LogOut, X } from 'lucide-vue-next';

interface OrderGallery {
    order_number: string;
    photos: string[];
}

interface Props {
    orders: {
        data: OrderGallery[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
}

const props = defineProps<Props>();

const lightboxUrl = ref<string | null>(null);

function openPhoto(url: string) {
    lightboxUrl.value = url;
}

function closeLightbox() {
    lightboxUrl.value = null;
}

function logout() {
    router.post('/social-media/logout');
}

function goToPage(page: number) {
    if (page < 1 || page > props.orders.last_page) {
        return;
    }
    router.get('/social-media', { page }, { preserveScroll: true });
}
</script>

<template>
    <Head title="سوشيال ميديا" />

    <div class="min-h-svh bg-neutral-950 text-white">
        <header class="sticky top-0 z-20 border-b border-neutral-800 bg-neutral-950/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <div>
                    <h1 class="text-lg font-bold sm:text-xl">سوشيال ميديا</h1>
                    <p class="text-xs text-neutral-400 sm:text-sm">صور الطلبات</p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-neutral-700 px-3 py-2 text-sm text-neutral-300 transition hover:bg-neutral-900"
                    @click="logout"
                >
                    <LogOut class="size-4" />
                    خروج
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
            <div v-if="orders.data.length === 0" class="rounded-2xl border border-dashed border-neutral-800 py-20 text-center text-neutral-500">
                لا توجد طلبات بصور حالياً.
            </div>

            <div v-else class="space-y-8">
                <section
                    v-for="order in orders.data"
                    :key="order.order_number"
                    class="overflow-hidden rounded-2xl border border-neutral-800 bg-neutral-900"
                >
                    <div class="border-b border-neutral-800 px-4 py-3 sm:px-5">
                        <p class="font-bold tabular-nums tracking-wide" dir="ltr">
                            {{ order.order_number }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-2 p-3 sm:grid-cols-3 sm:gap-3 sm:p-4 lg:grid-cols-4">
                        <button
                            v-for="(photo, index) in order.photos"
                            :key="`${order.order_number}-${index}`"
                            type="button"
                            class="group relative aspect-square overflow-hidden rounded-xl bg-neutral-800"
                            @click="openPhoto(photo)"
                        >
                            <img
                                :src="photo"
                                :alt="`${order.order_number} ${index + 1}`"
                                class="size-full object-cover transition duration-300 group-hover:scale-105"
                                loading="lazy"
                            />
                        </button>
                    </div>
                </section>
            </div>

            <div
                v-if="orders.last_page > 1"
                class="mt-8 flex items-center justify-center gap-3"
            >
                <button
                    type="button"
                    class="inline-flex size-9 items-center justify-center rounded-lg border border-neutral-700 disabled:opacity-40"
                    :disabled="orders.current_page <= 1"
                    @click="goToPage(orders.current_page - 1)"
                >
                    <ChevronRight class="size-4" />
                </button>
                <span class="text-sm tabular-nums text-neutral-400">
                    {{ orders.current_page }} / {{ orders.last_page }}
                </span>
                <button
                    type="button"
                    class="inline-flex size-9 items-center justify-center rounded-lg border border-neutral-700 disabled:opacity-40"
                    :disabled="orders.current_page >= orders.last_page"
                    @click="goToPage(orders.current_page + 1)"
                >
                    <ChevronLeft class="size-4" />
                </button>
            </div>
        </main>

        <div
            v-if="lightboxUrl"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
            @click.self="closeLightbox"
        >
            <button
                type="button"
                class="absolute end-4 top-4 inline-flex size-10 items-center justify-center rounded-full bg-neutral-900/80 text-white"
                @click="closeLightbox"
            >
                <X class="size-5" />
            </button>
            <img
                :src="lightboxUrl"
                alt="صورة الطلب"
                class="max-h-[90vh] max-w-full rounded-lg object-contain"
            />
        </div>
    </div>
</template>
