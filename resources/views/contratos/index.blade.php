<x-app-layout>
  <style>
    .contracts-primary-action, .contracts-secondary-action, .contracts-badge { font-family: inherit; font-weight: 700; text-decoration: none; }
    .contracts-primary-action { align-items: center; background: #155e75; border: 1px solid #0f4c5c; border-radius: .5rem; color: #fff; display: inline-flex; min-height: 2.5rem; padding: .6rem 1rem; }
    .contracts-primary-action:hover, .contracts-primary-action:focus { background: #0f4c5c; color: #fff; }
    .contracts-ja-action { background: #5b21b6; border-color: #4c1d95; }
    .contracts-ja-action:hover, .contracts-ja-action:focus { background: #4c1d95; }
    .contracts-secondary-action { align-items: center; background: #fff; border: 1px solid #64748b; border-radius: .5rem; color: #334155; display: inline-flex; min-height: 2.5rem; padding: .6rem 1rem; }
    .contracts-secondary-action:hover, .contracts-secondary-action:focus { background: #f1f5f9; color: #0f172a; }
    .contracts-badge { align-items: center; border: 1px solid; border-radius: 9999px; display: inline-flex; font-size: .75rem; gap: .35rem; line-height: 1; padding: .4rem .6rem; white-space: nowrap; }
    .contracts-badge-private { background: #ecfeff; border-color: #0e7490; color: #155e75; }
    .contracts-badge-ja { background: #f5f3ff; border-color: #7c3aed; color: #5b21b6; }
    .contracts-badge-dot { font-size: .8rem; line-height: 1; }
    .contracts-flash-success { background: #ecfdf5; border: 1px solid #047857; border-left: 4px solid #047857; border-radius: .4rem; color: #065f46; font-weight: 700; margin-bottom: 1rem; padding: .8rem 1rem; }
    .contracts-row-action { border: 1px solid #64748b; border-radius: .35rem; color: #1e3a5f; font-size: .75rem; font-weight: 700; padding: .35rem .55rem; text-decoration: none; }
    .contracts-row-action:hover, .contracts-row-action:focus { background: #eef6f8; color: #0f4c5c; }
    .contracts-vigencia { border:1px solid; border-radius:9999px; display:inline-flex; font-size:.75rem; font-weight:700; margin-top:.35rem; padding:.25rem .5rem; }
    .contracts-vigencia-vencido { background:#fef2f2; border-color:#b91c1c; color:#991b1b; }
    .contracts-vigencia-proximo { background:#eff6ff; border-color:#2563eb; color:#1d4ed8; }
    .contracts-vigencia-por_vencer { background:#fefce8; border-color:#ca8a04; color:#854d0e; }
    .contracts-vigencia-vigente { background:#ecfdf5; border-color:#059669; color:#047857; }
    .contracts-vigencia-sin_vigencia { background:#f3f4f6; border-color:#6b7280; color:#374151; }
  </style>
  <x-slot name="header">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          {{ __('Contratos') }}
        </h2>
        <p class="text-sm text-gray-500 mt-1">Contratos privados y de Justicia Alternativa.</p>
      </div>

      @if(auth()->user()?->can('create-private-contracts') || auth()->user()?->can('import-justice-alternative-contracts') || auth()->user()?->can('delete-anything'))
        <div class="flex flex-wrap items-center gap-2">
          @can('create-private-contracts')
            <a href="{{ route('contratos.privados.create') }}" class="contracts-primary-action">
              + Nuevo contrato privado
            </a>
            <a href="{{ route('contratos.borradores.index') }}" class="contracts-secondary-action">
              Borradores internos
            </a>
          @endcan

          @can('import-justice-alternative-contracts')
            <a href="{{ route('contratos.justicia-alternativa') }}" class="contracts-primary-action contracts-ja-action">
              Traer contrato de Justicia Alternativa
            </a>

            <a href="{{ route('contratos.pendientes.index') }}"
               class="contracts-secondary-action">
              {{ $pendientesCount ?? 0 }} Contratos pendientes
            </a>
          @endcan

          @can('delete-anything')
            <a href="{{ route('archivados.index') }}" class="contracts-secondary-action">
              Ver archivados
            </a>
          @endcan
        </div>
      @endif
    </div>
  </x-slot>

  <div class="max-w-7xl mx-auto mt-6 bg-white lg:px-8 py-6">
    @if(session('success'))<p class="contracts-flash-success" role="status">{{ session('success') }}</p>@endif
      
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
        <label for="tipo" class="block text-sm font-medium">Tipo de contrato</label>
        <select id="tipo" name="tipo" class="mt-1 w-full border rounded px-3 py-2">
          <option value="" @selected($tipo === '')>Todos</option>
          <option value="privado" @selected($tipo === 'privado')>Contrato privado</option>
          <option value="justicia_alternativa" @selected($tipo === 'justicia_alternativa')>Justicia alternativa</option>
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
      $sortUrlC = function ($col, $sort, $dir) {
        $next = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $col, 'dir' => $next, 'page' => 1]);
      };
      $sort = $sort ?? 'fecha';
      $dir  = $dir  ?? 'desc';
    @endphp

    <p class="mb-3 text-sm text-gray-600">Mostrando contratos privados y de Justicia Alternativa.</p>

    {{-- Tabla --}}
    <div class="overflow-x-auto bg-white border rounded">
        <table class="min-w-full text-sm lg:table-fixed">
            <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-3 w-32">Acciones</th>
                <th class="text-left px-4 py-3 w-32"><a class="underline" href="{{ $sortUrlC('id',$sort,$dir) }}">Expediente</a></th>
                <th class="text-left px-4 py-3 w-40">Tipo</th>
                <th class="text-left px-4 py-3 w-48"><a class="underline" href="{{ $sortUrlC('cliente',$sort,$dir) }}">Cliente</a></th>
                <th class="text-left px-4 py-3 w-48">Arrendatario</th>
                <th class="text-left px-4 py-3">Propiedad / Domicilio</th>
                <th class="text-left px-4 py-3 w-48">Vigencia</th>
                <th class="text-left px-4 py-3 w-36"><a class="underline" href="{{ $sortUrlC('monto_mensual',$sort,$dir) }}">Monto mensual</a></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($contratos as $c)
                @php($vigencia = $c->vigencia_estado)
                <tr class="border-b hover:bg-gray-50">
                <td class="px-4 py-3 align-top">
                    <div class="flex flex-col items-start gap-2">
                        <a href="{{ route('contratos.show', $c) }}" class="contracts-row-action">
                            Ver
                        </a>
                        @if($c->document_url)
                            <a href="{{ $c->document_url }}" target="_blank" rel="noopener noreferrer" class="contracts-row-action">Ver documento</a>
                        @else
                            <span class="text-gray-400">Sin documento</span>
                        @endif
                        @if($c->drive_folder_url)
                            <a href="{{ $c->drive_folder_url }}" target="_blank" rel="noopener noreferrer" class="contracts-row-action">Carpeta Drive</a>
                        @else
                            <span class="text-gray-400">Sin carpeta Drive</span>
                        @endif
                        @if($c->origen !== 'justicia_alternativa' && auth()->user()?->can('manage-records'))
                            <form method="POST" action="{{ route('contratos.revision.start', $c) }}">@csrf<button class="contracts-row-action" type="submit">Editar contrato</button></form>
                            <form method="POST" action="{{ route('contratos.renew', $c) }}">@csrf<button class="contracts-row-action" type="submit">Renovar contrato</button></form>
                        @endif
                    </div>
                </td>
                <td class="px-4 py-3 align-top">
                    <a href="{{ route('contratos.show', $c) }}" class="font-semibold text-blue-600 underline">{{ $c->expediente_justicia_alternativa ?: '#'.$c->id }}</a>
                </td>
                <td class="px-4 py-3 align-top">
                    @if($c->origen === 'justicia_alternativa')
                        <span class="contracts-badge contracts-badge-ja"><span class="contracts-badge-dot" aria-hidden="true">●</span> Justicia alternativa</span>
                    @else
                        <span class="contracts-badge contracts-badge-private"><span class="contracts-badge-dot" aria-hidden="true">●</span> Contrato privado</span>
                    @endif
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
                    <span class="contracts-vigencia contracts-vigencia-{{ $vigencia['key'] }}">{{ $vigencia['label'] }}</span>
                </td>
                <td class="px-4 py-3 align-top">
                    {{ $c->monto_mensual !== null ? '$'.number_format($c->monto_mensual, 2) : '—' }}
                </td>
                </tr>
            @empty
                <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-500">No hay contratos que coincidan con la búsqueda.</td>
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
