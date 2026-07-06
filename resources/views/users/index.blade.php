{{-- resources/views/users/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Usuarios
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-6 relative">
            @can('manage-users')
                <a href="{{ route('users.create') }}" class="bg-gray-800 hover:bg-gold-700 text-white font-bold py-2 px-4 rounded">
                    + Nuevo usuario
                </a>
            @endcan

            @if (session('status'))
                <div class="mb-4 p-3 rounded bg-green-100 text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <form method="GET" action="{{ route('users.index') }}" class="mt-6 flex flex-wrap items-end gap-2">
                <div>
                    <label for="q" class="block text-sm font-medium text-gray-700">Buscar</label>
                    <input type="text" id="q" name="q" value="{{ $q ?? '' }}" placeholder="Nombre o email" class="mt-1 border rounded px-3 py-2 w-80">
                </div>
                <button class="bg-gray-800 hover:bg-gray-700 text-white px-4 py-2 rounded">Buscar</button>
                @if(($q ?? '') !== '')
                    <a href="{{ route('users.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded">Limpiar</a>
                @endif
            </form>

            <table class="min-w-full divide-y divide-gray-200 mt-6">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                         @can('delete-anything')    
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($users as $u)
                        <tr>
                            <td class="px-4 py-2">{{ $u->name }}</td>
                            <td class="px-4 py-2">{{ $u->email }}</td>
                            <td class="px-4 py-2">{{ $u->role === 'admin' ? 'Administrador' : 'Agente' }}</td>
                            <td class="px-4 py-2 text-right space-x-2">
                                @can('delete-anything')    
                                    <a href="{{ route('users.edit', $u) }}" class="text-green-600 hover:underline">Editar</a>
                                
                                    <form action="{{ route('users.destroy', $u) }}" method="POST" class="inline"
                                          onsubmit="return confirm('¿Eliminar usuario?');">
                                        @csrf
                                        @method('DELETE')
                                        
                                        <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                                   
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-center text-gray-500">No hay usuarios registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="p-3">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
