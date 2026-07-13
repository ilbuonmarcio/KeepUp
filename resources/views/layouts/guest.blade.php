<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'KeepUp') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/scss/app.scss', 'resources/js/app.js'])
    </head>
    <body>
        <main class="auth-shell">
            <div class="auth-brand">
                <a href="/" aria-label="KeepUp home"><img src="/images/logo.png" alt="KeepUp"></a>
            </div>

            <div class="auth-card">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
