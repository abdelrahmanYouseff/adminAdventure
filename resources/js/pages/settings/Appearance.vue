<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AdminThemePicker from '@/components/AdminThemePicker.vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'المظهر',
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="إعدادات المظهر" />

        <SettingsLayout>
            <div class="space-y-10" dir="rtl">
                <div class="space-y-6">
                    <HeadingSmall
                        title="ثيم لوحة التحكم"
                        description="اختر شكل الواجهة الإدارية. ثيم سلة يطبّق ألواناً وتخطيطاً مستوحى من سلة على النظام بالكامل مع الإبقاء على محتواكم."
                    />
                    <AdminThemePicker />
                </div>

                <div class="space-y-6">
                    <HeadingSmall
                        title="الوضع الليلي/النهاري"
                        description="تحكم في الوضع الفاتح أو الداكن بشكل مستقل عن الثيم."
                    />
                    <AppearanceTabs />
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
