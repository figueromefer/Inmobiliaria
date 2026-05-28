<x-app-layout>
<x-slot name="header">
<h2 class="font-semibold text-xl text-gray-800 leading-tight">
Preview — Justicia Alternativa — {{ $expediente }}
</h2>
</x-slot>

<div class="max-w-7xl mx-auto py-8 sm:px-6 lg:px-8 space-y-6">

<form method="POST" action="{{ route('contratos.justicia-alternativa.importar') }}">
@csrf
<input type="hidden" name="expediente" value="{{ $expediente }}">

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

<div class="bg-white rounded-xl shadow border p-6 space-y-4">
<h3 class="font-bold text-lg">Datos del contrato</h3>

<div><strong>Expediente:</strong> {{ $mapped['expediente'] ?? '—' }}</div>
<div><strong>Solicitante:</strong> {{ $mapped['nombre_solicitante'] ?? '—' }}</div>
<div><strong>Arrendatario:</strong> {{ $mapped['nombre_complementaria'] ?? '—' }}</div>
<div><strong>Inmueble:</strong> {{ $mapped['domicilio_inmueble_arrendamiento'] ?? '—' }}</div>
<div><strong>Inicio:</strong> {{ $mapped['fecha_inicio_contrato'] ?? '—' }}</div>
<div><strong>Fin:</strong> {{ $mapped['fecha_terminacion_contrato'] ?? '—' }}</div>
<div><strong>Renta mensual:</strong> {{ $mapped['monto_mensual'] ?? '—' }}</div>
<div><strong>Depósito:</strong> {{ $mapped['monto_deposito'] ?? '—' }}</div>
<div><strong>Días pago:</strong> {{ $mapped['dias_pago'] ?? '—' }}</div>
</div>

<div class="bg-white rounded-xl shadow border p-6 space-y-5">
<h3 class="font-bold text-lg">Vinculación</h3>

<div>
<label class="block text-sm font-semibold mb-1">Cliente *</label>
<select name="fk_cliente" required class="w-full rounded-lg border-gray-300 shadow-sm">
<option value="">Seleccionar cliente…</option>
@foreach($clientes as $cliente)
<option value="{{ $cliente->pk_cliente }}"
    @selected(optional($matches['cliente'])->pk_cliente === $cliente->pk_cliente)>
    {{ $cliente->nombre }}
</option>
@endforeach
</select>
</div>

<div>
<label class="block text-sm font-semibold mb-1">Propiedad *</label>
<select name="fk_propiedad" required class="w-full rounded-lg border-gray-300 shadow-sm">
<option value="">Seleccionar propiedad…</option>
@foreach($propiedades as $propiedad)
<option value="{{ $propiedad->pk_propiedad }}"
    @selected(optional($matches['propiedad'])->pk_propiedad === $propiedad->pk_propiedad)>
    {{ $propiedad->alias ?: $propiedad->domicilio }}
</option>
@endforeach
</select>
</div>

<div>
<label class="block text-sm font-semibold mb-1">Inquilino existente (opcional)</label>
<select name="inquilino_id" class="w-full rounded-lg border-gray-300 shadow-sm">
<option value="">Crear/vincular automáticamente</option>
@foreach($inquilinos as $inquilino)
<option value="{{ $inquilino->id }}"
    @selected(optional($matches['inquilino'])->id === $inquilino->id)>
    {{ $inquilino->nombre }}
</option>
@endforeach
</select>
<p class="text-xs text-gray-500 mt-2">
Si no seleccionas uno, el sistema intentará crearlo usando los datos del expediente.
</p>
</div>

</div>

</div>

<div class="flex justify-between gap-4">
<a href="{{ route('contratos.justicia-alternativa') }}"
class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg">
← Volver
</a>

<button type="submit"
class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-6 rounded-lg">
Confirmar e importar contrato
</button>
</div>

</form>

</div>
</x-app-layout>
