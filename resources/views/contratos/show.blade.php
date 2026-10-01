<x-app-layout>
  <style>
    .contract-detail-action { align-items:center; background:#155e75; border:1px solid #0f4c5c; border-radius:.4rem; color:#fff; display:inline-flex; font-weight:700; min-height:2.4rem; padding:.5rem .8rem; text-decoration:none; }
    .contract-detail-action:hover,.contract-detail-action:focus { background:#0f4c5c; color:#fff; }
    .contract-detail-action-secondary { background:#475569; border-color:#334155; }
  </style>
  <x-slot name="header">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalle de contrato</h2>
        <p class="mt-1 text-sm text-gray-500">Consulta de sólo lectura.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        @if($contrato->document_url)<a href="{{ $contrato->document_url }}" target="_blank" rel="noopener noreferrer" class="contract-detail-action contract-detail-action-secondary">Ver documento</a>@endif
        @if($contrato->drive_folder_url)<a href="{{ $contrato->drive_folder_url }}" target="_blank" rel="noopener noreferrer" class="contract-detail-action">Carpeta Drive</a>@endif
        @if($contrato->origen !== 'justicia_alternativa' && auth()->user()?->can('manage-records'))<form method="POST" action="{{ route('contratos.revision.start', $contrato) }}">@csrf<button class="contract-detail-action" type="submit">Editar contrato</button></form>@endif
        @if($contrato->origen !== 'justicia_alternativa' && auth()->user()?->can('manage-records'))<form method="POST" action="{{ route('contratos.renew', $contrato) }}">@csrf<button class="contract-detail-action" type="submit">Renovar contrato</button></form>@endif
        <a href="{{ route('contratos.index') }}" class="inline-flex items-center bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">Volver a contratos</a>
      </div>
    </div>
  </x-slot>

  @php
    $dato = static fn ($valor) => filled($valor) ? $valor : '—';
    $moneda = static fn ($valor) => $valor === null ? '—' : '$'.number_format((float) $valor, 2);
    $fecha = static fn ($valor, $formato = 'd/m/Y') => $valor ? $valor->format($formato) : '—';
  @endphp

  <div class="max-w-7xl mx-auto mt-6 space-y-6 px-4 pb-8 lg:px-8">
    <section class="rounded-lg border bg-white p-6 shadow-sm">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p class="text-sm font-medium text-gray-500">Folio o identificador</p>
          <p class="text-2xl font-semibold text-gray-900">{{ $contrato->expediente_justicia_alternativa ?: '#'.$contrato->id }}</p>
        </div>
        <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">Contrato registrado</span>
      </div>
      <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div><dt class="text-sm text-gray-500">Origen</dt><dd class="mt-1 font-medium text-gray-900">{{ $contrato->origen === 'justicia_alternativa' ? 'Justicia Alternativa' : 'Privado' }}</dd></div>
        <div><dt class="text-sm text-gray-500">Fecha de alta</dt><dd class="mt-1 text-gray-900">{{ $fecha($contrato->fecha, 'd/m/Y H:i') }}</dd></div>
        <div><dt class="text-sm text-gray-500">Creado</dt><dd class="mt-1 text-gray-900">{{ $fecha($contrato->created_at, 'd/m/Y H:i') }}</dd></div>
        <div><dt class="text-sm text-gray-500">Actualizado</dt><dd class="mt-1 text-gray-900">{{ $fecha($contrato->updated_at, 'd/m/Y H:i') }}</dd></div>
      </dl>
    </section>

    @if($contrato->previousContract || $contrato->renewals->isNotEmpty())
    <section class="rounded-lg border bg-white p-6 shadow-sm">
      <h3 class="text-lg font-semibold text-gray-900">Renovaciones</h3>
      @if($contrato->previousContract)<p class="mt-3 text-sm text-gray-700">Renovación de contrato <a class="text-blue-600 underline" href="{{ route('contratos.show', $contrato->previousContract) }}">#{{ $contrato->previousContract->id }}</a>.</p>@endif
      @if($contrato->renewals->isNotEmpty())<ul class="mt-3 space-y-2 text-sm">@foreach($contrato->renewals as $renewal)<li><a class="text-blue-600 underline" href="{{ route('contratos.show', $renewal) }}">Contrato #{{ $renewal->id }}</a> · {{ $renewal->fecha_inicio?->format('d/m/Y') ?: '—' }} a {{ $renewal->fecha_fin?->format('d/m/Y') ?: '—' }}</li>@endforeach</ul>@endif
    </section>
    @endif

    <section class="rounded-lg border bg-white p-6 shadow-sm">
      <h3 class="text-lg font-semibold text-gray-900">Vigencia y condiciones</h3>
      <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div><dt class="text-sm text-gray-500">Inicio de vigencia</dt><dd class="mt-1 text-gray-900">{{ $fecha($contrato->fecha_inicio) }}</dd></div>
        <div><dt class="text-sm text-gray-500">Fin de vigencia</dt><dd class="mt-1 text-gray-900">{{ $fecha($contrato->fecha_fin) }}</dd></div>
        <div><dt class="text-sm text-gray-500">Días de pago</dt><dd class="mt-1 text-gray-900">{{ $dato($contrato->dias_pago) }}</dd></div>
        <div><dt class="text-sm text-gray-500">Renta mensual</dt><dd class="mt-1 font-medium text-gray-900">{{ $moneda($contrato->monto_mensual) }}</dd></div>
        <div><dt class="text-sm text-gray-500">Depósito</dt><dd class="mt-1 text-gray-900">{{ $moneda($contrato->monto_deposito) }}</dd></div>
        <div><dt class="text-sm text-gray-500">Monto total</dt><dd class="mt-1 text-gray-900">{{ $moneda($contrato->monto_total) }}</dd></div>
        <div><dt class="text-sm text-gray-500">Comisión por renta</dt><dd class="mt-1 text-gray-900">{{ $moneda($contrato->comision_renta) }}</dd></div>
        <div><dt class="text-sm text-gray-500">Comisión mensual</dt><dd class="mt-1 text-gray-900">{{ $moneda($contrato->comision_mensual) }}</dd></div>
        <div><dt class="text-sm text-gray-500">Domicilio registrado</dt><dd class="mt-1 text-gray-900">{{ $dato($contrato->domicilio_inmueble) }}</dd></div>
      </dl>
    </section>

    <section class="rounded-lg border bg-white p-6 shadow-sm">
      <h3 class="text-lg font-semibold text-gray-900">Partes relacionadas</h3>
      <div class="mt-4 grid gap-6 lg:grid-cols-3">
        <div class="rounded border p-4">
          <h4 class="font-semibold text-gray-900">Cliente / propietario</h4>
          @if($contrato->cliente)
            <dl class="mt-3 space-y-2 text-sm">
              <div><dt class="text-gray-500">Nombre</dt><dd>{{ $dato($contrato->cliente->nombre) }}</dd></div>
              <div><dt class="text-gray-500">RFC</dt><dd>{{ $dato($contrato->cliente->rfc) }}</dd></div>
              <div><dt class="text-gray-500">Correo</dt><dd>{{ $dato($contrato->cliente->correo) }}</dd></div>
              <div><dt class="text-gray-500">Teléfono</dt><dd>{{ $dato($contrato->cliente->celular ?: $contrato->cliente->fijo) }}</dd></div>
            </dl>
          @else
            <p class="mt-3 text-sm text-gray-500">Sin cliente registrado.</p>
          @endif
        </div>
        <div class="rounded border p-4">
          <h4 class="font-semibold text-gray-900">Propiedad</h4>
          @if($contrato->propiedad)
            <dl class="mt-3 space-y-2 text-sm">
              <div><dt class="text-gray-500">Alias</dt><dd>{{ $dato($contrato->propiedad->alias) }}</dd></div>
              <div><dt class="text-gray-500">Domicilio</dt><dd>{{ $dato($contrato->propiedad->domicilio) }}</dd></div>
            </dl>
          @else
            <p class="mt-3 text-sm text-gray-500">Sin propiedad registrada.</p>
          @endif
        </div>
        <div class="rounded border p-4">
          <h4 class="font-semibold text-gray-900">Inquilino</h4>
          @if($contrato->inquilino)
            <dl class="mt-3 space-y-2 text-sm">
              <div><dt class="text-gray-500">Nombre</dt><dd>{{ $dato($contrato->inquilino->nombre) }}</dd></div>
              <div><dt class="text-gray-500">Correo</dt><dd>{{ $dato($contrato->inquilino->correo) }}</dd></div>
              <div><dt class="text-gray-500">Teléfono</dt><dd>{{ $dato($contrato->inquilino->telefono) }}</dd></div>
              <div><dt class="text-gray-500">Nacionalidad</dt><dd>{{ $dato($contrato->inquilino->nacionalidad) }}</dd></div>
            </dl>
          @else
            <p class="mt-3 text-sm text-gray-500">Sin inquilino registrado.</p>
          @endif
        </div>
      </div>
    </section>

    <section class="rounded-lg border bg-white p-6 shadow-sm">
      <h3 class="text-lg font-semibold text-gray-900">Documentos y seguimiento</h3>
      <div class="mt-4 grid gap-6 lg:grid-cols-2">
        <div>
          <h4 class="font-medium text-gray-900">Documento asociado</h4>
          @if($contrato->document_url)
            <a href="{{ $contrato->document_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-blue-600 underline">Abrir documento del contrato</a>
          @else
            <p class="mt-2 text-sm text-gray-500">Sin documento asociado.</p>
          @endif
          @if($contrato->drive_folder_url)<a href="{{ $contrato->drive_folder_url }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block text-blue-600 underline">Abrir carpeta Drive</a>@else<p class="mt-3 text-sm text-gray-500">Sin carpeta Drive.</p>@endif
        </div>
        <div>
          <h4 class="font-medium text-gray-900">Movimientos</h4>
          <p class="mt-2 text-sm text-gray-500">Los movimientos se consultan desde el módulo Movimientos.</p>
          @if($contrato->pendientes->isNotEmpty())
            <h4 class="mt-4 font-medium text-gray-900">Registros de importación vinculados</h4>
            <ul class="mt-2 space-y-1 text-sm text-gray-700">
              @foreach($contrato->pendientes as $pendiente)
                <li>{{ $dato($pendiente->expediente ?: $pendiente->external_id) }} · {{ $dato($pendiente->estado) }}</li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>
    </section>

    <section class="rounded-lg border bg-white p-6 shadow-sm">
      <h3 class="text-lg font-semibold text-gray-900">Versiones del contrato</h3>
      @if($versions->isEmpty())
        <p class="mt-3 text-sm text-gray-500">Este contrato no cuenta con versiones documentales registradas.</p>
      @else
        <ul class="mt-4 divide-y rounded border">
          @foreach($versions as $version)
            <li class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
              <div><p class="font-medium text-gray-900">Versión {{ $loop->count - $loop->index }} @if($version->id === $contrato->contract_document_version_id)<span class="ml-2 rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-800">Actual</span>@else<span class="ml-2 rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-700">Anterior</span>@endif</p><p class="mt-1 text-sm text-gray-500">{{ $version->created_at?->format('d/m/Y H:i') }} · {{ $version->createdBy?->name ?: 'Sistema' }} · {{ $version->status === 'generated' ? 'Documento generado' : 'Documento pendiente' }}</p></div>
              @if($version->url)<a href="{{ $version->url }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline">Ver documento</a>@endif
            </li>
          @endforeach
        </ul>
      @endif
    </section>
  </div>
</x-app-layout>
