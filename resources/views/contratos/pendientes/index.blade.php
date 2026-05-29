<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Contratos pendientes
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Contratos recibidos que requieren vincular cliente, propiedad e inquilino antes de crear el contrato final.
                </p>
            </div>

            <a href="{{ route('contratos.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg">
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
                        @endphp
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3">{{ $pendiente->id }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                    {{ str_replace('_', ' ', $pendiente->origen) }}
                                </span>
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
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-800">
                                        Pendiente match
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
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
