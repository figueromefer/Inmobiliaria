<x-app-layout>
<x-slot name="header">
<div class="flex items-center justify-between gap-4">
<div>
<h2 class="font-semibold text-xl text-gray-800 leading-tight">
Resolver contrato pendiente #{{ $pendiente->id }}
</h2>
<p class="text-sm text-gray-500 mt-1">
Conciliación de cliente, propiedad e inquilino antes de crear el contrato definitivo.
</p>
</div>

<a href="{{ route('contratos.pendientes.index') }}"
class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg">
← Volver a pendientes
</a>
</div>
</x-slot>

@php
$mapped = $pendiente->mapped_payload ?? [];
@endphp

<div class="max-w-7xl mx-auto py-8 sm:px-6 lg:px-8 space-y-6">

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

<div class="bg-white rounded-xl shadow border p-6 space-y-4">
<h3 class="font-bold text-lg">Datos recibidos</h3>

<div><strong>Origen:</strong> {{ $pendiente->origen }}</div>
<div><strong>Expediente:</strong> {{ $pendiente->expediente ?: '—' }}</div>
<div><strong>Cliente recibido:</strong> {{ $mapped['nombre_solicitante'] ?? '—' }}</div>
<div><strong>RFC:</strong> {{ $mapped['rfc_solicitante'] ?? '—' }}</div>
<div><strong>Correo cliente:</strong> {{ $mapped['correo_solicitante'] ?? '—' }}</div>
<div><strong>Arrendatario:</strong> {{ $mapped['nombre_complementaria'] ?? '—' }}</div>
<div><strong>Inmueble:</strong> {{ $mapped['domicilio_inmueble_arrendamiento'] ?? '—' }}</div>
<div><strong>Inicio:</strong> {{ $mapped['fecha_inicio_contrato'] ?? '—' }}</div>
<div><strong>Fin:</strong> {{ $mapped['fecha_terminacion_contrato'] ?? '—' }}</div>
</div>

<div class="bg-white rounded-xl shadow border p-6 space-y-5">
<h3 class="font-bold text-lg">Próximo paso</h3>

<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
La siguiente fase implementará aquí el flujo completo de conciliación:
<ul class="list-disc pl-5 mt-3 space-y-1">
<li>Seleccionar cliente existente o crear nuevo.</li>
<li>Seleccionar propiedad existente del cliente o crear nueva.</li>
<li>Seleccionar o crear inquilino.</li>
<li>Crear tareas automáticas para completar información faltante.</li>
<li>Crear contrato definitivo.</li>
</ul>
</div>

<div class="bg-gray-50 border rounded-lg p-4 text-xs overflow-auto">
<pre>{{ json_encode($mapped, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre>
</div>

</div>

</div>

</div>
</x-app-layout>
