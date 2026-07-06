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
                method="POST"
                action="{{ route('contratos.justicia-alternativa.preview') }}"
                class="space-y-4"
                x-data="{ submitting: false }"
                x-on:submit="submitting = true"
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
                        type="submit"
                        class="bg-gray-800 hover:bg-gray-700 disabled:bg-gray-500 disabled:cursor-not-allowed text-white font-bold py-2 px-4 rounded-lg"
                        x-bind:disabled="submitting"
                    >
                        <span x-show="!submitting">Consultar expediente</span>
                        <span x-show="submitting" style="display: none;">Consultando expediente...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
