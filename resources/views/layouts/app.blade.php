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
        <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">

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
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (!window.TomSelect) return;

                document.querySelectorAll('select.js-searchable-select').forEach(function (select) {
                    if (select.tomselect) return;

                    new TomSelect(select, {
                        allowEmptyOption: true,
                        create: false,
                        maxOptions: 500,
                        sortField: {
                            field: 'text',
                            direction: 'asc'
                        }
                    });
                });

                document.dispatchEvent(new CustomEvent('searchable-selects:ready'));
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                function sanitizePhoneValue(value) {
                    let cleaned = String(value || '').replace(/[^0-9+ ]+/g, '');

                    cleaned = cleaned.replace(/\+/g, function (match, offset) {
                        return offset === 0 ? '+' : '';
                    });

                    return cleaned;
                }

                function cleanMoney(value) {
                    let cleaned = String(value || '')
                        .replace(/[$,\s]/g, '')
                        .replace(/[^0-9.]/g, '');

                    const firstDot = cleaned.indexOf('.');
                    if (firstDot !== -1) {
                        cleaned = cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, '');
                    }

                    const parts = cleaned.split('.');
                    if (parts.length > 1) {
                        cleaned = parts[0] + '.' + parts[1].slice(0, 2);
                    }

                    return cleaned;
                }

                function formatMoney(value) {
                    const cleaned = cleanMoney(value);
                    if (cleaned === '' || cleaned === '.') return '';

                    const hasDecimal = cleaned.includes('.');
                    const parts = cleaned.split('.');
                    const integerPart = parts[0] === '' ? '0' : parts[0];
                    const formattedInteger = Number(integerPart).toLocaleString('en-US', {
                        maximumFractionDigits: 0
                    });

                    if (!hasDecimal) return '$' + formattedInteger;

                    return '$' + formattedInteger + '.' + (parts[1] ?? '');
                }

                document.querySelectorAll('.js-phone-input').forEach(function (input) {
                    input.value = sanitizePhoneValue(input.value);

                    input.addEventListener('input', function () {
                        input.value = sanitizePhoneValue(input.value);
                    });

                    input.addEventListener('paste', function () {
                        setTimeout(function () {
                            input.value = sanitizePhoneValue(input.value);
                        }, 0);
                    });
                });

                document.querySelectorAll('.js-money-input').forEach(function (input) {
                    input.value = formatMoney(input.value);
                    input.addEventListener('input', function () {
                        input.value = formatMoney(input.value);
                        input.setSelectionRange(input.value.length, input.value.length);
                    });
                });

                document.querySelectorAll('.js-day-of-month-input').forEach(function (input) {
                    function normalizeDay() {
                        if (input.value === '') return;

                        const day = parseInt(input.value, 10);
                        if (!Number.isFinite(day) || day < 1) {
                            input.value = '';
                            return;
                        }

                        if (day > 31) input.value = '31';
                    }

                    input.addEventListener('input', normalizeDay);
                    input.addEventListener('blur', normalizeDay);
                });

                document.addEventListener('submit', function (event) {
                    event.target.querySelectorAll('.js-money-input').forEach(function (input) {
                        input.value = cleanMoney(input.value);
                    });
                }, true);
            });
        </script>
    </body>
</html>
