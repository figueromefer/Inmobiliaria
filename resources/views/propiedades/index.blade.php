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
                <div>
                    <label for="estatus_informacion" class="block text-sm font-medium text-gray-700">Estatus</label>
                    <select id="estatus_informacion" name="estatus_informacion" class="mt-1 border rounded px-3 py-2">
                        <option value="">Todos</option>
                        <option value="pendiente_critico" @selected(($estatus ?? '') === 'pendiente_critico')>Pendiente crítico</option>
                        <option value="pendiente" @selected(($estatus ?? '') === 'pendiente')>Pendiente</option>
                        <option value="pendiente_completar" @selected(($estatus ?? '') === 'pendiente_completar')>Pendiente de completar</option>
                        <option value="completo" @selected(($estatus ?? '') === 'completo')>Completo</option>
                    </select>
                </div>
                <button class="bg-gray-800 hover:bg-gray-700 text-white px-4 py-2 rounded">Buscar</button>
                @if(($q ?? '') !== '' || ($estatus ?? '') !== '')
                    <a href="{{ route('propiedades.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded">Limpiar</a>
                @endif
            </form>

            @php
                $estatusMeta = function ($estatus) {
                    return match ($estatus) {
                        'completo' => [
                            'label' => 'Completo',
                            'class' => 'bg-green-100 text-green-800 border-green-200',
                            'dot' => 'bg-green-500',
                        ],
                        'pendiente' => [
                            'label' => 'Pendiente',
                            'class' => 'bg-orange-100 text-orange-800 border-orange-200',
                            'dot' => 'bg-orange-500',
                        ],
                        'pendiente_completar' => [
                            'label' => 'Pendiente de completar',
                            'class' => 'bg-amber-100 text-amber-800 border-amber-200',
                            'dot' => 'bg-amber-500',
                        ],
                        'pendiente_critico' => [
                            'label' => 'Pendiente crítico',
                            'class' => 'bg-red-100 text-red-800 border-red-200',
                            'dot' => 'bg-red-500',
                        ],
                        default => [
                            'label' => 'Sin definir',
                            'class' => 'bg-gray-100 text-gray-700 border-gray-200',
                            'dot' => 'bg-gray-400',
                        ],
                    };
                };
            @endphp

            <table class="min-w-full divide-y divide-gray-200 mt-6">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alias</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domicilio</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estatus</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($propiedades as $propiedad)
                        @php($meta = $estatusMeta($propiedad->estatus_informacion))
                        <tr>
                            <td class="px-4 py-2">{{ $propiedad->alias }}</td>
                            <td class="px-4 py-2">{{ $propiedad->cliente->nombre ?? 'N/A' }}</td>
                            <td class="px-4 py-2">{{ $propiedad->domicilio }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $meta['class'] }}">
                                    <span class="h-2 w-2 rounded-full {{ $meta['dot'] }}"></span>
                                    {{ $meta['label'] }}
                                </span>
                            </td>
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
                            <td colspan="5" class="px-4 py-4 text-center text-gray-500">No hay propiedades registradas.</td>
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
