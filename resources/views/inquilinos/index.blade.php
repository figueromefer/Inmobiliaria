<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Inquilinos') }}</h2>
                <p class="text-sm text-gray-500 mt-1">Catálogo de inquilinos, documentos y contratos relacionados</p>
            </div>

            @can('manage-records')
                <a href="{{ route('inquilinos.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
                    + Nuevo inquilino
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-6 space-y-6">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <form method="GET" action="{{ route('inquilinos.index') }}" class="grid gap-3 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label for="q" class="block text-sm font-medium text-gray-700">Buscar</label>
                    <input type="text" id="q" name="q" value="{{ $q }}" placeholder="Nombre, correo, teléfono, domicilio o nacionalidad" class="mt-1 w-full border-gray-300 rounded-lg shadow-sm" />
                </div>

                <div>
                    <label for="perPage" class="block text-sm font-medium text-gray-700">Por página</label>
                    <select id="perPage" name="perPage" class="mt-1 w-full border-gray-300 rounded-lg shadow-sm">
                        @foreach ([10,15,25,50,100] as $pp)
                            <option value="{{ $pp }}" @selected($perPage == $pp)>{{ $pp }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">Aplicar</button>
                    <a href="{{ route('inquilinos.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg">Limpiar</a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto bg-white border rounded-xl shadow-sm">
            <table class="min-w-full text-sm">
                @php
                    function sortUrl($col, $currentSort, $currentDir) {
                        $nextDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
                        return request()->fullUrlWithQuery(['sort' => $col, 'dir' => $nextDir, 'page' => 1]);
                    }
                @endphp

                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3"><a href="{{ sortUrl('id', $sort ?? 'nombre', $dir ?? 'asc') }}" class="underline">ID</a></th>
                        <th class="text-left px-4 py-3"><a href="{{ sortUrl('nombre', $sort ?? 'nombre', $dir ?? 'asc') }}" class="underline">Nombre</a></th>
                        <th class="text-left px-4 py-3"><a href="{{ sortUrl('correo', $sort ?? 'nombre', $dir ?? 'asc') }}" class="underline">Correo</a></th>
                        <th class="text-left px-4 py-3">Teléfono</th>
                        <th class="text-left px-4 py-3">Nacionalidad</th>
                        <th class="text-left px-4 py-3"><a href="{{ sortUrl('created_at', $sort ?? 'nombre', $dir ?? 'asc') }}" class="underline">Creado</a></th>
                        <th class="text-right px-4 py-3">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($inquilinos as $inq)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3">{{ $inq->id }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $inq->nombre }}</td>
                            <td class="px-4 py-3">{{ $inq->correo ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $inq->telefono ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $inq->nacionalidad ?? '—' }}</td>
                            <td class="px-4 py-3">{{ optional($inq->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('inquilinos.show', $inq) }}" class="text-blue-600 hover:underline">Ver</a>
                                @can('manage-records')
                                    <a href="{{ route('inquilinos.edit', $inq) }}" class="text-gray-700 hover:underline ml-3">Editar</a>
                                @endcan
                                @can('delete-anything')
                                    <form action="{{ route('inquilinos.destroy', $inq) }}" method="POST" class="inline ml-3" onsubmit="return confirm('¿Eliminar inquilino?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">No hay inquilinos que coincidan con la búsqueda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $inquilinos->onEachSide(1)->links() }}
        </div>
    </div>
</x-app-layout>
