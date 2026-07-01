<x-app-layout>
<x-slot name="header">
<div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
<div>
<h2 class="font-semibold text-xl text-gray-800 leading-tight">
Resolver contrato pendiente #{{ $pendiente->id }}
</h2>
<p class="text-sm text-gray-500 mt-1">
Conciliación de cliente, propiedad e inquilino antes de crear el contrato definitivo.
</p>
</div>

<a href="{{ route('contratos.pendientes.index') }}"
class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">
← Volver a pendientes
</a>
</div>
</x-slot>

@php
$mapped = $pendiente->mapped_payload ?? [];
$clienteSuggestion = $suggestions['cliente'] ?? ['model' => null, 'confidence' => 'ninguna', 'reason' => 'Sin coincidencia'];
$propiedadSuggestion = $suggestions['propiedad'] ?? ['model' => null, 'confidence' => 'ninguna', 'reason' => 'Sin coincidencia'];
$inquilinoSuggestion = $suggestions['inquilino'] ?? ['model' => null, 'confidence' => 'ninguna', 'reason' => 'Sin coincidencia'];
$clienteSuggested = $clienteSuggestion['model'] ?? null;
$propiedadSuggested = $propiedadSuggestion['model'] ?? null;
$inquilinoSuggested = $inquilinoSuggestion['model'] ?? null;
$badgeClass = function ($confidence) {
    return match ($confidence) {
        'alta' => 'bg-green-100 text-green-800 border-green-200',
        'media' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
        default => 'bg-gray-100 text-gray-700 border-gray-200',
    };
};
$confidenceNote = function ($confidence) {
    return match ($confidence) {
        'alta' => 'Coincidencia fuerte. Aun así conviene revisar antes de confirmar.',
        'media' => 'Coincidencia probable, pero requiere revisión manual.',
        default => 'No se encontró coincidencia confiable. Se sugiere crear nuevo o seleccionar manualmente.',
    };
};
@endphp

<div class="max-w-7xl mx-auto py-8 sm:px-6 lg:px-8 space-y-6">

@if ($errors->any())
<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-800">
<p class="font-semibold mb-2">No se pudo resolver el contrato pendiente.</p>
<ul class="list-disc pl-5 space-y-1">
@foreach ($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif

@if (session('error'))
<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-800">
{{ session('error') }}
</div>
@endif

@if (session('success'))
<div class="bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-800">
{{ session('success') }}
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

<div class="bg-white rounded-xl shadow border p-6 space-y-4">
<h3 class="font-bold text-lg">Datos recibidos</h3>

<div><strong>Origen:</strong> {{ str_replace('_', ' ', $pendiente->origen) }}</div>
<div><strong>Expediente:</strong> {{ $pendiente->expediente ?: '—' }}</div>
<div><strong>Cliente recibido:</strong> {{ $mapped['nombre_solicitante'] ?? '—' }}</div>
<div><strong>RFC:</strong> {{ $mapped['rfc_solicitante'] ?? '—' }}</div>
<div><strong>Correo cliente:</strong> {{ $mapped['correo_solicitante'] ?? '—' }}</div>
<div><strong>Teléfono cliente:</strong> {{ $mapped['telefono_solicitante'] ?? '—' }}</div>
<div><strong>Propiedad alias:</strong> {{ $mapped['propiedad_alias'] ?? '—' }}</div>
<div><strong>Arrendatario:</strong> {{ $mapped['nombre_complementaria'] ?? '—' }}</div>
<div><strong>Inmueble:</strong> {{ $mapped['domicilio_inmueble_arrendamiento'] ?? '—' }}</div>
<div><strong>Inicio:</strong> {{ $mapped['fecha_inicio_contrato'] ?? '—' }}</div>
<div><strong>Fin:</strong> {{ $mapped['fecha_terminacion_contrato'] ?? '—' }}</div>
<div><strong>Renta mensual:</strong> {{ isset($mapped['monto_mensual']) ? '$'.number_format($mapped['monto_mensual'], 2) : '—' }}</div>
<div><strong>Depósito:</strong> {{ isset($mapped['monto_deposito']) ? '$'.number_format($mapped['monto_deposito'], 2) : '—' }}</div>
</div>

<form method="POST" action="{{ route('contratos.pendientes.resolver', $pendiente) }}" class="bg-white rounded-xl shadow border p-6 space-y-6">
@csrf

<h3 class="font-bold text-lg">Conciliación</h3>

<section class="space-y-3 border-b pb-5">
<div class="flex items-center justify-between gap-3">
<h4 class="font-semibold">1. Cliente</h4>
<span class="inline-flex items-center px-2 py-1 rounded-full border text-xs font-semibold {{ $badgeClass($clienteSuggestion['confidence']) }}">
{{ ucfirst($clienteSuggestion['confidence']) }} — {{ $clienteSuggestion['reason'] }}
</span>
</div>
<p class="text-xs text-gray-500">{{ $confidenceNote($clienteSuggestion['confidence']) }}</p>
@if($clienteSuggested)
<div class="bg-gray-50 border rounded-lg p-3 text-sm">
<p class="text-gray-700">Cliente sugerido:</p>
<p class="font-semibold">{{ $clienteSuggested->nombre }}</p>
<p class="text-xs text-gray-500">{{ $clienteSuggested->rfc ? 'RFC: '.$clienteSuggested->rfc.' · ' : '' }}{{ $clienteSuggested->correo ?: '' }}</p>
</div>
@endif
<label class="flex items-center gap-2">
<input type="radio" name="cliente_action" value="existing" @checked($clienteSuggested)>
<span>Usar cliente existente</span>
</label>
<label class="flex items-center gap-2">
<input type="radio" name="cliente_action" value="new" @checked(!$clienteSuggested)>
<span>Crear cliente nuevo con los datos recibidos</span>
</label>

<div data-action-panel="cliente" data-visible-when="existing">
<select name="fk_cliente" id="fk_cliente" class="w-full rounded-lg border-gray-300 shadow-sm">
<option value="">Seleccionar cliente…</option>
@foreach($clientes as $cliente)
<option value="{{ $cliente->pk_cliente }}" @selected($clienteSuggested && $clienteSuggested->pk_cliente === $cliente->pk_cliente)>
{{ $cliente->nombre }}{{ $cliente->rfc ? ' — RFC: '.$cliente->rfc : '' }}{{ $cliente->correo ? ' — '.$cliente->correo : '' }}
</option>
@endforeach
</select>
</div>
<p class="text-xs text-gray-500">Si creas cliente nuevo, se generará una tarea para completar su información.</p>
</section>

<section class="space-y-3 border-b pb-5">
<div class="flex items-center justify-between gap-3">
<h4 class="font-semibold">2. Propiedad</h4>
<span class="inline-flex items-center px-2 py-1 rounded-full border text-xs font-semibold {{ $badgeClass($propiedadSuggestion['confidence']) }}">
{{ ucfirst($propiedadSuggestion['confidence']) }} — {{ $propiedadSuggestion['reason'] }}
</span>
</div>
<p class="text-xs text-gray-500">{{ $confidenceNote($propiedadSuggestion['confidence']) }}</p>
@if($propiedadSuggested)
<div class="bg-gray-50 border rounded-lg p-3 text-sm">
<p class="text-gray-700">Propiedad sugerida:</p>
<p class="font-semibold">{{ $propiedadSuggested->alias ?: $propiedadSuggested->domicilio }}</p>
<p class="text-xs text-gray-500">{{ $propiedadSuggested->domicilio ?: '' }}</p>
</div>
@endif
<label class="flex items-center gap-2">
<input type="radio" name="propiedad_action" value="existing" @checked($propiedadSuggested)>
<span>Usar propiedad existente del cliente seleccionado</span>
</label>
<label class="flex items-center gap-2">
<input type="radio" name="propiedad_action" value="new" @checked(!$propiedadSuggested)>
<span>Crear propiedad nueva</span>
</label>

<div data-action-panel="propiedad" data-visible-when="existing">
<select name="fk_propiedad" id="fk_propiedad" class="w-full rounded-lg border-gray-300 shadow-sm">
<option value="">Seleccionar propiedad…</option>
@foreach($propiedades as $propiedad)
<option value="{{ $propiedad->pk_propiedad }}" data-cliente="{{ $propiedad->fk_cliente }}" @selected($propiedadSuggested && $propiedadSuggested->pk_propiedad === $propiedad->pk_propiedad)>
{{ $propiedad->alias ?: $propiedad->domicilio }}{{ $propiedad->domicilio ? ' — '.$propiedad->domicilio : '' }}
</option>
@endforeach
</select>
</div>
<p class="text-xs text-gray-500">Si creas propiedad nueva, quedará marcada como pendiente de completar información.</p>
</section>

<section class="space-y-3 border-b pb-5">
<div class="flex items-center justify-between gap-3">
<h4 class="font-semibold">3. Inquilino</h4>
<span class="inline-flex items-center px-2 py-1 rounded-full border text-xs font-semibold {{ $badgeClass($inquilinoSuggestion['confidence']) }}">
{{ ucfirst($inquilinoSuggestion['confidence']) }} — {{ $inquilinoSuggestion['reason'] }}
</span>
</div>
<p class="text-xs text-gray-500">{{ $confidenceNote($inquilinoSuggestion['confidence']) }}</p>
@if($inquilinoSuggested)
<div class="bg-gray-50 border rounded-lg p-3 text-sm">
<p class="text-gray-700">Inquilino sugerido:</p>
<p class="font-semibold">{{ $inquilinoSuggested->nombre }}</p>
<p class="text-xs text-gray-500">{{ $inquilinoSuggested->correo ? $inquilinoSuggested->correo.' · ' : '' }}{{ $inquilinoSuggested->telefono ?: '' }}</p>
</div>
@endif
<label class="flex items-center gap-2">
<input type="radio" name="inquilino_action" value="existing" @checked($inquilinoSuggested)>
<span>Usar inquilino existente</span>
</label>
<label class="flex items-center gap-2">
<input type="radio" name="inquilino_action" value="new" @checked(!$inquilinoSuggested)>
<span>Crear inquilino nuevo con los datos recibidos</span>
</label>

<div data-action-panel="inquilino" data-visible-when="existing">
<select name="inquilino_id" id="inquilino_id" class="w-full rounded-lg border-gray-300 shadow-sm">
<option value="">Seleccionar inquilino…</option>
@foreach($inquilinos as $inquilino)
<option value="{{ $inquilino->id }}" @selected($inquilinoSuggested && $inquilinoSuggested->id === $inquilino->id)>
{{ $inquilino->nombre }}{{ $inquilino->correo ? ' — '.$inquilino->correo : '' }}{{ $inquilino->telefono ? ' — '.$inquilino->telefono : '' }}
</option>
@endforeach
</select>
</div>
</section>

<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-900">
Al confirmar se creará el contrato definitivo. Si se crean cliente o propiedad nuevos, también se crearán tareas para completar la información faltante.
</div>

<div class="flex justify-end gap-3">
<a href="{{ route('contratos.pendientes.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">
Cancelar
</a>
<button type="submit" class="bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded-lg">
Resolver pendiente y crear contrato
</button>
</div>
</form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const clienteSelect = document.getElementById('fk_cliente');
    const propiedadSelect = document.getElementById('fk_propiedad');
    const propiedadOptions = Array.from(propiedadSelect.options);
    const suggestedPropiedadId = @json($propiedadSuggested?->pk_propiedad);

    function filtrarPropiedades(keepSelection = false) {
        if (!clienteSelect || !propiedadSelect) return;
        const clienteId = clienteSelect.value;
        const currentValue = propiedadSelect.value;

        propiedadOptions.forEach(function(option) {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            option.hidden = clienteId && option.dataset.cliente !== clienteId;
        });

        if (keepSelection && currentValue) {
            propiedadSelect.value = currentValue;
        } else if (suggestedPropiedadId) {
            propiedadSelect.value = suggestedPropiedadId;
        } else {
            propiedadSelect.value = '';
        }
    }

    function toggleActionPanels(name) {
        const selected = document.querySelector('input[name="' + name + '_action"]:checked');
        const value = selected ? selected.value : null;
        document.querySelectorAll('[data-action-panel="' + name + '"]').forEach(function(panel) {
            panel.hidden = panel.dataset.visibleWhen !== value;
        });
    }

    ['cliente', 'propiedad', 'inquilino'].forEach(function(name) {
        document.querySelectorAll('input[name="' + name + '_action"]').forEach(function(input) {
            input.addEventListener('change', function () {
                toggleActionPanels(name);
                if (name === 'cliente') filtrarPropiedades(false);
            });
        });
        toggleActionPanels(name);
    });

    if (clienteSelect) {
        clienteSelect.addEventListener('change', function () {
            filtrarPropiedades(false);
        });
    }

    filtrarPropiedades(true);
});
</script>
</x-app-layout>
