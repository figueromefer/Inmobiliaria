<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Clientes') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-6 relative">

            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <div class="mb-4 flex justify-between items-center">
                <div class="flex flex-wrap gap-2">
                    @can('manage-records')
                    <a href="{{ route('clientes.create') }}" class="bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">+ Nuevo Cliente</a>
                    @endcan
                    @can('delete-anything')
                    <a href="{{ route('archivados.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">Ver archivados</a>
                    @endcan
                </div>
                <form method="GET" class="flex gap-2" data-live-search>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Buscar cliente..."
                        class="border rounded px-3 py-2 w-64"
                        data-live-search-input
                    >
                    <button class="bg-gray-800 hover:bg-gray-700 text-white px-4 py-2 rounded">Buscar</button>
                </form>
            </div>

            <div class="adi-table-wrap">
                <table class="adi-table">
                    <thead>
                        <tr>
                            <th><x-table.sort-link column="nombre" label="Nombre" :current-sort="$sort" :current-dir="$dir" /></th>
                            <th>Notas</th>
                            <th><x-table.sort-link column="correo" label="Correo" :current-sort="$sort" :current-dir="$dir" /></th>
                            <th class="adi-table-number">Disponible</th>
                            <th class="adi-table-number">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clientes as $cliente)
                            <tr>
                                <td class="font-medium">{{ $cliente->nombre }}</td>
                                <td>{{ $cliente->notas }}</td>
                                <td>{{ $cliente->correo }}</td>
                                <td class="adi-table-number">${{ number_format((float) ($saldosDisponibles[$cliente->pk_cliente] ?? 0), 2) }}</td>
                                <td class="adi-table-number"><div class="adi-table-actions">
                                    <a href="{{ route('clientes.show', $cliente) }}" class="text-indigo-600">Ver</a>
                                    @can('manage-records')
                                    <a href="{{ route('clientes.edit', $cliente) }}" class="text-green-600">Editar</a>
                                    @endcan
                                    @can('delete-anything')
                                        <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" class="inline" onsubmit="return confirm('{{ $cliente->contratos_count > 0 ? 'Este cliente tiene '.$cliente->contratos_count.' contrato(s). Se archivará el cliente y también sus contratos asociados. Los movimientos/documentos no se borrarán. ¿Continuar?' : 'Se archivará este cliente. ¿Continuar?' }}');">
                                            @csrf
                                            @method('DELETE')
                                            @if($cliente->contratos_count > 0)
                                                <input type="hidden" name="archive_contracts" value="1">
                                            @endif
                                            <button class="text-red-600">Archivar</button>
                                        </form>
                                    @endcan
                                </div></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-8 text-gray-500">Sin resultados</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="p-4">
                    {{ $clientes->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
