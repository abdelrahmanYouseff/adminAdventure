<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
        <meta name="theme-color" content="#0f766e">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="عالم المغامرة">

        <title inertia>تطبيق الإدارة — عالم المغامرة</title>

        <link rel="manifest" href="/main-app/manifest.webmanifest">
        <link rel="icon" href="/assets/logo.png" type="image/png" sizes="any">
        <link rel="apple-touch-icon" href="/assets/logo.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=noto-kufi-arabic:400,500,600,700|ibm-plex-sans-arabic:400,500,600,700" rel="stylesheet" />

        <style>
            html, body {
                font-family: 'IBM Plex Sans Arabic', 'Noto Kufi Arabic', ui-sans-serif, system-ui, sans-serif;
            }
        </style>

        @vite(['resources/js/main-app/app.ts'])
        @inertiaHead
    </head>
    <body class="bg-[#f4f7f6] font-sans antialiased text-slate-900">
        @inertia

        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('/main-app/sw.js').catch(function () {});
                });
            }
        </script>
    </body>
</html>
