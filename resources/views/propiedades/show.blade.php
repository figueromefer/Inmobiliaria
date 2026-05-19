<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalles de la Propiedad</h2>
    </x-slot>

    @php
        $domicilioPartes = array_filter([
            trim(($propiedad->calle ?? '') . ' ' . ($propiedad->numero_exterior ?? '') . ' ' . ($propiedad->numero_interior ? 'Int. ' . $propiedad->numero_interior : '')),
            $propiedad->colonia ? 'Col. ' . $propiedad->colonia : null,
            $propiedad->codigo_postal ? 'CP ' . $propiedad->codigo_postal : null,
            $propiedad->municipio,
            $propiedad->estado,
        ]);

        $estatusClass = match($propiedad->estatus_informacion ?? 'pendiente_critico') {
            'completo' => 'bg-green-100 text-green-800 border-green-200',
            'pendiente' => 'bg-orange-100 text-orange-800 border-orange-200',
            default => 'bg-red-100 text-red-800 border-red-200',
        };

        $estatusLabel = match($propiedad->estatus_informacion ?? 'pendiente_critico') {
            'completo' => 'Completa',
            'pendiente' => 'Pendiente',
            default => 'Pendiente crítico',
        };
    @endphp

    <div class="py-6 max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $propiedad->alias }}</h1>
                        <span class="text-xs px-3 py-1 rounded-full border {{ $estatusClass }}">{{ $estatusLabel }}</span>
                    </div>
                    <p class="text-sm text-gray-500">Cliente: {{ $propiedad->cliente->nombre ?? 'N/A' }}</p>
                </div>

                <div class="flex gap-2">
                    <a href="{{ route('propiedades.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg">Volver</a>
                    <a href="{{ route('propiedades.edit', $propiedad->pk_propiedad) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">Editar</a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6 space-y-5">
                <h3 class="font-bold text-lg text-gray-900">Información general</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2 bg-gray-50 rounded-lg p-4">
                        <div class="text-xs uppercase text-gray-500 mb-1">Domicilio</div>
                        <div class="font-medium text-gray-800">
                            {{ count($domicilioPartes) ? implode(', ', $domicilioPartes) : ($propiedad->domicilio ?: 'Sin domicilio registrado') }}
                        </div>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs uppercase text-gray-500 mb-1">Agua</div>
                        <div class="font-medium text-gray-800">{{ $propiedad->siapa ?: 'Sin dato' }}</div>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs uppercase text-gray-500 mb-1">CFE</div>
                        <div class="font-medium text-gray-800">{{ $propiedad->cfe ?: 'Sin dato' }}</div>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs uppercase text-gray-500 mb-1">Predial</div>
                        <div class="font-medium text-gray-800">{{ $propiedad->predial ?: 'Sin dato' }}</div>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs uppercase text-gray-500 mb-1">Coordenadas</div>
                        <div class="font-medium text-gray-800">{{ $propiedad->latitud ?: '—' }}, {{ $propiedad->longitud ?: '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h3 class="font-bold text-lg text-gray-900">Mantenimiento</h3>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-xs uppercase text-gray-500 mb-1">Banco</div>
                    <div class="font-medium">{{ $propiedad->mantenimiento_banco ?: 'Sin dato' }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-xs uppercase text-gray-500 mb-1">Cuenta</div>
                    <div class="font-medium">{{ $propiedad->mantenimiento_cuenta ?: 'Sin dato' }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-xs uppercase text-gray-500 mb-1">Monto</div>
                    <div class="font-medium">{{ $propiedad->mantenimiento_monto ? '$'.number_format($propiedad->mantenimiento_monto, 2) : 'Sin dato' }}</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="font-bold text-lg text-gray-900">Documentos</h3>
                    <p class="text-sm text-gray-500">Archivos relacionados con esta propiedad</p>
                </div>

                <a href="{{ route('documentos.create', ['propiedad' => $propiedad->pk_propiedad]) }}" class="bg-gray-800 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">+ Subir</a>
            </div>

            @forelse($propiedad->documentos as $doc)
                <div class="flex justify-between items-center border rounded-lg p-3 mb-2">
                    <span class="font-medium">{{ $doc->titulo ?: 'Documento' }}</span>

                    <div class="space-x-3">
                        <a href="{{ route('documentos.view', $doc) }}" class="text-blue-600 hover:underline">Ver</a>

                        @can('delete-anything')
                            <form action="{{ route('documentos.destroy', $doc) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar documento?');">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 hover:underline">Eliminar</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="text-gray-500 bg-gray-50 rounded-lg p-4">Sin documentos</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
