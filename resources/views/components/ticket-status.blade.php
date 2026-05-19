@props(['status'])

@php
$classes = match($status) {
    'completed' => 'bg-green-100 text-green-800 border-green-200',
    'in_progress' => 'bg-blue-100 text-blue-800 border-blue-200',
    'canceled' => 'bg-gray-100 text-gray-700 border-gray-200',
    default => 'bg-yellow-100 text-yellow-800 border-yellow-200',
};

$label = match($status) {
    'completed' => 'Completado',
    'in_progress' => 'En proceso',
    'canceled' => 'Cancelado',
    default => 'Abierto',
};
@endphp

<span {{ $attributes->merge([
'class' => "inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {$classes}"
]) }}>
    {{ $label }}
</span>