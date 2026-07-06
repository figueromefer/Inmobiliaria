<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Perfil del Cliente</h2>
    </x-slot>

    @php
        $documentTypeMeta = [
            'comprobante_domicilio' => ['label' => 'Comprobante domicilio', 'class' => 'bg-slate-100 text-slate-800 border-slate-200', 'icon' => '🏠'],
            'agua' => ['label' => 'Agua', 'class' => 'bg-blue-100 text-blue-800 border-blue-200', 'icon' => '🔵'],
            'cfe' => ['label' => 'CFE', 'class' => 'bg-orange-100 text-orange-800 border-orange-200', 'icon' => '🟠'],
            'predial' => ['label' => 'Predial', 'class' => 'bg-green-100 text-green-800 border-green-200', 'icon' => '🟢'],
            'recibo' => ['label' => 'Recibo escaneado', 'class' => 'bg-purple-100 text-purple-800 border-purple-200', 'icon' => '🟣'],
            'otro' => ['label' => 'Otro', 'class' => 'bg-gray-100 text-gray-800 border-gray-200', 'icon' => '📄'],
        ];
    @endphp

    <div class="py-6 max-w-7xl mx-auto space-y-6">
        <div class="bg-white p-6 rounded shadow flex justify-between">
            <div>
                <h1 class="text-2xl font-bold">{{ $cliente->nombre }}</h1>
                <p class="text-sm text-gray-500">RFC: {{ $cliente->rfc ?: '—' }}</p>
            </div>

            <div class="space-x-2">
                <a href="{{ route('clientes.index') }}" class="bg-gray-200 px-4 py-2 rounded">Regresar</a>
                @can('manage-records')
                    <a href="{{ route('clientes.edit', $cliente) }}" class="bg-blue-600 text-white px-4 py-2 rounded">Editar</a>
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded shadow">
                <div class="text-sm text-gray-500">Propiedades</div>
                <div class="text-2xl font-bold">{{ $cliente->propiedades->count() }}</div>
            </div>

            <div class="bg-white p-4 rounded shadow">
                <div class="text-sm text-gray-500">Contratos</div>
                <div class="text-2xl font-bold">{{ $cliente->contratos->count() }}</div>
            </div>

            <div class="bg-white p-4 rounded shadow">
                <div class="text-sm text-gray-500">Documentos</div>
                <div class="text-2xl font-bold">{{ $cliente->documentos->count() }}</div>
            </div>
        </div>

        <div class="bg-white p-6 rounded shadow">
            <h3 class="font-bold mb-4">Información del cliente</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="text-gray-500">Domicilio</div>
                    <div class="font-medium">{{ $cliente->domicilio ?: '—' }}</div>
                </div>

                <div>
                    <div class="text-gray-500">Domicilio para notificaciones</div>
                    <div class="font-medium whitespace-pre-line">{{ $cliente->domicilio_notificaciones ?: '—' }}</div>
                </div>

                <div>
                    <div class="text-gray-500">Correo</div>
                    <div class="font-medium">{{ $cliente->correo ?: '—' }}</div>
                </div>

                <div>
                    <div class="text-gray-500">Celular</div>
                    <div class="font-medium">{{ $cliente->celular ?: '—' }}</div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded shadow">
            <div class="flex justify-between mb-4">
                <h3 class="font-bold">Propiedades</h3>
                @can('manage-records')
                    <a href="{{ route('propiedades.create', ['cliente_id' => $cliente->pk_cliente]) }}" class="bg-gray-800 text-white px-3 py-1 rounded">
                        + Agregar
                    </a>
                @endcan
            </div>

            @forelse($cliente->propiedades as $p)
                <div class="border p-3 mb-2 rounded">
                    <div class="flex justify-between items-center">
                        <a href="{{ route('propiedades.show', $p) }}" class="font-semibold text-gray-900 hover:text-blue-600 hover:underline">
                            {{ $p->alias }}
                        </a>

                        <span class="px-2 py-1 text-xs rounded text-white
                            @if($p->estatus_informacion == 'pendiente_critico') bg-red-500
                            @elseif($p->estatus_informacion == 'pendiente') bg-orange-400
                            @else bg-green-500
                            @endif
                        ">
                            @if($p->estatus_informacion == 'pendiente_critico') Pendiente crítico
                            @elseif($p->estatus_informacion == 'pendiente') Pendiente
                            @else Completo
                            @endif
                        </span>
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        {{ trim(($p->calle ?? '') . ' ' . ($p->numero_exterior ?? '') . ' ' . ($p->numero_interior ? 'Int. '.$p->numero_interior : '')) ?: $p->domicilio }}
                    </div>
                    <div class="text-sm">Contratos: {{ $p->contratos->count() }}</div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sin propiedades registradas.</p>
            @endforelse
        </div>

        <div class="bg-white p-6 rounded shadow">
            <h3 class="font-bold mb-3">Contratos</h3>

            @forelse($cliente->contratos as $c)
                <div class="border p-3 mb-2 rounded">
                    <div><strong>Propiedad:</strong> {{ $c->propiedad?->alias }}</div>
                    <div><strong>Inquilino:</strong> {{ $c->inquilino?->nombre }}</div>
                    <div><strong>Periodo:</strong> {{ $c->fecha_inicio }} - {{ $c->fecha_fin }}</div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sin contratos registrados.</p>
            @endforelse
        </div>

        <div class="bg-white p-6 rounded shadow">
            <div class="flex justify-between items-center mb-3">
                <div>
                    <h3 class="font-bold">Documentos</h3>
                    <p class="text-sm text-gray-500">Archivos del cliente agrupados por tipo</p>
                </div>
                @can('manage-records')
                    <a href="{{ route('documentos.create', ['cliente' => $cliente->pk_cliente]) }}"
                        class="bg-gray-800 text-white px-3 py-1 rounded">
                        + Subir documento
                    </a>
                @endcan
            </div>

            @forelse($cliente->documentos->groupBy(fn($doc) => $doc->tipo ?: 'otro') as $tipo => $docs)
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
                        @foreach($docs as $d)
                            <div class="flex justify-between items-center p-3">
                                <span class="font-medium">{{ $d->titulo ?: 'Documento sin título' }}</span>
                                <div class="space-x-3 shrink-0">
                                    <a href="{{ route('documentos.view', $d) }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Ver</a>
                                    <a href="{{ route('documentos.download', $d) }}" class="text-blue-600 hover:underline">Descargar</a>
                                    @can('delete-anything')
                                        <form action="{{ route('documentos.destroy', $d) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar documento?');">
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
                <p class="text-sm text-gray-500">Sin documentos registrados.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
