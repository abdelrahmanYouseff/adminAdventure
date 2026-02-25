<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

defineProps<{
    order_id: string | null;
}>();

const showPopup = ref(false);

onMounted(() => {
    showPopup.value = true;
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-red-50 via-white to-rose-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950">
        <Head title="فشل عملية الدفع - عالم المغامرة" />

        <Dialog :open="showPopup" @update:open="showPopup = $event">
            <DialogContent class="text-center sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center justify-center gap-2 text-red-600 dark:text-red-400">
                        <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        فشلت عملية الدفع
                    </DialogTitle>
                </DialogHeader>
                <p class="text-muted-foreground py-2">
                    لم تتم عملية الدفع. لم يتم خصم أي مبلغ من حسابك. يمكنك إعادة المحاولة لاحقاً أو التواصل مع الدعم.
                </p>
                <DialogFooter class="flex justify-center sm:justify-center">
                    <Button type="button" variant="destructive" @click="showPopup = false">
                        حسناً
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <div class="relative overflow-hidden">
            <div class="absolute inset-0 -z-10 opacity-30 pointer-events-none">
                <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-red-400 blur-3xl mix-blend-multiply"></div>
                <div class="absolute -bottom-24 -left-24 w-72 h-72 rounded-full bg-rose-400 blur-3xl mix-blend-multiply"></div>
            </div>

            <div class="max-w-2xl mx-auto px-4 pt-16 pb-12">
                <div class="flex items-center justify-center gap-4 mb-8">
                    <div class="w-14 h-14 rounded-2xl bg-white/80 dark:bg-white/10 backdrop-blur flex items-center justify-center shadow-lg ring-1 ring-black/5">
                        <AppLogo />
                    </div>
                </div>

                <div class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-xl rounded-3xl shadow-2xl ring-1 ring-black/5 dark:ring-white/5 overflow-hidden text-center">
                    <div class="px-8 py-12">
                        <div
                            class="mx-auto w-16 h-16 rounded-full bg-red-100 dark:bg-red-900/50 flex items-center justify-center mb-6"
                        >
                            <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">
                            فشلت عملية الدفع
                        </h1>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">
                            لم تتم عملية الدفع. لم يتم خصم أي مبلغ من حسابك. يمكنك إعادة المحاولة أو العودة للصفحة الرئيسية.
                        </p>
                        <div v-if="order_id" class="text-sm text-gray-500 dark:text-gray-400 font-mono mb-6">
                            المرجع: {{ order_id }}
                        </div>
                        <Link
                            href="/"
                            class="inline-flex items-center justify-center rounded-xl bg-red-600 px-6 py-3 text-white font-medium hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        >
                            العودة للصفحة الرئيسية
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
