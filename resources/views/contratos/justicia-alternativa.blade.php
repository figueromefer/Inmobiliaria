<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Traer contrato de Justicia Alternativa
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Consulta el expediente en el Google Sheet externo y revisa la información antes de importarla.
                </p>
            </div>

            <a href="{{ route('contratos.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg">
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

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
                Captura el número de expediente exactamente como aparece en Justicia Alternativa. El sistema validará que exista, que no esté duplicado en la fuente externa y que no haya sido importado antes.
            </div>

            <form method="POST" action="{{ route('contratos.justicia-alternativa.preview') }}" class="space-y-4">
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
                        placeholder="Ej. JA-2026-0001"
                        class="mt-1 w-full border-gray-300 rounded-lg shadow-sm"
                        required
                        autofocus
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        Este dato se enviará al Web App de Apps Script configurado en el archivo .env.
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('contratos.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg">
                        Cancelar
                    </a>
                    <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 px-4 rounded-lg">
                        Consultar expediente
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
