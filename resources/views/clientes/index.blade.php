<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Clientes') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-6 relative">

            <div class="mb-4 flex justify-between items-center">
                <a href="{{ route('clientes.create') }}" class="bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">+ Nuevo Cliente</a>

                <form method="GET" class="flex gap-2">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Buscar cliente..."
                        class="border rounded px-3 py-2 w-64"
                    >
                    <button class="bg-blue-600 text-white px-4 py-2 rounded">Buscar</button>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Notas</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Correo</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($clientes as $cliente)
                            <tr>
                                <td class="px-4 py-2">{{ $cliente->nombre }}</td>
                                <td class="px-4 py-2">{{ $cliente->notas }}</td>
                                <td class="px-4 py-2">{{ $cliente->correo }}</td>
                                <td class="px-4 py-2 text-right space-x-2">
                                    <a href="{{ route('clientes.show', $cliente) }}" class="text-indigo-600">Ver</a>
                                    <a href="{{ route('clientes.edit', $cliente) }}" class="text-green-600">Editar</a>

                                    @can('delete-anything')
                                        <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600">Eliminar</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4">Sin resultados</td>
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
