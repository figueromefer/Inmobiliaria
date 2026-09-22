<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h2 class="font-semibold text-xl text-gray-800">Borrador #{{ $draft->id }}</h2><p class="text-sm text-gray-500">Estado técnico: {{ $draft->status }}</p></div>
            <div class="flex gap-2"><a href="{{ route('contratos.borradores.wizard.show', [$draft, 'generales']) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Continuar captura</a><a href="{{ route('contratos.borradores.document-preview', $draft) }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">Previsualización documental</a><a href="{{ route('contratos.borradores.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">Volver</a></div>
        </div>
    </x-slot>
    @php($payload = $draft->currentVersion->canonical_payload)
    <div class="max-w-6xl mx-auto mt-6 space-y-5">
        <div class="bg-white rounded-lg shadow p-5 grid gap-3 md:grid-cols-4 text-sm"><div><span class="text-gray-500">Versión actual</span><div class="font-semibold">{{ $draft->currentVersion->draft_version }}</div></div><div><span class="text-gray-500">Actor</span><div class="font-semibold">{{ $draft->currentVersion->createdBy?->name ?? '—' }}</div></div><div><span class="text-gray-500">Fecha</span><div class="font-semibold">{{ $draft->currentVersion->created_at->format('Y-m-d H:i') }}</div></div><div><span class="text-gray-500">Hash</span><div class="font-mono text-xs break-all">{{ $draft->currentVersion->payload_hash }}</div></div></div>
        @include('contratos.borradores._snapshot', ['payload' => $payload])
        @include('contratos.borradores._reconciliation', ['draft' => $draft, 'searches' => $searches, 'comparisons' => $comparisons])
        <div class="bg-white rounded-lg shadow p-5"><a href="{{ route('contratos.borradores.versions', $draft) }}" class="text-blue-600 underline font-medium">Ver historial de versiones</a></div>
    </div>
</x-app-layout>
