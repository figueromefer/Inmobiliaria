<x-app-layout>
    <x-slot name="header"><div class="flex justify-between gap-3"><div><h2 class="font-semibold text-xl text-gray-800">Snapshot histórico — borrador #{{ $draft->id }}</h2><p class="text-sm text-gray-500">Versión {{ $version->draft_version }} de sólo lectura.</p></div><a href="{{ route('contratos.borradores.versions', $draft) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">Volver al historial</a></div></x-slot>
    <div class="max-w-6xl mx-auto mt-6 space-y-5"><div class="bg-white rounded-lg shadow p-5 text-sm"><span class="text-gray-500">Hash</span><div class="font-mono text-xs break-all">{{ $version->payload_hash }}</div></div>@include('contratos.borradores._snapshot', ['payload' => $version->canonical_payload])</div>
</x-app-layout>
