<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Propiedades</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-6 relative">
            <div class="flex flex-wrap items-center gap-2">
                @can('manage-records')
                <a href="{{ route('propiedades.create') }}" class="bg-gray-800 hover:bg-gold-700 text-white font-bold py-2 px-4 rounded">+ Nueva propiedad</a>
                @endcan
                <a href="{{ route('propiedades.mapa') }}" class="bg-gray-500 hover:bg-gold-700 text-white font-bold py-2 px-4 rounded">Mapa de propiedades</a>
                @can('delete-anything')
                <a href="{{ route('archivados.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">Ver archivados de clientes/contratos</a>
                @endcan
            </div>

            <form method="GET" action="{{ route('propiedades.index') }}" class="mt-6 flex flex-wrap items-end gap-2" data-live-search>
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="dir" value="{{ $dir }}">
                <div>
                    <label for="q" class="block text-sm font-medium text-gray-700">Buscar</label>
                    <input type="text" id="q" name="q" value="{{ $q ?? '' }}" placeholder="Alias, domicilio, colonia, municipio o estado" class="mt-1 border rounded px-3 py-2 w-80" data-live-search-input>
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

            <div class="adi-table-wrap mt-6"><table class="adi-table">
                <thead>
                    <tr>
                        <th><x-table.sort-link column="alias" label="Alias" :current-sort="$sort" :current-dir="$dir" /></th>
                        <th><x-table.sort-link column="cliente" label="Cliente" :current-sort="$sort" :current-dir="$dir" /></th>
                        <th><x-table.sort-link column="domicilio" label="Domicilio" :current-sort="$sort" :current-dir="$dir" /></th>
                        <th><x-table.sort-link column="estatus_informacion" label="Estatus" :current-sort="$sort" :current-dir="$dir" /></th>
                        <th>Cobro</th>
                        <th class="adi-table-number">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($propiedades as $propiedad)
                        @php($meta = $estatusMeta($propiedad->estatus_informacion))
                        <tr>
                            <td class="font-medium">{{ $propiedad->alias }}</td>
                            <td>{{ $propiedad->cliente->nombre ?? 'N/A' }}</td>
                            <td>{{ $propiedad->domicilio }}</td>
                            <td>
                                <span class="inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $meta['class'] }}">
                                    <span class="h-2 w-2 rounded-full {{ $meta['dot'] }}"></span>
                                    {{ $meta['label'] }}
                                </span>
                            </td>
                            <td><span class="text-xs font-semibold">{{ $propiedad->operational_status['label'] }}</span></td>
                            <td class="adi-table-number"><div class="adi-table-actions">
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
                            </div></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-gray-500">No hay propiedades registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table></div>
            <div class="p-3">
                {{ $propiedades->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
