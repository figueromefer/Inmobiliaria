<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Inquilinos') }}
        </h2>
    </x-slot>
    <div class="max-w-6xl mx-auto mt-6 bg-white lg:px-8 py-6">

    {{-- Filtros / acciones --}}
    <form method="GET" action="{{ route('inquilinos.index') }}" class="mb-4 grid gap-3 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <label for="q" class="block text-sm font-medium">Buscar</label>
            <input
                type="text"
                id="q"
                name="q"
                value="{{ $q }}"
                placeholder="Nombre, correo, teléfono o domicilio"
                class="mt-1 w-full border rounded px-3 py-2"
            />
        </div>

        <div>
            <label for="perPage" class="block text-sm font-medium">Por página</label>
            <select id="perPage" name="perPage" class="mt-1 w-full border rounded px-3 py-2">
                @foreach ([10,15,25,50,100] as $pp)
                    <option value="{{ $pp }}" @selected($perPage == $pp)>{{ $pp }}</option>
                @endforeach
            </select>
        </div>

        <div class="sm:col-span-3 flex gap-2">
            <button type="submit" class="inline-flex items-center bg-gray-800 hover:bg-gold-700 text-white font-bold py-2 px-4 rounded">
                Aplicar
            </button>
            <a href="{{ route('inquilinos.index') }}" class="inline-flex items-center bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Limpiar
            </a>
        </div>
    </form>

    {{-- Tabla --}}
    <div class="overflow-x-auto bg-white border rounded">
        <table class="min-w-full text-sm">
            @php
                function sortUrl($col, $currentSort, $currentDir) {
                    $nextDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
                    return request()->fullUrlWithQuery(['sort' => $col, 'dir' => $nextDir, 'page' => 1]);
                }
            @endphp

            <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-2">
                <a href="{{ sortUrl('id', $sort ?? 'nombre', $dir ?? 'asc') }}" class="underline">ID</a>
                </th>
                <th class="text-left px-4 py-2">
                <a href="{{ sortUrl('nombre', $sort ?? 'nombre', $dir ?? 'asc') }}" class="underline">Nombre</a>
                </th>
                <th class="text-left px-4 py-2">
                <a href="{{ sortUrl('correo', $sort ?? 'nombre', $dir ?? 'asc') }}" class="underline">Correo</a>
                </th>
                <th class="text-left px-4 py-2">Teléfono</th>
                <th class="text-left px-4 py-2">Domicilio</th>
                <th class="text-left px-4 py-2">Nacionalidad</th>
                <th class="text-left px-4 py-2">
                <a href="{{ sortUrl('created_at', $sort ?? 'nombre', $dir ?? 'asc') }}" class="underline">Creado</a>
                </th>
            </tr>
            </thead>

            <tbody>
                @forelse ($inquilinos as $inq)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2">{{ $inq->id }}</td>
                        <td class="px-4 py-2">{{ $inq->nombre }}</td>
                        <td class="px-4 py-2">{{ $inq->correo ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $inq->telefono ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $inq->domicilio ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $inq->nacionalidad ?? '—' }}</td>
                        <td class="px-4 py-2">
                            {{ optional($inq->created_at)->format('Y-m-d H:i') ?? '—' }}
                        </td>
                        {{-- <td class="px-4 py-2">
                            <a href="{{ route('inquilinos.show', $inq) }}" class="underline">Ver</a>
                            <a href="{{ route('inquilinos.edit', $inq) }}" class="underline ml-2">Editar</a>
                        </td> --}}
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            No hay inquilinos que coincidan con la búsqueda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    <div class="mt-4">
        {{ $inquilinos->onEachSide(1)->links() }}
        {{-- Si no usas Tailwind pagination views, puedes hacer:
        {{ $inquilinos->withQueryString()->links('pagination::simple-default') }} --}}
    </div>
</div>
</x-app-layout>