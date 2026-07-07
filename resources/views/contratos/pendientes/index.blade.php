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
        @if(session('success'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                {{ session('error') }}
            </div>
        @endif

        <div class="space-y-4">
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

                <div class="rounded-lg border bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold {{ $origenClass }}" title="{{ $origenTitle }}">
                                    {{ $origenLabel }}
                                </span>

                                @if($pendiente->estado === 'pendiente_match')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-800 text-white">
                                        Pendiente match
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-500 text-white">
                                        {{ $pendiente->estado }}
                                    </span>
                                @endif

                                <span class="text-xs text-gray-500">#{{ $pendiente->id }} · {{ $origenTitle }}</span>
                            </div>

                            <div>
                                <div class="text-xs uppercase text-gray-500">Expediente / ID externo</div>
                                <div class="font-semibold text-gray-900">{{ $pendiente->expediente ?: ($pendiente->external_id ?: '—') }}</div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                                <div>
                                    <div class="text-xs uppercase text-gray-500">Cliente recibido</div>
                                    <div class="font-medium text-gray-900">{{ $mapped['nombre_solicitante'] ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase text-gray-500">Arrendatario recibido</div>
                                    <div class="font-medium text-gray-900">{{ $mapped['nombre_complementaria'] ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase text-gray-500">Recibido</div>
                                    <div class="font-medium text-gray-900">{{ $pendiente->created_at ? $pendiente->created_at->format('Y-m-d H:i') : '—' }}</div>
                                </div>
                            </div>

                            <div>
                                <div class="text-xs uppercase text-gray-500">Propiedad recibida</div>
                                <div class="line-clamp-2 text-gray-900">{{ $mapped['domicilio_inmueble_arrendamiento'] ?? '—' }}</div>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-2 sm:flex-row lg:flex-col">
                            <a href="{{ route('contratos.pendientes.show', $pendiente) }}" class="inline-flex items-center justify-center rounded bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2">
                                Resolver
                            </a>

                            <form action="{{ route('contratos.pendientes.destroy', $pendiente) }}" method="POST" onsubmit="return confirm('¿Eliminar este contrato pendiente? Esta acción solo elimina el registro temporal y no elimina contratos, clientes, propiedades ni inquilinos.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded border border-red-200 text-red-700 hover:bg-red-50 font-semibold px-4 py-2">
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-lg border bg-white px-4 py-10 text-center text-gray-500">
                    No hay contratos pendientes de match.
                </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $pendientes->links() }}
        </div>
    </div>
</x-app-layout>
