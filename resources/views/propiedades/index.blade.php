<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Propiedades</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <a href="{{ route('propiedades.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">+ Nueva propiedad</a>

            <table class="min-w-full divide-y divide-gray-200">
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
                                <a href="{{ route('propiedades.edit', $propiedad) }}" class="text-green-600 hover:underline">Editar</a>
                                <form action="{{ route('propiedades.destroy', $propiedad) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Estás seguro de eliminar esta propiedad?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
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
        </div>
    </div>
</x-app-layout>
