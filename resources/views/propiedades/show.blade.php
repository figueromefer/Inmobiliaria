<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalles de la Propiedad</h2>
    </x-slot>

    @php
        $domicilioPartes = array_filter([
            trim(($propiedad->calle ?? '') . ' ' . ($propiedad->numero_exterior ?? '') . ' ' . ($propiedad->numero_interior ? 'Int. ' . $propiedad->numero_interior : '')),
            $propiedad->colonia ? 'Col. ' . $propiedad->colonia : null,
            $propiedad->codigo_postal ? 'CP ' . $propiedad->codigo_postal : null,
            $propiedad->municipio,
            $propiedad->estado,
        ]);

        $estatusClass = match($propiedad->estatus_informacion ?? 'pendiente_critico') {
            'completo' => 'bg-green-100 text-green-800 border-green-200',
            'pendiente' => 'bg-orange-100 text-orange-800 border-orange-200',
            default => 'bg-red-100 text-red-800 border-red-200',
        };

        $estatusLabel = match($propiedad->estatus_informacion ?? 'pendiente_critico') {
            'completo' => 'Completa',
            'pendiente' => 'Pendiente',
            default => 'Pendiente crítico',
        };

        $documentTypeMeta = [
            'comprobante_domicilio' => ['label' => 'Comprobante domicilio', 'class' => 'bg-slate-100 text-slate-800 border-slate-200', 'icon' => '🏠'],
            'agua' => ['label' => 'Agua', 'class' => 'bg-blue-100 text-blue-800 border-blue-200', 'icon' => '🔵'],
            'cfe' => ['label' => 'CFE', 'class' => 'bg-orange-100 text-orange-800 border-orange-200', 'icon' => '🟠'],
            'predial' => ['label' => 'Predial', 'class' => 'bg-green-100 text-green-800 border-green-200', 'icon' => '🟢'],
            'recibo' => ['label' => 'Recibo escaneado', 'class' => 'bg-purple-100 text-purple-800 border-purple-200', 'icon' => '🟣'],
            'otro' => ['label' => 'Otro', 'class' => 'bg-gray-100 text-gray-800 border-gray-200', 'icon' => '📄'],
        ];
    @endphp

    <div class="py-6 max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $propiedad->alias }}</h1>
                        <span class="text-xs px-3 py-1 rounded-full border {{ $estatusClass }}">{{ $estatusLabel }}</span>
                    </div>
                    <p class="text-sm text-gray-500">Cliente: {{ $propiedad->cliente->nombre ?? 'N/A' }}</p>
                </div>

                <div class="flex gap-2">
                    <a href="{{ route('propiedades.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg">Volver</a>
                    <a href="{{ route('propiedades.edit', $propiedad->pk_propiedad) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">Editar</a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6 space-y-5">
                <h3 class="font-bold text-lg text-gray-900">Información general</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2 bg-gray-50 rounded-lg p-4">
                        <div class="text-xs uppercase text-gray-500 mb-1">Domicilio</div>
                        <div class="font-medium text-gray-800">
                            {{ count($domicilioPartes) ? implode(', ', $domicilioPartes) : ($propiedad->domicilio ?: 'Sin domicilio registrado') }}
                        </div>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs uppercase text-gray-500 mb-1">Agua</div>
                        <div class="font-medium text-gray-800">{{ $propiedad->siapa ?: 'Sin dato' }}</div>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs uppercase text-gray-500 mb-1">CFE</div>
                        <div class="font-medium text-gray-800">{{ $propiedad->cfe ?: 'Sin dato' }}</div>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs uppercase text-gray-500 mb-1">Predial</div>
                        <div class="font-medium text-gray-800">{{ $propiedad->predial ?: 'Sin dato' }}</div>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs uppercase text-gray-500 mb-1">Coordenadas</div>
                        <div class="font-medium text-gray-800">{{ $propiedad->latitud ?: '—' }}, {{ $propiedad->longitud ?: '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h3 class="font-bold text-lg text-gray-900">Mantenimiento</h3>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-xs uppercase text-gray-500 mb-1">Banco</div>
                    <div class="font-medium">{{ $propiedad->mantenimiento_banco ?: 'Sin dato' }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-xs uppercase text-gray-500 mb-1">Cuenta</div>
                    <div class="font-medium">{{ $propiedad->mantenimiento_cuenta ?: 'Sin dato' }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-xs uppercase text-gray-500 mb-1">Monto</div>
                    <div class="font-medium">{{ $propiedad->mantenimiento_monto ? '$'.number_format($propiedad->mantenimiento_monto, 2) : 'Sin dato' }}</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="font-bold text-lg text-gray-900">Documentos</h3>
                    <p class="text-sm text-gray-500">Archivos relacionados con esta propiedad, agrupados por tipo</p>
                </div>

                <a href="{{ route('documentos.create', ['propiedad' => $propiedad->pk_propiedad]) }}" class="bg-gray-800 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">+ Subir</a>
            </div>

            @forelse($propiedad->documentos->groupBy(fn($doc) => $doc->tipo ?: 'otro') as $tipo => $docs)
                @php($meta = $documentTypeMeta[$tipo] ?? $documentTypeMeta['otro'])

                <div class="border rounded-xl overflow-hidden mb-4">
                    <div class="flex items-center justify-between bg-gray-50 px-4 py-3 border-b">
                        <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $meta['class'] }}">
                            <span>{{ $meta['icon'] }}</span>
                            {{ $meta['label'] }}
                        </span>
                        <span class="text-xs text-gray-500">{{ $docs->count() }} archivo(s)</span>
                    </div>

                    <div class="divide-y">
                        @foreach($docs as $doc)
                            <div class="flex justify-between items-center p-3">
                                <span class="font-medium">{{ $doc->titulo ?: 'Documento sin título' }}</span>

                                <div class="space-x-3 shrink-0">
                                    <a href="{{ route('documentos.view', $doc) }}" class="text-blue-600 hover:underline">Ver</a>

                                    @can('delete-anything')
                                        <form action="{{ route('documentos.destroy', $doc) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar documento?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:underline">Eliminar</button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="text-gray-500 bg-gray-50 rounded-lg p-4">Sin documentos</div>
            @endforelse
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="font-bold text-lg text-gray-900">Tickets</h3>
                    <p class="text-sm text-gray-500">Incidencias y tareas relacionadas con esta propiedad</p>
                </div>

                @can('manage-records')
                    <a href="{{ route('tickets.create', ['propiedad' => $propiedad->pk_propiedad]) }}"
                    class="bg-gray-800 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                        + Nuevo ticket
                    </a>
                @endcan
            </div>

            @forelse($propiedad->tickets as $ticket)
                <div class="border rounded-lg p-4 mb-3">
                    <div class="flex justify-between gap-4">
                        <div>
                            <a href="{{ route('tickets.show', $ticket) }}"
                            class="font-semibold text-gray-900 hover:text-blue-600">
                                {{ $ticket->title }}
                            </a>

                            <p class="text-sm text-gray-500 mt-1">
                                {{ $ticket->description ?: 'Sin descripción' }}
                            </p>

                            <div class="text-xs text-gray-500 mt-2">
                                Creado por: {{ $ticket->creator?->name ?? '—' }}
                                @if($ticket->assignee)
                                    · Asignado a: {{ $ticket->assignee->name }}
                                @endif
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <x-ticket-status :status="$ticket->status" />

                            <div class="text-xs text-gray-500 mt-2">
                                Vence: {{ $ticket->due_date?->format('d/m/Y') ?? '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-gray-500 bg-gray-50 rounded-lg p-4">
                    Sin tickets registrados para esta propiedad.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
