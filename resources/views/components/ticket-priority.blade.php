@props(['priority'])

@php
$config = [
    'low' => [
        'class' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        'icon' => '🟢',
        'label' => 'Baja',
    ],
    'medium' => [
        'class' => 'bg-orange-100 text-orange-800 border-orange-300',
        'icon' => '🟠',
        'label' => 'Media',
    ],
    'high' => [
        'class' => 'bg-red-600 text-white border-red-700 animate-pulse',
        'icon' => '🚨',
        'label' => 'URGENTE',
    ],
];

$item = $config[$priority] ?? $config['low'];
@endphp

<span class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $item['class'] }}">
    <span>{{ $item['icon'] }}</span>
    {{ $item['label'] }}
</span>
