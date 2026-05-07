<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalles de la Propiedad</h2>
    </x-slot>

    <div class="py-6 max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex justify-between">
            <div>
                <h3 class="text-lg font-bold">{{ $propiedad->alias }}</h3>
                <p class="text-sm text-gray-500">{{ $propiedad->cliente->nombre ?? 'N/A' }}</p>
            </div>

            <a href="{{ route('propiedades.edit', $propiedad->pk_propiedad) }}" class="bg-blue-600 text-white px-3 py-1 rounded">Editar</a>
        </div>

        <div>
            <strong>Domicilio:</strong>
            <p>{{ $propiedad->calle }}
{{ $propiedad->numero_exterior }}
{{ $propiedad->numero_interior ? 'Int. '.$propiedad->numero_interior : '' }},
{{ $propiedad->colonia }},
{{ $propiedad->codigo_postal }},
{{ $propiedad->municipio }},
{{ $propiedad->estado }}</p>
        </div>

        <div>
            <strong>Agua:</strong>
            <p>{{ $propiedad->siapa }}</p>
        </div>

        <div>
            <strong>CFE:</strong>
            <p>{{ $propiedad->cfe }}</p>
        </div>

        <div>
            <strong>Predial:</strong>
            <p>{{ $propiedad->predial }}</p>
        </div>

        <div class="bg-white shadow rounded p-4">
            <div class="flex justify-between mb-3">
                <h3 class="font-bold">Documentos</h3>

                <a href="{{ route('documentos.create', ['propiedad' => $propiedad->pk_propiedad]) }}"
                   class="bg-gray-800 text-white px-3 py-1 rounded">
                    + Subir
                </a>
            </div>

            @forelse($propiedad->documentos as $doc)
                <div class="flex justify-between border p-2 mb-2 rounded">
                    <span>{{ $doc->titulo }}</span>

                    <div class="space-x-2">
                        <a href="{{ route('documentos.view', $doc) }}" class="text-blue-600">Ver</a>

                        <form action="{{ route('documentos.destroy', $doc) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600">Eliminar</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-gray-500">Sin documentos</p>
            @endforelse
        </div>

        <div>
            <a href="{{ route('propiedades.index') }}" class="text-gray-600">← Volver</a>
        </div>
    </div>
</x-app-layout>
