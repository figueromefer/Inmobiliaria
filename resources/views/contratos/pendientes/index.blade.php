<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Contratos pendientes
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Contratos recibidos que requieren vincular cliente, propiedad e inquilino antes de crear el contrato final.
                </p>
            </div>

            <a href="{{ route('contratos.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">
                ← Volver a contratos
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto mt-6 bg-white lg:px-8 py-6">
        <div class="overflow-x-auto bg-white border rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3">ID</th>
                        <th class="text-left px-4 py-3">Origen</th>
                        <th class="text-left px-4 py-3">Expediente / ID externo</th>
                        <th class="text-left px-4 py-3">Cliente recibido</th>
                        <th class="text-left px-4 py-3">Propiedad recibida</th>
                        <th class="text-left px-4 py-3">Estado</th>
                        <th class="text-left px-4 py-3">Recibido</th>
                        <th class="text-left px-4 py-3">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendientes as $pendiente)
                        @php
                            $mapped = $pendiente->mapped_payload ?? [];
                            $origenLabel = match ($pendiente->origen) {
                                'justicia_alternativa' => 'JA',
                                'privado' => 'PRIVADO',
                                default => strtoupper(str_replace('_', ' ', $pendiente->origen)),
                            };
                            $origenTitle = match ($pendiente->origen) {
                                'justicia_alternativa' => 'Justicia Alternativa',
                                'privado' => 'Contrato privado',
                                default => str_replace('_', ' ', $pendiente->origen),
                            };
                            $origenClass = $pendiente->origen === 'justicia_alternativa'
                                ? 'bg-gray-800 text-white'
                                : 'bg-gray-500 text-white';
                        @endphp
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3">{{ $pendiente->id }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold {{ $origenClass }}" title="{{ $origenTitle }}">
                                    {{ $origenLabel }}
                                </span>
                                <div class="text-xs text-gray-500 mt-1">{{ $origenTitle }}</div>
                            </td>
                            <td class="px-4 py-3">
                                {{ $pendiente->expediente ?: ($pendiente->external_id ?: '—') }}
                            </td>
                            <td class="px-4 py-3">
                                {{ $mapped['nombre_solicitante'] ?? '—' }}
                            </td>
                            <td class="px-4 py-3 max-w-xs truncate">
                                {{ $mapped['domicilio_inmueble_arrendamiento'] ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if($pendiente->estado === 'pendiente_match')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-800 text-white">
                                        Pendiente match
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-500 text-white">
                                        {{ $pendiente->estado }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                {{ $pendiente->created_at ? $pendiente->created_at->format('Y-m-d H:i') : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('contratos.pendientes.show', $pendiente) }}" class="text-blue-600 hover:underline font-semibold">
                                    Resolver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                No hay contratos pendientes de match.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $pendientes->links() }}
        </div>
    </div>
</x-app-layout>
