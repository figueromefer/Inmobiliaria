<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Traer contrato de Justicia Alternativa
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Pantalla preparada para conectar contratos desde el formulario externo.
                </p>
            </div>

            <a href="{{ route('contratos.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg">
                ← Volver a contratos
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto py-8 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl shadow-sm border p-6 space-y-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
                Esta integración se conectará posteriormente con Google Forms / Apps Script de Justicia Alternativa.
                Por ahora esta pantalla deja listo el flujo para capturar el ID de contrato y validar la información que deberá traer el script.
            </div>

            <form method="GET" action="#" class="space-y-4">
                <div>
                    <label for="contract_id" class="block text-sm font-medium text-gray-700">
                        ID de contrato de Justicia Alternativa
                    </label>
                    <input
                        type="text"
                        id="contract_id"
                        name="contract_id"
                        placeholder="Ej. JA-2026-0001"
                        class="mt-1 w-full border-gray-300 rounded-lg shadow-sm"
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        Cuando se conecte Apps Script, este ID se usará para consultar la respuesta del formulario externo.
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('contratos.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg">
                        Cancelar
                    </a>
                    <button type="button" disabled class="bg-blue-300 text-white font-bold py-2 px-4 rounded-lg cursor-not-allowed">
                        Consultar contrato
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
