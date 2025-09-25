<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Clientes') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-6 relative">
            <div class="mb-4 relative">
                <a href="{{ route('clientes.create') }}" class="bg-gray-800 hover:bg-gold-700 text-white font-bold py-2 px-4 rounded mb-4">+ Nuevo Cliente</a>
                <a href="https://forms.gle/jzHofrGekRVLvNNe9" class="bg-gray-500 hover:bg-gold-700 text-white font-bold py-2 px-4 rounded right-0 absolute mr-4 mb-4" target="_blank">+ Nuevo CPS</a>
            </div>
          

      

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RFC</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Correo</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($clientes as $cliente)
                            <tr>
                                <td class="px-4 py-2">{{ $cliente->nombre }}</td>
                                <td class="px-4 py-2">{{ $cliente->rfc }}</td>
                                <td class="px-4 py-2">{{ $cliente->correo }}</td>
                                <td class="px-4 py-2 text-right space-x-2">
                                    <a href="{{ route('clientes.show', $cliente) }}" class="text-indigo-600 hover:underline">Ver</a>
                                    <a href="{{ route('clientes.edit', $cliente) }}" class="text-green-600 hover:underline">Editar</a>
                                    <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Estás seguro de eliminar este cliente?');">
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
                                <td colspan="4" class="px-4 py-4 text-center text-gray-500">
                                    No hay clientes registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
