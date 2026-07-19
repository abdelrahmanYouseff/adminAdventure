<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
        <meta name="theme-color" content="#f5f7fb">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="عمال المغامرة">

        <title inertia>تطبيق العمال — عالم المغامرة</title>

        <link rel="manifest" href="/pwa/manifest.webmanifest">
        <link rel="icon" href="/assets/logo.png" type="image/png" sizes="any">
        <link rel="apple-touch-icon" href="/assets/logo.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=noto-kufi-arabic:400,500,600,700" rel="stylesheet" />

        @vite(['resources/js/pwa/app.ts'])
        @inertiaHead
    </head>
    <body class="bg-[#f5f7fb] font-sans antialiased text-slate-900">
        @inertia

        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('/pwa/sw.js').catch(function () {});
                });
            }
        </script>
    </body>
</html>
