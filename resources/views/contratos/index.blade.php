<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
      {{ __('Contratos') }}
    </h2>
  </x-slot>

  <div class="max-w-7xl mx-auto p-4">
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
            <select id="solicitante" name="solicitante" class="mt-1 w-full border rounded px-3 py-2">
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
        <button type="submit" class="inline-flex items-center border rounded px-4 py-2">
          Aplicar
        </button>
        <a href="{{ route('contratos.index') }}" class="inline-flex items-center border rounded px-4 py-2">
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
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-2"><a class="underline" href="{{ sortUrlC('id',$sort,$dir) }}">ID</a></th>
                <th class="text-left px-4 py-2">Tipo Solicitante</th>
                {{-- antes era solicitante (texto); ahora ordenamos por nombre del cliente --}}
                <th class="text-left px-4 py-2"><a class="underline" href="{{ sortUrlC('cliente',$sort,$dir) }}">Cliente</a></th>
                <th class="text-left px-4 py-2">Inquilino</th>
                <th class="text-left px-4 py-2">Domicilio inmueble</th>
                <th class="text-left px-4 py-2"><a class="underline" href="{{ sortUrlC('fecha',$sort,$dir) }}">Alta</a></th>
                <th class="text-left px-4 py-2"><a class="underline" href="{{ sortUrlC('fecha_inicio',$sort,$dir) }}">Inicio</a></th>
                <th class="text-left px-4 py-2"><a class="underline" href="{{ sortUrlC('fecha_fin',$sort,$dir) }}">Fin</a></th>
                <th class="text-left px-4 py-2"><a class="underline" href="{{ sortUrlC('comision_renta',$sort,$dir) }}">Comisión de renta</a></th>
                <th class="text-left px-4 py-2"><a class="underline" href="{{ sortUrlC('comision_mensual',$sort,$dir) }}">% Comisión mensual</a></th>
                <th class="text-left px-4 py-2"><a class="underline" href="{{ sortUrlC('monto_mensual',$sort,$dir) }}">Monto mensual</a></th>
                <th class="text-left px-4 py-2">Documento</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($contratos as $c)
                <tr class="border-b hover:bg-gray-50">
                <td class="px-4 py-2">{{ $c->id }}</td>
                <td class="px-4 py-2">{{ $c->tipo_solicitante ?? '—' }}</td>

                {{-- Cliente por relación (fk_cliente) --}}
                <td class="px-4 py-2">{{ optional($c->cliente)->nombre ?? '—' }}</td>

                <td class="px-4 py-2">{{ optional($c->inquilino)->nombre ?? '—' }}</td>
                <td class="px-4 py-2">{{ $c->domicilio_inmueble ?? '—' }}</td>

                <td class="px-4 py-2">
                    {{ $c->fecha ? \Illuminate\Support\Carbon::parse($c->fecha)->format('Y-m-d H:i') : '—' }}
                </td>
                <td class="px-4 py-2">
                    {{ $c->fecha_inicio ? \Illuminate\Support\Carbon::parse($c->fecha_inicio)->format('Y-m-d') : '—' }}
                </td>
                <td class="px-4 py-2">
                    {{ $c->fecha_fin ? \Illuminate\Support\Carbon::parse($c->fecha_fin)->format('Y-m-d') : '—' }}
                </td>

                <td class="px-4 py-2">
                    {{ $c->comision_renta !== null ? number_format($c->comision_renta, 2) : '—' }}
                </td>

                {{-- Si comision_mensual está guardada como fracción (0.10), muéstrala como 10.00 % --}}
                <td class="px-4 py-2">
                    @if(!is_null($c->comision_mensual))
                    {{ number_format($c->comision_mensual * 100, 2) }} %
                    @else
                    —
                    @endif
                </td>

                <td class="px-4 py-2">
                    {{ $c->monto_mensual !== null ? number_format($c->monto_mensual, 2) : '—' }}
                </td>

                {{-- Documento: la columna ahora es edit_url (antes urldoc) --}}
                <td class="px-4 py-2">
                    @if(!empty($c->urldoc))
                    <a href="{{ $c->urldoc }}" target="_blank" class="text-blue-600 underline">Abrir</a>
                    @else
                    —
                    @endif
                </td>
                </tr>
            @empty
                <tr>
                <td colspan="12" class="px-4 py-8 text-center text-gray-500">No hay contratos que coincidan con la búsqueda.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
        </div>


    {{-- Paginación --}}
    <div class="mt-4">
      {{ $contratos->onEachSide(1)->links() }}
      {{-- Si tu proyecto no usa Tailwind para paginación:
      {{ $contratos->withQueryString()->links('pagination::simple-default') }} --}}
    </div>
  </div>
</x-app-layout>