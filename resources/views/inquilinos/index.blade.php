<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Inquilinos') }}</h2>
                <p class="text-sm text-gray-500 mt-1">Consulta de inquilinos sincronizados desde contratos y sistemas externos.</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-6 space-y-6">
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-900">
            Los inquilinos ya no se crean ni editan manualmente desde el sistema. Su información proviene de contratos privados y futuras integraciones externas.
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-5">
            <form method="GET" action="{{ route('inquilinos.index') }}" class="grid gap-3 md:grid-cols-4" data-live-search>
                <div class="md:col-span-2">
                    <label for="q" class="block text-sm font-medium text-gray-700">Buscar</label>
                    <input type="text" id="q" name="q" value="{{ $q }}" placeholder="Nombre, correo, teléfono, domicilio, nacionalidad o propiedad" class="mt-1 w-full border-gray-300 rounded-lg shadow-sm" data-live-search-input />
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

        <div class="adi-table-wrap">
            <table class="adi-table">
                <thead>
                    <tr>
                        <th><x-table.sort-link column="id" label="ID" :current-sort="$sort" :current-dir="$dir" /></th>
                        <th><x-table.sort-link column="nombre" label="Nombre" :current-sort="$sort" :current-dir="$dir" /></th>
                        <th><x-table.sort-link column="correo" label="Correo" :current-sort="$sort" :current-dir="$dir" /></th>
                        <th>Teléfono</th>
                        <th>Nacionalidad</th>
                        <th>Propiedad vigente</th>
                        <th><x-table.sort-link column="created_at" label="Creado" :current-sort="$sort" :current-dir="$dir" /></th>
                        <th class="adi-table-number">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($inquilinos as $inq)
                        <tr>
                            <td>{{ $inq->id }}</td>
                            <td class="font-semibold text-gray-900">{{ $inq->nombre }}</td>
                            <td>{{ $inq->correo ?? '—' }}</td>
                            <td>{{ $inq->telefono ?? '—' }}</td>
                            <td>{{ $inq->nacionalidad ?? '—' }}</td>
                            <td>
                                @php($propiedadesVigentes = $inq->contratos->map(fn($contrato) => $contrato->propiedad?->alias ?: $contrato->propiedad?->domicilio)->filter()->unique())
                                {{ $propiedadesVigentes->isNotEmpty() ? $propiedadesVigentes->implode(', ') : 'Sin propiedad vigente' }}
                            </td>
                            <td>{{ optional($inq->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="adi-table-number whitespace-nowrap">
                                <a href="{{ route('inquilinos.show', $inq) }}" class="text-blue-600 hover:underline">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-500">No hay inquilinos que coincidan con la búsqueda.</td>
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
