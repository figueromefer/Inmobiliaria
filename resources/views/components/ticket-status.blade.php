@props(['status'])
@php
$map = [
  'open' => 'bg-blue-100 text-blue-800 border-blue-200',
  'in_progress' => 'bg-amber-100 text-amber-800 border-amber-200',
  'completed' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
  'canceled' => 'bg-gray-100 text-gray-700 border-gray-200',
];
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded border text-xs {{ $map[$status] ?? 'bg-gray-100 text-gray-700 border-gray-200' }}">
  {{ ['open'=>'Abierto','in_progress'=>'En proceso','completed'=>'Completado','canceled'=>'Cancelado'][$status] ?? ucfirst($status) }}
</span>
