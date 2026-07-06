<x-app-layout>
  <x-slot name="header">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          {{ __('Contratos') }}
        </h2>
        <p class="text-sm text-gray-500 mt-1">Contratos privados y futuras integraciones con Justicia Alternativa.</p>
      </div>

      @can('manage-users')
        <div class="flex flex-wrap items-center gap-2">
          <a href="https://forms.gle/F5ao5ZMKN8bJToVy5" target="_blank" class="bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">
            + Nuevo contrato privado
          </a>

          <a href="{{ route('contratos.justicia-alternativa') }}" class="bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">
            Traer contrato de justicia alternativa
          </a>

          <a href="{{ route('contratos.pendientes.index') }}"
             class="{{ ($pendientesCount ?? 0) > 0 ? 'bg-gray-800 hover:bg-gray-700' : 'bg-gray-500 hover:bg-gray-700' }} text-white font-bold py-2 px-4 rounded-lg">
            {{ $pendientesCount ?? 0 }} Contratos pendientes
          </a>
        </div>
      @endcan
    </div>
  </x-slot>

  <div class="max-w-7xl mx-auto mt-6 bg-white lg:px-8 py-6">
      
    {{-- Filtros --}}
    <form method="GET" action="{{ route('contratos.index') }}" class="mb-4 grid gap-3 sm:grid-cols-6">
      <div class="sm:col-span-2">
        <label for="q" class="block text-sm font-medium">Buscar</label>
        <input type="text" id="q" name="q" value="{{ $q }}"
               placeholder="Solicitante o domicilio"
               class="mt-1 w-full border rounded px-3 py-2"/>
      </div>

      {{-- Filtro por solicitante (cliente) --}}
        <div class="sm:col-span-2">
            <label for="solicitante" class="block text-sm font-medium">Solicitante (cliente)</label>
            <select id="solicitante" name="solicitante" class="js-searchable-select mt-1 w-full border rounded px-3 py-2">
                <option value="">— Todos —</option>
                @foreach ($solicitantes as $nombreCliente)
                <option value="{{ $nombreCliente }}" @selected(($solicitante ?? '') === $nombreCliente)>
                    {{ $nombreCliente }}
                </option>
                @endforeach
            </select>
        </div>

      <div>
        <label for="desde" class="block text-sm font-medium">Desde (alta)</label>
        <input type="date" id="desde" name="desde" value="{{ $desde }}" class="mt-1 w-full border rounded px-3 py-2"/>
      </div>

      <div>
        <label for="hasta" class="block text-sm font-medium">Hasta (alta)</label>
        <input type="date" id="hasta" name="hasta" value="{{ $hasta }}" class="mt-1 w-full border rounded px-3 py-2"/>
      </div>

      <div>
        <label for="perPage" class="block text-sm font-medium">Por página</label>
        <select id="perPage" name="perPage" class="mt-1 w-full border rounded px-3 py-2">
          @foreach ([10,15,25,50,100] as $pp)
            <option value="{{ $pp }}" @selected($perPage == $pp)>{{ $pp }}</option>
          @endforeach
        </select>
      </div>

      <div class="sm:col-span-6 flex gap-2">
        <button type="submit" class="inline-flex items-center bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
          Aplicar
        </button>
        <a href="{{ route('contratos.index') }}" class="inline-flex items-center bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
          Limpiar
        </a>
      </div>
    </form>

    @php
      function sortUrlC($col, $sort, $dir) {
        $next = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $col, 'dir' => $next, 'page' => 1]);
      }
      $sort = $sort ?? 'fecha';
      $dir  = $dir  ?? 'desc';
    @endphp

    {{-- Tabla --}}
    <div class="overflow-x-auto bg-white border rounded">
        <table class="min-w-full text-sm lg:table-fixed">
            <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-3 w-32"><a class="underline" href="{{ sortUrlC('id',$sort,$dir) }}">Expediente</a></th>
                <th class="text-left px-4 py-3 w-48"><a class="underline" href="{{ sortUrlC('cliente',$sort,$dir) }}">Cliente</a></th>
                <th class="text-left px-4 py-3 w-48">Arrendatario</th>
                <th class="text-left px-4 py-3">Propiedad / Domicilio</th>
                <th class="text-left px-4 py-3 w-48">Vigencia</th>
                <th class="text-left px-4 py-3 w-36"><a class="underline" href="{{ sortUrlC('monto_mensual',$sort,$dir) }}">Monto mensual</a></th>
                <th class="text-right px-4 py-3 w-28 sticky right-0 bg-gray-50 shadow-[-8px_0_12px_-12px_rgba(0,0,0,0.35)]">Acciones</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($contratos as $c)
                @php
                    $alerta = $c->por_expirar;
                @endphp
                <tr class="border-b hover:bg-gray-50">
                <td class="px-4 py-3 align-top">
                    <div class="font-semibold text-gray-900">{{ $c->expediente_justicia_alternativa ?: '#'.$c->id }}</div>
                    <div class="text-xs text-gray-500">{{ $c->origen === 'justicia_alternativa' ? 'Justicia Alternativa' : 'Privado' }}</div>
                </td>
                <td class="px-4 py-3 align-top">
                    <div class="font-medium text-gray-900 truncate">{{ optional($c->cliente)->nombre ?? '—' }}</div>
                    <div class="text-xs text-gray-500">{{ $c->tipo_solicitante ?? '—' }}</div>
                </td>
                <td class="px-4 py-3 align-top truncate">{{ optional($c->inquilino)->nombre ?? '—' }}</td>
                <td class="px-4 py-3 align-top">
                    <div class="font-medium text-gray-900 truncate">{{ optional($c->propiedad)->alias ?? '—' }}</div>
                    <div class="text-xs text-gray-500 line-clamp-2">{{ $c->domicilio_inmueble ?: optional($c->propiedad)->domicilio ?: '—' }}</div>
                </td>
                <td class="px-4 py-3 align-top">
                    <div>{{ $c->fecha_inicio ? \Illuminate\Support\Carbon::parse($c->fecha_inicio)->format('Y-m-d') : '—' }}</div>
                    <div class="text-xs text-gray-500">
                        al {{ $c->fecha_fin ? \Carbon\Carbon::parse($c->fecha_fin)->format('Y-m-d') : '—' }}
                    </div>
                    @if($alerta)
                        <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-500 text-white">
                            Por vencer
                        </span>
                    @endif
                </td>
                <td class="px-4 py-3 align-top">
                    {{ $c->monto_mensual !== null ? '$'.number_format($c->monto_mensual, 2) : '—' }}
                </td>
                <td class="px-4 py-3 align-top text-right sticky right-0 bg-white shadow-[-8px_0_12px_-12px_rgba(0,0,0,0.35)]">
                    @if(!empty($c->urldoc))
                        <a href="{{ $c->urldoc }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline">Ver documento</a>
                    @else
                        <span class="text-gray-400">Sin documento</span>
                    @endif
                </td>
                </tr>
            @empty
                <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">No hay contratos que coincidan con la búsqueda.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    <div class="mt-4">
      {{ $contratos->onEachSide(1)->links() }}
    </div>
  </div>
</x-app-layout>
