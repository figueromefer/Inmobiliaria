<x-app-layout>
  <x-slot name="header">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalle de contrato</h2>
        <p class="mt-1 text-sm text-gray-500">Consulta de sólo lectura.</p>
      </div>
      <a href="{{ route('contratos.index') }}" class="text-sm text-blue-600 underline">Volver a contratos</a>
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
        <span class="inline-flex w-fit rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800">Registro activo</span>
      </div>
      <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div><dt class="text-sm text-gray-500">Origen</dt><dd class="mt-1 font-medium text-gray-900">{{ $contrato->origen === 'justicia_alternativa' ? 'Justicia Alternativa' : 'Privado' }}</dd></div>
        <div><dt class="text-sm text-gray-500">Fecha de alta</dt><dd class="mt-1 text-gray-900">{{ $fecha($contrato->fecha, 'd/m/Y H:i') }}</dd></div>
        <div><dt class="text-sm text-gray-500">Creado</dt><dd class="mt-1 text-gray-900">{{ $fecha($contrato->created_at, 'd/m/Y H:i') }}</dd></div>
        <div><dt class="text-sm text-gray-500">Actualizado</dt><dd class="mt-1 text-gray-900">{{ $fecha($contrato->updated_at, 'd/m/Y H:i') }}</dd></div>
      </dl>
    </section>

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
          @if($contrato->urldoc)
            <a href="{{ $contrato->urldoc }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-blue-600 underline">Abrir documento del contrato</a>
          @else
            <p class="mt-2 text-sm text-gray-500">Sin documento asociado.</p>
          @endif
          <p class="mt-3 text-sm text-gray-500">No existe una relación directa de documentos con este contrato.</p>
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
  </div>
</x-app-layout>
