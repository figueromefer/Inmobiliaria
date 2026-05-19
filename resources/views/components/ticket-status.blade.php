@props(['status'])

@php
$classes = match($status) {
    'completed' => 'bg-emerald-600 text-white border-emerald-700 shadow-sm',
    'in_progress' => 'bg-blue-600 text-white border-blue-700 shadow-sm',
    'canceled' => 'bg-slate-500 text-white border-slate-600 shadow-sm',
    default => 'bg-amber-500 text-white border-amber-600 shadow-sm',
};

$dot = match($status) {
    'completed' => 'bg-emerald-200',
    'in_progress' => 'bg-blue-200',
    'canceled' => 'bg-slate-200',
    default => 'bg-amber-100',
};

$label = match($status) {
    'completed' => 'Completado',
    'in_progress' => 'En proceso',
    'canceled' => 'Cancelado',
    default => 'Pendiente',
};
@endphp

<span {{ $attributes->merge([
    'class' => "inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wide {$classes}"
]) }}>
    <span class="h-2 w-2 rounded-full {{ $dot }}"></span>
    {{ $label }}
</span>
