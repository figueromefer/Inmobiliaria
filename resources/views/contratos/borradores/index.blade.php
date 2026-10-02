<x-app-layout>
    <style>
        .client-link-button { align-items:center; background-color:#a16207; border:1px solid #854d0e; border-radius:.375rem; color:#fff; cursor:pointer; display:inline-flex; font-weight:700; line-height:1.25; padding:.5rem .75rem; text-decoration:none; }
        .client-link-button:hover { background-color:#854d0e; color:#fff; }
        .client-link-button:disabled { cursor:not-allowed; opacity:.6; }
        .contract-prepare-button { align-items:center; background-color:#4f46e5; border:1px solid #3730a3; border-radius:.375rem; color:#fff; display:inline-flex; font-weight:700; line-height:1.25; padding:.5rem .75rem; text-decoration:none; }
        .contract-prepare-button:hover { background-color:#3730a3; color:#fff; }
    </style>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Borradores y solicitudes de contrato</h2>
                <p class="mt-1 text-sm text-gray-500">Captura interna y solicitudes enviadas por clientes.</p>
            </div>
            <a href="{{ route('contratos.borradores.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Nuevo borrador</a>
        </div>
    </x-slot>

    @php
        $sourceLabels = ['public_form' => 'Solicitud del cliente', 'laravel' => 'Captura interna', 'internal' => 'Captura interna'];
        $statusLabels = ['draft' => 'En captura', 'submitted' => 'Enviada'];
    @endphp
    <div class="max-w-7xl mx-auto mt-6 bg-white p-6 rounded-lg shadow">
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-2" data-live-search>
            <div>
                <label for="q" class="block text-sm font-medium">Buscar</label>
                <input id="q" name="q" value="{{ $q ?? '' }}" placeholder="ID, origen, estado o creador" class="mt-1 rounded border px-3 py-2" data-live-search-input>
            </div>
            <button class="rounded bg-gray-800 px-4 py-2 text-white">Buscar</button>
            <a href="{{ route('contratos.borradores.index') }}" class="rounded bg-gray-100 px-4 py-2 text-gray-700">Limpiar</a>
        </form>
        <div class="adi-table-wrap">
            <table class="adi-table">
                <thead>
                    <tr>
                        <th><x-table.sort-link column="id" label="ID" :current-sort="$sort" :current-dir="$dir" /></th>
                        <th><x-table.sort-link column="source" label="Origen" :current-sort="$sort" :current-dir="$dir" /></th>
                        <th><x-table.sort-link column="status" label="Estado" :current-sort="$sort" :current-dir="$dir" /></th>
                        <th class="text-left px-4 py-3">Seguimiento</th>
                        <th class="text-left px-4 py-3">Versión actual</th>
                        <th class="text-left px-4 py-3">Creado por</th>
                        <th><x-table.sort-link column="updated_at" label="Actualizado" :current-sort="$sort" :current-dir="$dir" /></th>
                        <th class="text-left px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($drafts as $draft)
                        @php($isEditablePublicRequest = $draft->source === 'public_form' && $draft->publicRequest && $draft->status === \App\Models\ContractDraft::STATUS_DRAFT && !$draft->publicRequest->submitted_at && !$draft->publicRequest->revoked_at)
                        @php($isInternalDraft = in_array($draft->source, ['laravel', 'internal'], true))
                        @php($canDelete = $draft->status === \App\Models\ContractDraft::STATUS_DRAFT && $draft->contrato_id === null && $draft->source !== 'public_form' && $draft->finalization_key === null)
                        <tr>
                            <td class="font-medium">#{{ $draft->id }}</td>
                            <td><span class="rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-800">{{ $sourceLabels[$draft->source] ?? ucfirst($draft->source) }}</span></td>
                            <td><span class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-700">{{ $statusLabels[$draft->status] ?? ucfirst($draft->status) }}</span></td>
                            <td>
                                @if($draft->publicRequest)
                                    <div class="text-xs text-gray-500">Creada: {{ $draft->publicRequest->created_at?->format('Y-m-d H:i') ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">Expira: {{ $draft->publicRequest->expires_at?->format('Y-m-d H:i') ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">Enviada: {{ $draft->publicRequest->submitted_at?->format('Y-m-d H:i') ?? '—' }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $draft->currentVersion?->draft_version ?? '—' }}</td>
                            <td>{{ $draft->createdBy?->name ?? '—' }}</td>
                            <td>{{ $draft->updated_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td>
                                <a class="inline-flex bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-3 py-1 rounded" href="{{ route('contratos.borradores.show', $draft) }}">Ver borrador</a>
                                @if($isInternalDraft)<a class="contract-prepare-button mt-2" href="{{ route('contratos.borradores.document-preview', $draft) }}">Preparar contrato</a>@endif
                                @if($isEditablePublicRequest)<form method="POST" action="{{ route('contratos.borradores.public-continuation-link.regenerate', $draft) }}" class="inline" onsubmit="return confirm('El enlace anterior dejará de funcionar. ¿Deseas generar uno nuevo?');">@csrf<button type="submit" class="client-link-button mt-2 inline-flex bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs px-3 py-1 rounded">Generar enlace para cliente</button></form>@endif
                                @can('delete-anything')
                                    @if($canDelete)
                                        <form method="POST" action="{{ route('contratos.borradores.destroy', $draft) }}" class="inline" onsubmit="return confirm('¿Eliminar este borrador? Esta acción no se puede deshacer.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="mt-2 inline-flex rounded border border-red-700 bg-red-700 px-3 py-1 text-xs font-bold text-white hover:bg-red-800">Eliminar borrador</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">Aún no hay solicitudes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $drafts->links() }}</div>
    </div>
</x-app-layout>
