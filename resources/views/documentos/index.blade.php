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

            <form method="GET" action="{{ route('documentos.index') }}" class="mb-6 flex flex-wrap gap-2" data-live-search>
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="dir" value="{{ $dir }}">
                @if($inquilinoId)
                    <input type="hidden" name="inquilino" value="{{ $inquilinoId }}">
                @endif
                @if($contratoId)
                    <input type="hidden" name="contrato" value="{{ $contratoId }}">
                @endif

                <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Título, tipo, cliente, propiedad o inquilino" class="border-gray-300 rounded shadow-sm px-3 py-2" data-live-search-input>

                <select name="cliente" class="js-searchable-select border-gray-300 rounded shadow-sm px-3 py-2">
                    <option value="">-- Filtrar por cliente --</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->pk_cliente }}" {{ (isset($clienteId) && $clienteId == $cliente->pk_cliente) ? 'selected' : '' }}>
                            {{ $cliente->nombre }}
                        </option>
                    @endforeach
                </select>

                <select name="propiedad" class="js-searchable-select border-gray-300 rounded shadow-sm px-3 py-2">
                    <option value="">-- Filtrar por propiedad --</option>
                    @foreach($propiedades as $propiedad)
                        <option value="{{ $propiedad->pk_propiedad }}" {{ (isset($propiedadId) && $propiedadId == $propiedad->pk_propiedad) ? 'selected' : '' }}>
                            {{ $propiedad->alias }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                    Buscar
                </button>
                @if(($q ?? '') !== '' || $clienteId || $propiedadId || $inquilinoId)
                    <a href="{{ route('documentos.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded">
                        Limpiar
                    </a>
                @endif
            </form>

            <div class="adi-table-wrap">
                <table class="adi-table">
                    <thead>
                        <tr>
                            <th><x-table.sort-link column="titulo" label="Título" :current-sort="$sort" :current-dir="$dir" /></th>
                            <th><x-table.sort-link column="tipo" label="Tipo" :current-sort="$sort" :current-dir="$dir" /></th>
                            <th>Cliente</th>
                            <th>Propiedad</th>
                            <th>Inquilino</th>
                            <th><x-table.sort-link column="created_at" label="Fecha" :current-sort="$sort" :current-dir="$dir" /></th>
                            <th class="adi-table-number">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documentos as $documento)
                            <tr>
                                <td class="font-medium">{{ $documento->titulo ?? 'Sin título' }}</td>
                                <td>{{ $tipos[$documento->tipo] ?? $documento->tipo ?? '—' }}</td>
                                <td>{{ $documento->cliente->nombre ?? '—' }}</td>
                                <td>{{ $documento->propiedad->alias ?? $documento->propiedad->domicilio ?? '—' }}</td>
                                <td>{{ $documento->inquilino->nombre ?? '—' }}</td>
                                <td>{{ optional($documento->created_at)->format('Y-m-d') ?? '—' }}</td>
                                <td class="adi-table-number whitespace-nowrap"><div class="adi-table-actions">
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
                                </div></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-8 text-gray-500">
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
