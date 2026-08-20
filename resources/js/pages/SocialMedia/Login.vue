<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Lock } from 'lucide-vue-next';

const form = useForm({
    password: '',
});

function submit() {
    form.post(route('social-media.login.store'), {
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="سوشيال ميديا" />

    <div class="flex min-h-svh items-center justify-center bg-neutral-950 p-4">
        <div class="w-full max-w-sm rounded-2xl border border-neutral-800 bg-neutral-900 p-6 shadow-xl sm:p-8">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-xl bg-blue-600/15 text-blue-400">
                    <Lock class="size-6" />
                </div>
                <h1 class="text-xl font-bold text-white">سوشيال ميديا</h1>
                <p class="mt-1 text-sm text-neutral-400">أدخل كلمة المرور للمتابعة</p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="password" class="text-neutral-300">كلمة المرور</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        class="h-11 border-neutral-700 bg-neutral-950 text-white"
                        placeholder="••••••••"
                        autofocus
                    />
                    <p v-if="form.errors.password" class="text-sm text-red-400">
                        {{ form.errors.password }}
                    </p>
                </div>

                <Button
                    type="submit"
                    class="h-11 w-full bg-blue-600 hover:bg-blue-700"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'جاري الدخول...' : 'دخول' }}
                </Button>
            </form>
        </div>
    </div>
</template>
