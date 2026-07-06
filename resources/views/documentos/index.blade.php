<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Documentos') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto mt-6 bg-white lg:px-8 py-6">
            @can('manage-records')
                <div class="mb-4 flex justify-end">
                    <a href="{{ route('documentos.create') }}"
                    class="bg-gray-800 hover:bg-gold-700 text-white font-bold py-2 px-4 rounded">
                        + Nuevo documento
                    </a>
                </div>
            @endcan

            <form method="GET" action="{{ route('documentos.index') }}" class="mb-6 flex flex-wrap gap-2">
                <select name="cliente" class="border-gray-300 rounded shadow-sm px-3 py-2">
                    <option value="">-- Filtrar por cliente --</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->pk_cliente }}" {{ (isset($clienteId) && $clienteId == $cliente->pk_cliente) ? 'selected' : '' }}>
                            {{ $cliente->nombre }}
                        </option>
                    @endforeach
                </select>

                <select name="propiedad" class="border-gray-300 rounded shadow-sm px-3 py-2">
                    <option value="">-- Filtrar por propiedad --</option>
                    @foreach($propiedades as $propiedad)
                        <option value="{{ $propiedad->pk_propiedad }}" {{ (isset($propiedadId) && $propiedadId == $propiedad->pk_propiedad) ? 'selected' : '' }}>
                            {{ $propiedad->alias }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                    Filtrar
                </button>
            </form>

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Título</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Propiedad</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($documentos as $documento)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $documento->titulo ?? 'Sin título' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $documento->cliente->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $documento->propiedad->alias ?? '—' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap text-right space-x-2">
                                    <a href="{{ route('documentos.view', $documento) }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800">
                                        Ver
                                    </a>
                                    <a href="{{ route('documentos.download', $documento) }}" class="text-blue-600 hover:text-blue-800">
                                        Descargar
                                    </a>
                                    @can('delete-anything')
                                        <form action="{{ route('documentos.destroy', $documento) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Está seguro de eliminar este documento?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800">
                                                Eliminar
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500">
                                    No hay documentos registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4">
                    {{ $documentos->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
