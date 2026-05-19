<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Dorantes Aranda') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('custom.css') }}" />
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            @if (session('success') || session('error') || session('warning') || session('info'))
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 4500)"
                    x-show="show"
                    x-transition
                    class="fixed top-5 right-5 z-50 max-w-sm"
                >
                    @if (session('success'))
                        <div class="rounded-xl bg-green-600 text-white shadow-lg px-5 py-4">
                            <div class="font-semibold">Listo</div>
                            <div class="text-sm opacity-90">{{ session('success') }}</div>
                        </div>
                    @elseif (session('error'))
                        <div class="rounded-xl bg-red-600 text-white shadow-lg px-5 py-4">
                            <div class="font-semibold">Error</div>
                            <div class="text-sm opacity-90">{{ session('error') }}</div>
                        </div>
                    @elseif (session('warning'))
                        <div class="rounded-xl bg-orange-500 text-white shadow-lg px-5 py-4">
                            <div class="font-semibold">Atención</div>
                            <div class="text-sm opacity-90">{{ session('warning') }}</div>
                        </div>
                    @elseif (session('info'))
                        <div class="rounded-xl bg-blue-600 text-white shadow-lg px-5 py-4">
                            <div class="font-semibold">Información</div>
                            <div class="text-sm opacity-90">{{ session('info') }}</div>
                        </div>
                    @endif
                </div>
            @endif

            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-4">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
