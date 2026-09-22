<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl">Previsualización documental — borrador #{{ $draft->id }}</h2>
                <p class="text-sm text-gray-500">La generación sólo usa una copia de la plantilla; nunca modifica la original.</p>
            </div>
            <a href="{{ route('contratos.borradores.show', $draft) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">Volver al borrador</a>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto mt-6 space-y-4">
        <div class="bg-white p-5 rounded shadow">
            <p><b>Estado documental:</b> {{ $document['status'] }}</p>
            <p><b>Plantilla:</b> {{ $document['template_key'] }}</p>
            <p><b>Carpeta propuesta:</b> {{ $document['folder_name'] }}</p>
            <p><b>Documento propuesto:</b> {{ $document['document_name'] }}</p>
            @foreach($document['reasons'] as $reason)
                <p class="text-orange-700">{{ $reason }}</p>
            @endforeach
            @if($document['status'] === 'ready')
                <form method="POST" action="{{ route('contratos.borradores.document-preview.generate', $draft) }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="expected_draft_version_id" value="{{ $draft->currentVersion->id }}">
                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                    <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg">Generar documento</button>
                </form>
            @endif
        </div>
        @if($documentVersions->isNotEmpty())
            <div class="bg-white p-5 rounded shadow">
                <h3 class="font-semibold">Versiones documentales</h3>
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach($documentVersions as $documentVersion)
                        <li class="border-t pt-2">
                            <b>Versión {{ $documentVersion->document_version }}</b> — {{ $documentVersion->status }} — intentos: {{ $documentVersion->attempts }}
                            @if($documentVersion->url)
                                <a class="ml-2 text-blue-700 underline" target="_blank" rel="noopener noreferrer" href="{{ $documentVersion->url }}">Abrir Google Doc</a>
                            @endif
                            @if($documentVersion->status === \App\Models\ContractDocumentVersion::STATUS_FAILED)
                                <span class="block text-red-700">{{ $documentVersion->last_error }}</span>
                                <form class="inline" method="POST" action="{{ route('contratos.borradores.document-preview.retry', [$draft, $documentVersion]) }}">
                                    @csrf
                                    <button class="mt-2 bg-orange-600 hover:bg-orange-700 text-white font-bold py-1 px-3 rounded">Reintentar generación</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="bg-white p-5 rounded shadow">
            <h3 class="font-semibold">Operaciones documentales previstas</h3>
            <ul class="list-disc ml-5">
                @foreach($document['document_operations'] as $operation)
                    <li>{{ $operation['operation'] ?? 'replace_placeholders' }}: {{ $operation['marker'] ?? $operation['identifier'] ?? 'placeholders' }}</li>
                @endforeach
            </ul>
        </div>
        <div class="bg-white p-5 rounded shadow">
            <h3 class="font-semibold">Placeholders resueltos</h3>
            <dl class="grid md:grid-cols-2 gap-2 text-sm">
                @foreach($document['placeholders'] as $key => $value)
                    <div><dt class="font-mono">{{ $key }}</dt><dd>{{ $value ?: '—' }}</dd></div>
                @endforeach
            </dl>
        </div>
    </div>
</x-app-layout>
