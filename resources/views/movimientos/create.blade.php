<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
      {{ __('Nuevo movimiento') }}
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

    <form method="POST" action="{{ route('movimientos.store') }}" enctype="multipart/form-data" class="grid gap-4">
      @csrf

      {{-- Cliente --}}
      <div>
        <label class="block text-sm font-medium">Cliente (con contrato activo)</label>
        <select id="cliente_id" name="cliente_id" class="js-searchable-select mt-1 w-full border rounded px-3 py-2" required>
          <option value="">— Selecciona —</option>
          @foreach ($clientesActivos as $c)
            <option value="{{ $c->id }}" @selected(old('cliente_id', $clienteId ?? '') == $c->id)>{{ $c->nombre }}</option>
          @endforeach
        </select>
      </div>

      {{-- Concepto --}}
      <div>
        <label class="block text-sm font-medium">Concepto</label>
        <select name="concepto" class="mt-1 w-full border rounded px-3 py-2" required>
          @php
            $conceptoOld = old('concepto');
          @endphp
          <option value="">— Selecciona —</option>
          <option value="deposito" @selected($conceptoOld==='deposito')>Depósito en garantía</option>
          <option value="renta"    @selected($conceptoOld==='renta')>Pago de renta</option>
          <option value="gasto"    @selected($conceptoOld==='gasto')>Gasto de la propiedad</option>
          <option value="gasto_cliente" @selected(old('concepto')==='gasto_cliente')>Gastos del cliente</option>
          <option value="pago_cliente" @selected(old('concepto')==='pago_cliente')>Pago al cliente</option>

        </select>
      </div>

      {{-- Propiedad (depende de cliente) --}}
      <div>
        <label class="block text-sm font-medium">Propiedad</label>
        <select id="propiedad_id" name="propiedad_id" class="js-searchable-select mt-1 w-full border rounded px-3 py-2" required>
          <option value="">— Selecciona un cliente primero —</option>
          @foreach ($propiedades as $p)
            <option value="{{ $p->id }}" @selected(old('propiedad_id') == $p->id)>{{ $p->alias }}</option>
          @endforeach
        </select>
      </div>

      

      {{-- Fecha --}}
      <div>
        <label class="block text-sm font-medium">Fecha</label>
        <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" class="mt-1 w-full border rounded px-3 py-2" required>
      </div>

      {{-- Importe --}}
      <div>
        <label class="block text-sm font-medium">Importe</label>
        <input type="number" step="0.01" min="0" name="importe" value="{{ old('importe') }}" class="mt-1 w-full border rounded px-3 py-2" required>
      </div>

      {{-- Forma de pago --}}
      <div>
        <label class="block text-sm font-medium">Forma de pago</label>
        <select name="forma_pago" class="mt-1 w-full border rounded px-3 py-2" required>
          @php $fpOld = old('forma_pago'); @endphp
          <option value="">— Selecciona —</option>
          <option value="efectivo"     @selected($fpOld==='efectivo')>Efectivo</option>
          <option value="transferencia"@selected($fpOld==='transferencia')>Transferencia</option>
        </select>
      </div>

      {{-- Notas --}}
      <div>
        <label class="block text-sm font-medium">Notas</label>
        <textarea name="notas" rows="3" class="mt-1 w-full border rounded px-3 py-2">{{ old('notas') }}</textarea>
      </div>

      {{-- Comprobante --}}
    <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700" for="comprobante">
            Comprobante (opcional)
        </label>
        <input type="file" name="comprobante" id="comprobante"
            class="form-input mt-1 block w-full rounded-md shadow-sm border-gray-300"
            accept="image/*,application/pdf">
        @error('comprobante')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

      <div class="flex gap-2">
        <button class="border rounded px-4 py-2">Guardar</button>
        <a href="{{ route('movimientos.index') }}" class="border rounded px-4 py-2">Cancelar</a>
      </div>
    </form>
  </div>

  <script>
document.addEventListener('DOMContentLoaded', () => {
  const selCliente   = document.getElementById('cliente_id');
  const selProp      = document.getElementById('propiedad_id');
  const selConcepto  = document.querySelector('select[name="concepto"]');
  const selFormaPago = document.querySelector('select[name="forma_pago"]');

  async function loadProps(clienteId) {
    if (selProp.tomselect) {
      selProp.tomselect.clear(true);
      selProp.tomselect.clearOptions();
      selProp.tomselect.addOption({ value: '', text: 'Cargando...' });
      selProp.tomselect.refreshOptions(false);
    }
    selProp.innerHTML = '<option value="">Cargando...</option>';
    if (!clienteId) {
      selProp.innerHTML = '<option value="">— Selecciona un cliente primero —</option>';
      if (selProp.tomselect) {
        selProp.tomselect.clearOptions();
        selProp.tomselect.addOption({ value: '', text: '— Selecciona un cliente primero —' });
        selProp.tomselect.refreshOptions(false);
      }
      return;
    }
    try {
      const url = "{{ route('movimientos.propiedadesPorCliente', ['cliente' => 'ID_PLACEHOLDER']) }}".replace('ID_PLACEHOLDER', clienteId);
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      if (!Array.isArray(data) || data.length === 0) {
        selProp.innerHTML = '<option value="">— Sin propiedades para este cliente —</option>';
        if (selProp.tomselect) {
          selProp.tomselect.clearOptions();
          selProp.tomselect.addOption({ value: '', text: '— Sin propiedades para este cliente —' });
          selProp.tomselect.refreshOptions(false);
        }
        return;
      }
      selProp.innerHTML = data.map(p => `<option value="${p.id}">${p.alias ?? ('Propiedad #'+p.id)}</option>`).join('');
      if (selProp.tomselect) {
        selProp.tomselect.clearOptions();
        selProp.tomselect.addOptions(data.map(p => ({ value: String(p.id), text: p.alias ?? ('Propiedad #'+p.id) })));
        selProp.tomselect.refreshOptions(false);
      }
    } catch (e) {
      selProp.innerHTML = '<option value="">Error cargando propiedades</option>';
      if (selProp.tomselect) {
        selProp.tomselect.clearOptions();
        selProp.tomselect.addOption({ value: '', text: 'Error cargando propiedades' });
        selProp.tomselect.refreshOptions(false);
      }
      console.error('propiedades-por-cliente:', e);
    }
  }

  function toggleFieldsByConcept(value) {
    const esGastoCliente = (value === 'gasto_cliente');
    const esPagoCliente  = (value === 'pago_cliente');
    const esGastoProp    = (value === 'gasto');

    // Propiedad: deshabilitar en gasto_cliente y pago_cliente
    if (esGastoCliente || esPagoCliente) {
        selProp.value = '';
        selProp.setAttribute('disabled', 'disabled');
        if (selProp.tomselect) {
          selProp.tomselect.clear(true);
          selProp.tomselect.disable();
        }
    } else {
        selProp.removeAttribute('disabled');
        if (selProp.tomselect) selProp.tomselect.enable();
    }

    // Forma de pago: forzar EFECTIVO solo en gasto / gasto_cliente
    if (esGastoProp || esGastoCliente) {
        if (selFormaPago) {
        selFormaPago.value = 'efectivo';
        selFormaPago.setAttribute('disabled', 'disabled');
        }
    } else {
        if (selFormaPago) selFormaPago.removeAttribute('disabled');
    }
    }


  selCliente.addEventListener('change', e => {
    if (selConcepto.value !== 'gasto_cliente') {
      loadProps(e.target.value);
    }
  });

  selConcepto.addEventListener('change', e => {
    toggleFieldsByConcept(e.target.value);
    if (e.target.value !== 'gasto_cliente' && selCliente.value) {
      loadProps(selCliente.value);
    }
  });

  // Estado inicial
  toggleFieldsByConcept(selConcepto.value);
  const pre = "{{ $clienteId ?? '' }}";
  if (pre && selConcepto.value !== 'gasto_cliente') loadProps(pre);
});
</script>

</x-app-layout>
