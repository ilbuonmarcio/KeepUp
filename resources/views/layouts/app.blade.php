<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/scss/app.scss'])
        @yield('page-css')
    </head>
    <body>
        <div id="app">
            <div id="app-navigation">
                @include('layouts.navigation')
            </div>
            <div id="app-content">
                @yield('page-content')
            </div>
            <div id="credits">
                Made with ❤️ by <a href="https://goldmark.solutions">Goldmark Solutions</a>
            </div>
        </div>

        @vite(['resources/js/app.js'])
        @yield('page-js')
    </body>
</html>
