<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Archivados</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto py-6 px-4 space-y-6">
        @if($missingArchiveColumns)
            <div class="rounded border border-yellow-200 bg-yellow-50 p-4 text-yellow-900">
                Faltan columnas de archivado. Ejecuta las migraciones pendientes antes de usar esta sección.
            </div>
        @endif

        <div class="bg-white rounded shadow p-4">
            <form method="GET" action="{{ route('archivados.index') }}" class="flex flex-col md:flex-row gap-3">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">Buscar</label>
                    <input type="text" name="q" value="{{ $q }}" class="mt-1 w-full rounded border-gray-300 shadow-sm" placeholder="Cliente, propiedad, inquilino, expediente">
                </div>
                <div class="flex items-end gap-2">
                    <button class="rounded bg-gray-800 px-4 py-2 text-white">Buscar</button>
                    @if($q !== '')
                        <a href="{{ route('archivados.index') }}" class="rounded border px-4 py-2">Limpiar</a>
                    @endif
                </div>
            </form>
        </div>

        <section class="bg-white rounded shadow p-5">
            <div class="mb-4">
                <h3 class="text-lg font-bold">Clientes archivados</h3>
                <p class="text-sm text-gray-500">Desarchivar un cliente no restaura automáticamente sus contratos archivados.</p>
            </div>

            <div class="overflow-x-auto border rounded">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="text-left px-3 py-2">ID</th>
                            <th class="text-left px-3 py-2">Nombre</th>
                            <th class="text-left px-3 py-2">Correo</th>
                            <th class="text-left px-3 py-2">Teléfono</th>
                            <th class="text-left px-3 py-2">Archivado</th>
                            <th class="text-left px-3 py-2">Contratos archivados</th>
                            <th class="text-right px-3 py-2">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clientesArchivados as $cliente)
                            <tr class="border-b">
                                <td class="px-3 py-2">{{ $cliente->pk_cliente }}</td>
                                <td class="px-3 py-2 font-semibold">{{ $cliente->nombre }}</td>
                                <td class="px-3 py-2">{{ $cliente->correo ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $cliente->celular ?: ($cliente->fijo ?: '—') }}</td>
                                <td class="px-3 py-2">{{ $cliente->deleted_at ? \Carbon\Carbon::parse($cliente->deleted_at)->format('Y-m-d H:i') : '—' }}</td>
                                <td class="px-3 py-2">{{ $cliente->contratos_archivados_count }}</td>
                                <td class="px-3 py-2 text-right">
                                    <form action="{{ route('archivados.clientes.restore', $cliente) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas desarchivar este registro?');">
                                        @csrf
                                        @method('PATCH')
                                        <button class="rounded bg-green-600 px-3 py-1 text-xs font-bold text-white hover:bg-green-700">Desarchivar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-gray-500">No hay clientes archivados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $clientesArchivados->links() }}
            </div>
        </section>

        <section class="bg-white rounded shadow p-5">
            <div class="mb-4">
                <h3 class="text-lg font-bold">Contratos archivados</h3>
                <p class="text-sm text-gray-500">Un contrato solo puede desarchivarse si su cliente está activo.</p>
            </div>

            <div class="overflow-x-auto border rounded">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="text-left px-3 py-2">ID</th>
                            <th class="text-left px-3 py-2">Cliente</th>
                            <th class="text-left px-3 py-2">Propiedad</th>
                            <th class="text-left px-3 py-2">Inquilino</th>
                            <th class="text-left px-3 py-2">Inicio</th>
                            <th class="text-left px-3 py-2">Fin</th>
                            <th class="text-left px-3 py-2">Archivado</th>
                            <th class="text-right px-3 py-2">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contratosArchivados as $contrato)
                            @php($clienteArchivado = $contrato->cliente && !blank($contrato->cliente->deleted_at))
                            <tr class="border-b">
                                <td class="px-3 py-2">#{{ $contrato->id }}</td>
                                <td class="px-3 py-2">
                                    {{ $contrato->cliente?->nombre ?? '—' }}
                                    @if($clienteArchivado)
                                        <span class="ml-1 rounded bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">Cliente archivado</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">{{ $contrato->propiedad?->alias ?: ($contrato->propiedad?->domicilio ?: '—') }}</td>
                                <td class="px-3 py-2">{{ $contrato->inquilino?->nombre ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $contrato->fecha_inicio?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $contrato->fecha_fin?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $contrato->deleted_at ? \Carbon\Carbon::parse($contrato->deleted_at)->format('Y-m-d H:i') : '—' }}</td>
                                <td class="px-3 py-2 text-right">
                                    @if($clienteArchivado)
                                        <span class="text-xs text-gray-500">Desarchiva primero el cliente</span>
                                    @else
                                        <form action="{{ route('archivados.contratos.restore', $contrato) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas desarchivar este registro?');">
                                            @csrf
                                            @method('PATCH')
                                            <button class="rounded bg-green-600 px-3 py-1 text-xs font-bold text-white hover:bg-green-700">Desarchivar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-6 text-center text-gray-500">No hay contratos archivados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $contratosArchivados->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
