<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Propiedades</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-6 relative">
            @can('manage-records')
            <a href="{{ route('propiedades.create') }}" class="bg-gray-800 hover:bg-gold-700 text-white font-bold py-2 px-4 rounded">+ Nueva propiedad</a>
            @endcan
            <a href="{{ route('propiedades.mapa') }}" class="bg-gray-500 hover:bg-gold-700 text-white font-bold py-2 px-4 rounded right-0 absolute mr-4">Mapa de propiedades</a>

            <form method="GET" action="{{ route('propiedades.index') }}" class="mt-6 flex flex-wrap items-end gap-2">
                <div>
                    <label for="q" class="block text-sm font-medium text-gray-700">Buscar</label>
                    <input type="text" id="q" name="q" value="{{ $q ?? '' }}" placeholder="Alias, domicilio, colonia, municipio o estado" class="mt-1 border rounded px-3 py-2 w-80">
                </div>
                <button class="bg-gray-800 hover:bg-gray-700 text-white px-4 py-2 rounded">Buscar</button>
                @if(($q ?? '') !== '')
                    <a href="{{ route('propiedades.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded">Limpiar</a>
                @endif
            </form>

            <table class="min-w-full divide-y divide-gray-200 mt-6">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alias</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domicilio</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($propiedades as $propiedad)
                        <tr>
                            <td class="px-4 py-2">{{ $propiedad->alias }}</td>
                            <td class="px-4 py-2">{{ $propiedad->cliente->nombre ?? 'N/A' }}</td>
                            <td class="px-4 py-2">{{ $propiedad->domicilio }}</td>
                            <td class="px-4 py-2 text-right space-x-2">
                                <a href="{{ route('propiedades.show', $propiedad) }}" class="text-indigo-600 hover:underline">Ver</a>
                                @can('manage-records')
                                <a href="{{ route('propiedades.edit', $propiedad) }}" class="text-green-600 hover:underline">Editar</a>
                                @endcan
                                <form action="{{ route('propiedades.destroy', $propiedad) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Estás seguro de eliminar esta propiedad?');">
                                    @csrf
                                    @method('DELETE')
                                    @can('delete-anything')
                                        <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                                    @endcan
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-center text-gray-500">No hay propiedades registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">
                {{ $propiedades->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
