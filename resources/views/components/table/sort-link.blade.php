@props(['column', 'label', 'currentSort', 'currentDir'])

@php
    $isCurrent = $currentSort === $column;
    $nextDir = $isCurrent && $currentDir === 'asc' ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery(['sort' => $column, 'dir' => $nextDir, 'page' => 1]);
@endphp

<a href="{{ $url }}" class="adi-table-sort" aria-label="Ordenar por {{ $label }} {{ $nextDir === 'asc' ? 'ascendente' : 'descendente' }}">
    {{ $label }}
    @if($isCurrent)
        <span aria-hidden="true">{{ $currentDir === 'asc' ? '↑' : '↓' }}</span>
    @else
        <span class="adi-table-sort-muted" aria-hidden="true">↕</span>
    @endif
</a>
