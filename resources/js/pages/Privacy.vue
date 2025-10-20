<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950">
        <Head title="سياسة الخصوصية - عالم المغامرة" />

        <!-- Header / Hero -->
        <div class="relative overflow-hidden">
            <div class="absolute inset-0 -z-10 opacity-30 pointer-events-none">
                <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-blue-400 blur-3xl mix-blend-multiply"></div>
                <div class="absolute -bottom-24 -left-24 w-72 h-72 rounded-full bg-indigo-400 blur-3xl mix-blend-multiply"></div>
            </div>

            <div class="max-w-5xl mx-auto px-4 pt-12 sm:pt-16">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-white/70 dark:bg-white/10 backdrop-blur flex items-center justify-center shadow-sm ring-1 ring-black/5">
                        <AppLogo />
                    </div>
                    <div>
                        <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white">سياسة الخصوصية</h1>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">سياسة حماية البيانات لنظام عالم المغامرة</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Card -->
        <div class="max-w-5xl mx-auto px-4 py-8 sm:py-12">
            <div class="bg-white/80 dark:bg-gray-900/70 backdrop-blur-xl rounded-3xl shadow-2xl ring-1 ring-black/5 dark:ring-white/5 overflow-hidden">
                <!-- Sub-header -->
                <div class="px-6 sm:px-10 py-6 border-b border-gray-100/80 dark:border-gray-800/60 bg-gradient-to-r from-white to-blue-50/60 dark:from-gray-900 dark:to-gray-800">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            آخر تحديث: <span class="font-medium text-gray-900 dark:text-gray-200">{{ new Date().toLocaleDateString('ar-SA') }}</span>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            شركة عالم المغامرة (Adventure World) — جميع الحقوق محفوظة
                        </div>
                    </div>
                </div>

                <!-- Body -->
                <div class="px-6 sm:px-10 py-8 space-y-8 text-gray-800 dark:text-gray-200">
                    <section>
                        <h2 class="text-xl font-bold mb-3">مقدمة</h2>
                        <p class="leading-8">نلتزم في عالم المغامرة بحماية خصوصية مستخدمينا. توضح هذه السياسة كيفية جمع بياناتك الشخصية ومعالجتها وحمايتها عند استخدامك لمنصتنا وخدماتنا.</p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold mb-3">١) البيانات التي نجمعها</h2>
                        <ul class="list-disc pr-6 space-y-2 leading-8">
                            <li>معلومات الحساب: الاسم، البريد الإلكتروني، رقم الهاتف، وأذونات الوصول.</li>
                            <li>بيانات الطلبات: تفاصيل الطلبات والمنتجات والكميات والفواتير.</li>
                            <li>بيانات الدفع: طريقة الدفع والمرجعات ذات الصلة (لا نخزن بيانات البطاقة الكاملة).</li>
                            <li>بيانات تقنية: عنوان IP، نوع المتصفح، نظام التشغيل، وملفات تعريف الارتباط.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold mb-3">٢) استخدام البيانات</h2>
                        <ul class="list-disc pr-6 space-y-2 leading-8">
                            <li>تشغيل المنصة وتقديم الخدمات وإدارة الطلبات والفواتير.</li>
                            <li>تحسين التجربة والأداء ودعم العملاء وإرسال الإشعارات اللازمة.</li>
                            <li>الامتثال للالتزامات القانونية والتنظيمية ومتطلبات التدقيق.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold mb-3">٣) مشاركة البيانات</h2>
                        <p class="leading-8">قد نشارك بياناتك مع مزودي خدمات موثوقين (مثل بوابات الدفع) فقط للأغراض التشغيلية وبما يتوافق مع اتفاقيات سرية البيانات. لا نقوم ببيع بياناتك لأي طرف ثالث.</p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold mb-3">٤) الحماية والأمان</h2>
                        <p class="leading-8">نطبق ضوابط تقنية وتنظيمية لحماية البيانات، بما في ذلك التشفير، والتحكم بالوصول، والمراجعة الدورية. ومع ذلك، لا يمكن ضمان أمان مطلق عبر الإنترنت.</p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold mb-3">٥) حقوق المستخدم</h2>
                        <ul class="list-disc pr-6 space-y-2 leading-8">
                            <li>الوصول إلى البيانات الشخصية أو تصحيحها أو طلب حذفها وفق القوانين المعمول بها.</li>
                            <li>الاعتراض على بعض المعالجات أو طلب تقييدها في ظروف محددة.</li>
                            <li>سحب الموافقة على المعالجة التي تعتمد على موافقتك.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold mb-3">٦) ملفات تعريف الارتباط</h2>
                        <p class="leading-8">نستخدم ملفات تعريف الارتباط لتحسين الأداء وتجربة الاستخدام والتحليلات. يمكنك ضبط إعدادات المتصفح للتحكم في قبول ملفات تعريف الارتباط.</p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold mb-3">٧) التواصل</h2>
                        <p class="leading-8">للاستفسارات المتعلقة بالخصوصية، راسلنا على: <span class="font-mono">privacy@adventureksa.com</span></p>
                    </section>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-sm">
                        <Link href="/" class="text-blue-600 hover:text-blue-700 dark:text-blue-400">العودة للصفحة الرئيسية</Link>
                        <span class="text-gray-500 dark:text-gray-400">© {{ new Date().getFullYear() }} عالم المغامرة</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>


