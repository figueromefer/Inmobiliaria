@php
  $editing = isset($movimiento);
  $field = fn (string $name, $default = null) => old($name, $editing ? data_get($movimiento, $name) : $default);
@endphp

<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
      {{ $editing ? __('Editar movimiento').' '.($movimiento->folio ?? '') : __('Nuevo movimiento') }}
    </h2>
  </x-slot>

  <div class="max-w-3xl mx-auto p-4">
    @if ($errors->any())
      <div class="mb-4 p-2 bg-red-100 border text-red-800 rounded">
        <ul class="list-disc ml-6">
          @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ $editing ? route('movimientos.update', $movimiento) : route('movimientos.store') }}" enctype="multipart/form-data" class="grid gap-4">
      @csrf
      @if($editing)
        @method('PUT')
      @endif

      {{-- Tipo de asignación --}}
      <div>
        <label class="block text-sm font-medium">Asignar movimiento a</label>
        <select id="asignado_a_tipo" name="asignado_a_tipo" class="mt-1 w-full border rounded px-3 py-2" required>
          <option value="cliente" @selected($field('asignado_a_tipo', 'cliente') === 'cliente')>Cliente</option>
          <option value="propiedad" @selected($field('asignado_a_tipo', 'cliente') === 'propiedad')>Propiedad</option>
          <option value="inquilino" @selected($field('asignado_a_tipo', 'cliente') === 'inquilino')>Inquilino</option>
        </select>
      </div>

      {{-- Cliente --}}
      <div data-assignment-panel="cliente">
        <label class="block text-sm font-medium">Cliente</label>
        <select id="cliente_id" name="cliente_id" class="js-searchable-select mt-1 w-full border rounded px-3 py-2">
          <option value="">— Selecciona —</option>
          @foreach ($clientes as $c)
            <option value="{{ $c->id }}" @selected($field('cliente_id', $clienteId ?? '') == $c->id)>{{ $c->nombre }}</option>
          @endforeach
        </select>
      </div>

      {{-- Concepto --}}
      <div>
        <label class="block text-sm font-medium">Concepto</label>
        <select name="concepto" class="mt-1 w-full border rounded px-3 py-2" required>
          <option value="">— Selecciona —</option>
          <option value="deposito" @selected($field('concepto') === 'deposito')>Depósito</option>
          <option value="renta" @selected($field('concepto') === 'renta')>Pago de renta</option>
          <option value="gasto" @selected($field('concepto') === 'gasto')>Gasto</option>
          <option value="gasto_cliente" @selected($field('concepto') === 'gasto_cliente')>Gasto cliente</option>
          <option value="pago_cliente" @selected($field('concepto') === 'pago_cliente')>Pago al cliente</option>
          <option value="iguala" @selected($field('concepto') === 'iguala')>Iguala / Comisión de administración</option>

        </select>
      </div>

      {{-- Propiedad --}}
      <div data-assignment-panel="propiedad">
        <label class="block text-sm font-medium">Propiedad</label>
        <select id="propiedad_id" name="propiedad_id" class="js-searchable-select mt-1 w-full border rounded px-3 py-2">
          <option value="">— Selecciona —</option>
          @foreach ($propiedades as $p)
            <option value="{{ $p->id }}" @selected($field('propiedad_id') == $p->id)>
              {{ $p->alias ?: $p->domicilio ?: 'Propiedad #'.$p->id }}{{ $p->cliente ? ' — '.$p->cliente->nombre : '' }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Inquilino --}}
      <div data-assignment-panel="inquilino">
        <label class="block text-sm font-medium">Inquilino</label>
        <select id="inquilino_id" name="inquilino_id" class="js-searchable-select mt-1 w-full border rounded px-3 py-2">
          <option value="">— Selecciona —</option>
          @foreach ($inquilinos as $inquilino)
            <option value="{{ $inquilino->id }}" @selected($field('inquilino_id') == $inquilino->id)>
              {{ $inquilino->nombre }}{{ $inquilino->correo ? ' — '.$inquilino->correo : '' }}{{ $inquilino->telefono ? ' — '.$inquilino->telefono : '' }}
            </option>
          @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">El sistema resolverá propiedad y cliente desde el contrato del inquilino.</p>
      </div>

      {{-- Fecha --}}
      <div>
        <label class="block text-sm font-medium">Fecha</label>
        <input type="date" name="fecha" value="{{ $field('fecha', now()->toDateString()) instanceof \Carbon\CarbonInterface ? $field('fecha')->toDateString() : $field('fecha', now()->toDateString()) }}" class="mt-1 w-full border rounded px-3 py-2" required>
      </div>

      {{-- Estado financiero --}}
      <div>
        <label class="block text-sm font-medium">Estado de pago</label>
        <select id="estado_pago" name="estado_pago" class="mt-1 w-full border rounded px-3 py-2" required>
          <option value="pendiente" @selected($field('estado_pago', 'liquidado') === 'pendiente')>Pendiente</option>
          <option value="liquidado" @selected($field('estado_pago', 'liquidado') === 'liquidado')>Liquidado</option>
          <option value="cancelado" @selected($field('estado_pago', 'liquidado') === 'cancelado')>Cancelado</option>
        </select>
      </div>

      <div id="fecha_liquidacion_wrap">
        <label class="block text-sm font-medium">Fecha de liquidación</label>
        <input id="fecha_liquidacion" type="date" name="fecha_liquidacion" value="{{ $field('fecha_liquidacion') instanceof \Carbon\CarbonInterface ? $field('fecha_liquidacion')->toDateString() : $field('fecha_liquidacion') }}" class="mt-1 w-full border rounded px-3 py-2">
        <p class="text-xs text-gray-500 mt-1">Si se deja vacía en estado liquidado, se usará la fecha del movimiento.</p>
      </div>

      <label class="inline-flex items-center gap-2">
        <input type="hidden" name="afecta_saldo_cliente" value="0">
        <input type="checkbox" name="afecta_saldo_cliente" value="1" class="rounded border-gray-300" @checked((bool) $field('afecta_saldo_cliente', true))>
        <span class="text-sm font-medium">Afecta saldo del cliente</span>
      </label>

      {{-- Importe --}}
      <div>
        <label class="block text-sm font-medium">Importe</label>
        <input type="text" inputmode="decimal" name="importe" value="{{ $field('importe') }}" class="js-money-input mt-1 w-full border rounded px-3 py-2" required>
      </div>

      {{-- Forma de pago --}}
      <div>
        <label class="block text-sm font-medium">Forma de pago</label>
        <select name="forma_pago" class="mt-1 w-full border rounded px-3 py-2" required>
          <option value="">— Selecciona —</option>
          <option value="efectivo" @selected($field('forma_pago') === 'efectivo')>Efectivo</option>
          <option value="transferencia" @selected($field('forma_pago') === 'transferencia')>Transferencia</option>
        </select>
      </div>

      {{-- Notas --}}
      <div>
        <label class="block text-sm font-medium">Notas</label>
        <textarea name="notas" rows="3" class="mt-1 w-full border rounded px-3 py-2">{{ $field('notas') }}</textarea>
      </div>

      {{-- Comprobante --}}
    <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700" for="comprobante">
            Comprobante (opcional)
        </label>
        <input type="file" name="comprobante" id="comprobante"
            class="form-input mt-1 block w-full rounded-md shadow-sm border-gray-300"
            accept="image/jpeg,image/png,application/pdf">
        <p class="mt-1 text-sm text-gray-500">Formatos permitidos: PDF, JPG y PNG. Tamaño máximo: 50 MB.</p>
        @error('comprobante')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

      @if($editing && $movimiento->comprobante)
        <p class="text-sm text-gray-600 -mt-3">
          Comprobante actual:
          <a href="{{ route('movimientos.comprobante', $movimiento) }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline">ver archivo</a>.
          Déjalo vacío para conservarlo.
        </p>
      @endif

      <div class="flex gap-2">
        <button class="border rounded px-4 py-2">{{ $editing ? 'Guardar cambios' : 'Guardar' }}</button>
        <a href="{{ route('movimientos.index') }}" class="border rounded px-4 py-2">Cancelar</a>
      </div>
    </form>
  </div>

  <script>
document.addEventListener('DOMContentLoaded', () => {
  const selAsignado  = document.getElementById('asignado_a_tipo');
  const selCliente   = document.getElementById('cliente_id');
  const selProp      = document.getElementById('propiedad_id');
  const selInquilino = document.getElementById('inquilino_id');
  const selConcepto  = document.querySelector('select[name="concepto"]');
  const selFormaPago = document.querySelector('select[name="forma_pago"]');
  const selEstadoPago = document.getElementById('estado_pago');
  const fechaLiquidacionWrap = document.getElementById('fecha_liquidacion_wrap');
  const fechaLiquidacion = document.getElementById('fecha_liquidacion');

  function setSelectEnabled(select, enabled) {
    if (!select) return;

    if (enabled) {
      select.removeAttribute('disabled');
      if (select.tomselect) select.tomselect.enable();
    } else {
      select.setAttribute('disabled', 'disabled');
      if (select.tomselect) select.tomselect.disable();
    }
  }

  function toggleAssignmentPanels() {
    const selected = selAsignado.value || 'cliente';

    document.querySelectorAll('[data-assignment-panel]').forEach(panel => {
      const visible = panel.dataset.assignmentPanel === selected;
      panel.classList.toggle('hidden', !visible);
    });

    setSelectEnabled(selCliente, selected === 'cliente');
    setSelectEnabled(selProp, selected === 'propiedad');
    setSelectEnabled(selInquilino, selected === 'inquilino');
  }

  function toggleFieldsByConcept(value) {
    const esGastoProp    = (value === 'gasto');

    // Forma de pago: forzar EFECTIVO solo en gasto / gasto_cliente / iguala
    if (esGastoProp || value === 'gasto_cliente' || value === 'iguala') {
        if (selFormaPago) {
        selFormaPago.value = 'efectivo';
        selFormaPago.setAttribute('disabled', 'disabled');
        }
    } else {
        if (selFormaPago) selFormaPago.removeAttribute('disabled');
    }
    }


  selAsignado.addEventListener('change', toggleAssignmentPanels);

  selConcepto.addEventListener('change', e => {
    toggleFieldsByConcept(e.target.value);
  });

  function togglePaymentFields() {
    const liquidado = selEstadoPago.value === 'liquidado';
    fechaLiquidacionWrap.classList.toggle('hidden', !liquidado);

    if (liquidado) {
      fechaLiquidacion.removeAttribute('disabled');
    } else {
      fechaLiquidacion.value = '';
      fechaLiquidacion.setAttribute('disabled', 'disabled');
    }
  }

  selEstadoPago.addEventListener('change', togglePaymentFields);

  // Estado inicial
  toggleAssignmentPanels();
  toggleFieldsByConcept(selConcepto.value);
  togglePaymentFields();
});
</script>

</x-app-layout>
