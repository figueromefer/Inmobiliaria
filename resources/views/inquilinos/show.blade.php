<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Perfil del Inquilino</h2>
    </x-slot>

    @php
        $documentTypeMeta = [
            'comprobante_domicilio' => ['label' => 'Comprobante domicilio', 'class' => 'bg-slate-100 text-slate-800', 'icon' => '🏠'],
            'agua' => ['label' => 'Agua', 'class' => 'bg-blue-100 text-blue-800', 'icon' => '🔵'],
            'cfe' => ['label' => 'CFE', 'class' => 'bg-orange-100 text-orange-800', 'icon' => '🟠'],
            'predial' => ['label' => 'Predial', 'class' => 'bg-green-100 text-green-800', 'icon' => '🟢'],
            'recibo' => ['label' => 'Recibo', 'class' => 'bg-purple-100 text-purple-800', 'icon' => '🟣'],
            'otro' => ['label' => 'Otro', 'class' => 'bg-gray-100 text-gray-800', 'icon' => '📄'],
        ];

        $contratosOrdenados = $inquilino->contratos->sortByDesc(function ($contrato) {
            $inicio = $contrato->fecha_inicio?->timestamp ?? 0;
            $fin = $contrato->fecha_fin?->timestamp ?? PHP_INT_MAX;
            $activo = (!$contrato->fecha_inicio || $contrato->fecha_inicio->lte(now())) && (!$contrato->fecha_fin || $contrato->fecha_fin->gte(now()));

            return ($activo ? 1_000_000_000_000 : 0) + $fin + $inicio;
        });

        $contratoActivo = $contratosOrdenados->first(function ($contrato) {
            return (!$contrato->fecha_inicio || $contrato->fecha_inicio->lte(now())) && (!$contrato->fecha_fin || $contrato->fecha_fin->gte(now()));
        });

        $statusMeta = function ($contrato) {
            if ($contrato->deleted_at ?? false) {
                return ['label' => 'Archivado', 'class' => 'bg-gray-100 text-gray-700 border-gray-200'];
            }

            if ($contrato->fecha_inicio && $contrato->fecha_inicio->gt(now())) {
                return ['label' => 'Futuro', 'class' => 'bg-blue-100 text-blue-800 border-blue-200'];
            }

            if ($contrato->fecha_fin && $contrato->fecha_fin->lt(now())) {
                return ['label' => 'Vencido', 'class' => 'bg-red-100 text-red-800 border-red-200'];
            }

            return ['label' => 'Activo', 'class' => 'bg-green-100 text-green-800 border-green-200'];
        };
    @endphp

    <div class="py-6 max-w-7xl mx-auto space-y-6">
        <div class="bg-white p-6 rounded-xl shadow-sm">
            <div class="flex justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">{{ $inquilino->nombre }}</h1>
                    <p class="text-gray-500">{{ $inquilino->correo ?: 'Sin correo' }}</p>
                </div>
                <a href="{{ route('inquilinos.index') }}" class="px-4 py-2 rounded bg-gray-100 self-start">Volver</a>
            </div>
        </div>

        <div class="grid md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Contratos</div>
                <div class="text-3xl font-bold">{{ $inquilino->contratos->count() }}</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Documentos</div>
                <div class="text-3xl font-bold">{{ $inquilino->documentos->count() }}</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Contrato activo</div>
                <div class="font-semibold">{{ $contratoActivo ? '#'.$contratoActivo->id : '—' }}</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Nacionalidad</div>
                <div class="font-semibold">{{ $inquilino->nacionalidad ?: '—' }}</div>
            </div>
        </div>

        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="font-bold mb-4">Datos del inquilino</h3>
            <div class="grid md:grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="text-gray-500">Nombre</div>
                    <div class="font-medium">{{ $inquilino->nombre }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Teléfono</div>
                    <div class="font-medium">{{ $inquilino->telefono ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Correo</div>
                    <div class="font-medium">{{ $inquilino->correo ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Nacionalidad</div>
                    <div class="font-medium">{{ $inquilino->nacionalidad ?: '—' }}</div>
                </div>
                <div class="md:col-span-2">
                    <div class="text-gray-500">Domicilio</div>
                    <div class="font-medium whitespace-pre-line">{{ $inquilino->domicilio ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Creado</div>
                    <div class="font-medium">{{ $inquilino->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Actualizado</div>
                    <div class="font-medium">{{ $inquilino->updated_at?->format('d/m/Y H:i') ?? '—' }}</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="font-bold mb-4">Contratos, propiedades y clientes</h3>
            <div class="space-y-4">
                @forelse($contratosOrdenados as $contrato)
                    @php($meta = $statusMeta($contrato))
                    <div class="border rounded-xl p-4">
                        <div class="flex flex-col md:flex-row md:justify-between gap-3 mb-4">
                            <div>
                                <div class="text-xs uppercase text-gray-500">Contrato</div>
                                <div class="font-bold">#{{ $contrato->id }}</div>
                            </div>
                            <span class="self-start rounded-full border px-3 py-1 text-xs font-bold {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                        </div>

                        <div class="grid md:grid-cols-3 gap-4 text-sm">
                            <div>
                                <div class="text-gray-500">Periodo</div>
                                <div class="font-medium">{{ $contrato->fecha_inicio?->format('Y-m-d') ?? '—' }} - {{ $contrato->fecha_fin?->format('Y-m-d') ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Renta mensual</div>
                                <div class="font-medium">{{ $contrato->monto_mensual ? '$'.number_format((float) $contrato->monto_mensual, 2) : '—' }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Depósito</div>
                                <div class="font-medium">{{ $contrato->monto_deposito ? '$'.number_format((float) $contrato->monto_deposito, 2) : '—' }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Comisión renta</div>
                                <div class="font-medium">{{ $contrato->comision_renta ? '$'.number_format((float) $contrato->comision_renta, 2) : '—' }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Comisión mensual</div>
                                <div class="font-medium">{{ $contrato->comision_mensual ? rtrim(rtrim(number_format((float) $contrato->comision_mensual, 2), '0'), '.').'%' : '—' }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Expediente JA</div>
                                <div class="font-medium">{{ $contrato->expediente_justicia_alternativa ?: '—' }}</div>
                            </div>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4 mt-4 text-sm">
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="text-gray-500">Propiedad</div>
                                @if($contrato->propiedad)
                                    <a href="{{ route('propiedades.show', $contrato->propiedad) }}" class="font-semibold text-blue-600 hover:underline">{{ $contrato->propiedad->alias ?: 'Propiedad #'.$contrato->propiedad->pk_propiedad }}</a>
                                    <div class="text-gray-600 mt-1">{{ $contrato->propiedad->domicilio ?: 'Sin domicilio' }}</div>
                                @else
                                    <div class="font-medium">—</div>
                                @endif
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="text-gray-500">Cliente / dueño</div>
                                @php($cliente = $contrato->cliente ?: $contrato->propiedad?->cliente)
                                @if($cliente)
                                    <a href="{{ route('clientes.show', $cliente) }}" class="font-semibold text-blue-600 hover:underline">{{ $cliente->nombre }}</a>
                                    <div class="text-gray-600 mt-1">{{ $cliente->correo ?: 'Sin correo' }}{{ $cliente->celular ? ' · '.$cliente->celular : '' }}</div>
                                @else
                                    <div class="font-medium">—</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-gray-50 rounded p-4 text-gray-500">Sin contratos relacionados.</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-xl p-6 shadow-sm">
            <div class="flex justify-between mb-4">
                <h3 class="font-bold">Documentos</h3>
                <a href="{{ route('documentos.create', ['inquilino' => $inquilino->id]) }}" class="bg-gray-800 text-white px-3 py-1 rounded">+ Subir</a>
            </div>

            @forelse($inquilino->documentos->groupBy(fn($d) => $d->tipo ?: 'otro') as $tipo => $docs)
                @php($meta = $documentTypeMeta[$tipo] ?? $documentTypeMeta['otro'])
                <div class="border rounded-xl mb-4 overflow-hidden">
                    <div class="p-3 bg-gray-50 border-b">
                        <span class="px-3 py-1 rounded-full text-xs font-bold {{ $meta['class'] }}">{{ $meta['icon'] }} {{ $meta['label'] }}</span>
                    </div>
                    @foreach($docs as $d)
                        <div class="flex justify-between p-3 border-b">
                            <span>{{ $d->titulo ?: 'Documento' }}</span>
                            <div class="space-x-3 shrink-0">
                                <a href="{{ route('documentos.view', $d) }}" target="_blank" rel="noopener noreferrer" class="text-blue-600">Ver</a>
                                <a href="{{ route('documentos.download', $d) }}" class="text-blue-600">Descargar</a>
                                @can('delete-anything')
                                    <form action="{{ route('documentos.destroy', $d) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar documento?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-600">Eliminar</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="bg-gray-50 rounded p-4 text-gray-500">Sin documentos</div>
            @endforelse
        </div>

        @include('movimientos._perfil-section', ['movimientosPerfil' => $movimientosPerfil])
    </div>
</x-app-layout>
