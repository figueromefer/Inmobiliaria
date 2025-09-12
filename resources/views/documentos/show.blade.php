<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Detalle de documento') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-700 mb-4">
                    {{ $documento->titulo ?? 'Documento sin título' }}
                </h3>
                <p class="mb-2"><strong>Cliente:</strong> {{ $documento->cliente->nombre ?? '—' }}</p>
                <p class="mb-2"><strong>Propiedad:</strong> {{ $documento->propiedad->alias ?? '—' }}</p>
                <p class="mb-4"><strong>Ruta del archivo:</strong> {{ $documento->archivo }}</p>

                <div class="flex items-center space-x-4">
                    <a href="{{ route('documentos.download', $documento) }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded">
                        Descargar
                    </a>
                    <a href="{{ route('documentos.index') }}"
                       class="text-gray-600 hover:text-gray-800">
                        Volver al listado
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
