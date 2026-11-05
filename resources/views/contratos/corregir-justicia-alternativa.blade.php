<x-app-layout>
  <x-slot name="header">
    <div>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Corregir datos administrativos</h2>
      <p class="mt-1 text-sm text-gray-500">Contrato de Justicia Alternativa #{{ $contrato->id }} · {{ $contrato->expediente_justicia_alternativa ?: 'sin expediente' }}</p>
    </div>
  </x-slot>

  <div class="mx-auto mt-6 max-w-4xl space-y-6 px-4 pb-8">
    <section class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
      <strong>Corrección administrativa posterior a importación.</strong>
      Esta corrección no modifica el expediente ni la información original recibida de Justicia Alternativa.
    </section>

    @if($historicMovementCount > 0)
      <section class="rounded-lg border border-orange-300 bg-orange-50 p-4 text-sm text-orange-900">
        <strong>Advertencia:</strong> existen {{ $historicMovementCount }} movimiento(s) histórico(s) de renta o iguala asociado(s) o dentro de la vigencia del contrato. Guardar esta corrección no los recalcula ni los modifica. Usa el flujo explícito de recalcular igualas si corresponde.
      </section>
    @endif

    <form method="POST" action="{{ route('contratos.justicia-alternativa.correccion.update', $contrato) }}" class="space-y-6 rounded-lg border bg-white p-6 shadow-sm">
      @csrf
      @method('PUT')
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-sm font-medium text-gray-700">Fecha de inicio
          <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio', $contrato->fecha_inicio?->toDateString()) }}" class="mt-1 block w-full rounded border-gray-300">
          @error('fecha_inicio') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm font-medium text-gray-700">Fecha de fin
          <input type="date" name="fecha_fin" value="{{ old('fecha_fin', $contrato->fecha_fin?->toDateString()) }}" class="mt-1 block w-full rounded border-gray-300">
          @error('fecha_fin') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm font-medium text-gray-700">Día de pago (1–31)
          <input type="number" min="1" max="31" name="dias_pago" value="{{ old('dias_pago', $contrato->dias_pago) }}" class="mt-1 block w-full rounded border-gray-300">
          @error('dias_pago') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm font-medium text-gray-700">Renta mensual
          <input type="number" min="0" step="0.01" name="monto_mensual" value="{{ old('monto_mensual', $contrato->monto_mensual) }}" class="mt-1 block w-full rounded border-gray-300">
          @error('monto_mensual') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm font-medium text-gray-700">Monto total
          <input type="number" min="0" step="0.01" name="monto_total" value="{{ old('monto_total', $contrato->monto_total) }}" class="mt-1 block w-full rounded border-gray-300">
          @error('monto_total') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm font-medium text-gray-700">Depósito
          <input type="number" min="0" step="0.01" name="monto_deposito" value="{{ old('monto_deposito', $contrato->monto_deposito) }}" class="mt-1 block w-full rounded border-gray-300">
          @error('monto_deposito') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm font-medium text-gray-700">Comisión mensual ({{ $contrato->monthlyCommissionDisplay() }})
          <input type="number" min="0.01" step="0.01" name="comision_mensual" value="{{ old('comision_mensual', $contrato->comision_mensual) }}" class="mt-1 block w-full rounded border-gray-300">
          @error('comision_mensual') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm font-medium text-gray-700 sm:col-span-2">Domicilio del inmueble
          <textarea name="domicilio_inmueble" rows="3" class="mt-1 block w-full rounded border-gray-300">{{ old('domicilio_inmueble', $contrato->domicilio_inmueble) }}</textarea>
          @error('domicilio_inmueble') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
      </div>
      <label class="block text-sm font-medium text-gray-700">Motivo de corrección
        <textarea name="motivo" rows="3" required class="mt-1 block w-full rounded border-gray-300" placeholder="Ej. Error de captura manual al vincular renovación JA.">{{ old('motivo') }}</textarea>
        @error('motivo') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
      </label>
      <div class="flex flex-wrap gap-3">
        <button type="submit" class="rounded bg-teal-700 px-4 py-2 font-bold text-white hover:bg-teal-800">Guardar corrección</button>
        <a href="{{ route('contratos.show', $contrato) }}" class="rounded bg-gray-500 px-4 py-2 font-bold text-white hover:bg-gray-700">Cancelar</a>
      </div>
    </form>
  </div>
</x-app-layout>
