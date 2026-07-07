<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Traer contrato de Justicia Alternativa
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Consulta el expediente en el Google Sheet externo y revisa la información antes de importarla.
                </p>
            </div>

            <a href="{{ route('contratos.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">
                ← Volver a contratos
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto py-8 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl shadow-sm border p-6 space-y-6">
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-800">
                    <p class="font-semibold mb-2">No se pudo consultar el expediente.</p>
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-gray-50 border rounded-lg p-4 text-sm text-gray-800">
                Captura el número de expediente exactamente como aparece en Justicia Alternativa. El sistema validará que exista, que no esté duplicado en la fuente externa y que no haya sido importado antes.
            </div>

            <form
                id="justicia-alternativa-form"
                method="POST"
                action="{{ route('contratos.justicia-alternativa.preview') }}"
                class="space-y-4"
            >
                @csrf

                <div>
                    <label for="expediente" class="block text-sm font-medium text-gray-700">
                        Número de expediente
                    </label>
                    <input
                        type="text"
                        id="expediente"
                        name="expediente"
                        value="{{ old('expediente') }}"
                        placeholder="Ej. 923-2026"
                        class="mt-1 w-full border-gray-300 rounded-lg shadow-sm"
                        required
                        autofocus
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        Este dato se enviará al Web App de Apps Script configurado en el archivo .env.
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('contratos.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">
                        Cancelar
                    </a>
                    <button
                        id="justicia-alternativa-submit"
                        type="submit"
                        class="bg-gray-800 hover:bg-gray-700 disabled:bg-gray-500 disabled:cursor-not-allowed text-white font-bold py-2 px-4 rounded-lg"
                    >
                        <span data-default-label>Consultar expediente</span>
                        <span data-loading-label class="hidden inline-flex items-center gap-2">
                            <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                            Consultando expediente...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="justicia-alternativa-loading-overlay" class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/70 px-4" aria-live="polite" aria-hidden="true">
        <div class="w-full max-w-md rounded-xl bg-white p-6 text-center shadow-2xl" role="status">
            <div class="mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-4 border-gray-200 border-t-gray-800"></div>
            <h3 class="text-lg font-bold text-gray-900">Consultando expediente...</h3>
            <p class="mt-3 text-sm leading-6 text-gray-600">
                Estamos consultando Justicia Alternativa. Esto puede tardar unos segundos. No cierres ni recargues esta ventana.
            </p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('justicia-alternativa-form');
            const button = document.getElementById('justicia-alternativa-submit');
            const overlay = document.getElementById('justicia-alternativa-loading-overlay');

            if (!form || !button) return;

            let submitted = false;
            const defaultLabel = button.querySelector('[data-default-label]');
            const loadingLabel = button.querySelector('[data-loading-label]');

            form.addEventListener('submit', function (event) {
                if (submitted) {
                    event.preventDefault();
                    return;
                }

                submitted = true;
                button.disabled = true;

                if (defaultLabel) defaultLabel.classList.add('hidden');
                if (loadingLabel) loadingLabel.classList.remove('hidden');
                if (overlay) {
                    overlay.classList.remove('hidden');
                    overlay.classList.add('flex');
                    overlay.setAttribute('aria-hidden', 'false');
                }
            });
        });
    </script>
</x-app-layout>
