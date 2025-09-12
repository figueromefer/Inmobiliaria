@props(['priority'])
@php
$map = [
  'low' => 'bg-gray-100 text-gray-700 border-gray-200',
  'medium' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
  'high' => 'bg-rose-100 text-rose-800 border-rose-200',
];
@endphp
@if($priority)
  <span class="inline-flex items-center px-2 py-0.5 rounded border text-xs {{ $map[$priority] ?? 'bg-gray-100 text-gray-700 border-gray-200' }}">
    {{ ucfirst($priority) }}
  </span>
@endif
