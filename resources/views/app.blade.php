<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'عالم المغامرة للترفيه') }}</title>

        <meta name="description" content="عالم المغامرة للترفيه — تأجير ألعاب ترفيهية للأطفال في المملكة العربية السعودية">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ config('app.name', 'عالم المغامرة للترفيه') }}">
        <meta property="og:site_name" content="{{ config('app.name', 'عالم المغامرة للترفيه') }}">
        <meta property="og:description" content="عالم المغامرة للترفيه — تأجير ألعاب ترفيهية للأطفال في المملكة العربية السعودية">
        <meta property="og:image" content="{{ url('/assets/logo.png') }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ config('app.name', 'عالم المغامرة للترفيه') }}">
        <meta name="twitter:description" content="عالم المغامرة للترفيه — تأجير ألعاب ترفيهية للأطفال في المملكة العربية السعودية">
        <meta name="twitter:image" content="{{ url('/assets/logo.png') }}">

        {{-- نفس شعار الهيدر (StoreHeader وغيره): /assets/logo.png --}}
        <link rel="icon" href="/assets/logo.png" type="image/png" sizes="any">
        <link rel="apple-touch-icon" href="/assets/logo.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
