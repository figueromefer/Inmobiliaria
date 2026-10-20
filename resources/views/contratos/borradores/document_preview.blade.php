<x-app-layout>
    <style>
        .document-generate-button { align-items:center; background-color:#4f46e5; border:1px solid #3730a3; border-radius:.5rem; color:#fff; cursor:pointer; display:inline-flex; font-weight:700; line-height:1.25; padding:.625rem 1rem; }
        .document-generate-button:hover { background-color:#3730a3; color:#fff; }
        .document-retry-button { align-items:center; background-color:#c2410c; border:1px solid #9a3412; border-radius:.375rem; color:#fff; cursor:pointer; display:inline-flex; font-weight:700; line-height:1.25; padding:.5rem .75rem; }
        .document-retry-button:hover { background-color:#9a3412; color:#fff; }
        .document-continue-button { align-items:center; background-color:#2563eb; border:1px solid #1d4ed8; border-radius:.5rem; color:#fff; display:inline-flex; font-weight:700; line-height:1.25; padding:.625rem 1rem; text-decoration:none; }
        .document-continue-button:hover { background-color:#1d4ed8; color:#fff; }
        .document-generate-button:disabled { cursor:not-allowed; opacity:.65; }
        .document-generation-overlay { align-items:center; background:rgba(15,23,42,.72); display:none; inset:0; justify-content:center; position:fixed; z-index:1000; }
        .document-generation-overlay.is-visible { display:flex; }
        .document-generation-dialog { background:#fff; border-radius:.75rem; box-shadow:0 16px 48px rgba(0,0,0,.3); max-width:26rem; padding:1.75rem; text-align:center; }
        .document-generation-spinner { animation:document-spin 1s linear infinite; border:4px solid #d1d5db; border-radius:50%; border-top-color:#4f46e5; height:2.4rem; margin:0 auto 1rem; width:2.4rem; }
        @keyframes document-spin { to { transform:rotate(360deg); } }
    </style>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl">Previsualización documental — borrador #{{ $draft->id }}</h2>
                <p class="text-sm text-gray-500">La generación sólo usa una copia de la plantilla; nunca modifica la original.</p>
            </div>
            <a href="{{ route('contratos.borradores.show', $draft) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">Volver al borrador</a>
        </div>
    </x-slot>

    @php
        $firstMissing = $document['missing_fields'][0] ?? null;
        $stepForMissing = match (true) {
            in_array($firstMissing, ['domicilio del inmueble', 'fecha de firma'], true) => 'generales',
            $firstMissing === 'uso del inmueble' => 'uso',
            in_array($firstMissing, ['inicio de vigencia', 'fin de vigencia', 'vigencia', 'días de pago', 'renta total', 'renta mensual', 'depósito en garantía'], true) => 'vigencia',
            str_starts_with((string) $firstMissing, 'arrendador:') || str_starts_with((string) $firstMissing, 'representante arrendador:') => 'arrendador',
            str_starts_with((string) $firstMissing, 'arrendatario:') || str_starts_with((string) $firstMissing, 'representante arrendatario:') => 'arrendatario',
            str_starts_with((string) $firstMissing, 'tercero/fiador:') || str_starts_with((string) $firstMissing, 'representante tercero/fiador:') => 'tercero',
            str_starts_with((string) $firstMissing, 'garantía:') => 'garantia',
            str_starts_with((string) $firstMissing, 'transferencia:') => 'pago',
            $firstMissing === 'mantenimiento: pagador' => 'mantenimiento',
            default => 'generales',
        };
    @endphp
    <div class="max-w-6xl mx-auto mt-6 space-y-4">
        <div class="bg-white p-5 rounded shadow">
            @if($document['status'] === 'blocked')
                <h3 class="font-semibold text-lg text-orange-900">El contrato todavía no está listo para generarse.</h3>
                <p class="mt-2 text-gray-700">Completa los siguientes datos:</p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-gray-800">
                    @foreach($document['missing_fields'] as $field)
                        <li>{{ $field }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('contratos.borradores.wizard.show', [$draft, $stepForMissing]) }}" class="document-continue-button mt-5">Continuar captura</a>
            @elseif($document['status'] === 'requires_review')
                <h3 class="font-semibold text-lg text-orange-900">El contrato requiere revisión antes de generarse.</h3>
                <p class="mt-2 text-gray-700">Revisa la información indicada y confirma el criterio correspondiente antes de continuar.</p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-gray-800">
                    @foreach($document['reasons'] as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('contratos.borradores.wizard.show', [$draft, 'uso']) }}" class="document-continue-button mt-5">Continuar captura</a>
            @elseif($document['status'] === 'ready')
                <h3 class="font-semibold text-lg text-green-900">{{ $draft->purpose === 'revision' ? 'La revisión está lista para publicarse.' : 'El contrato está listo para generarse.' }}</h3>
                <p class="mt-2 text-gray-700">{{ $draft->purpose === 'revision' ? 'Al continuar se generará la nueva versión del documento y se aplicarán los cambios al contrato definitivo.' : 'Revisa la información antes de generar el documento.' }}</p>
                <form method="POST" action="{{ route('contratos.borradores.document-preview.generate', $draft) }}" class="mt-4" data-document-generation-form data-revision="{{ $draft->purpose === 'revision' ? 'true' : 'false' }}">
                    @csrf
                    <input type="hidden" name="expected_draft_version_id" value="{{ $draft->currentVersion->id }}">
                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                    <button type="submit" class="document-generate-button">{{ $draft->purpose === 'revision' ? 'Generar y publicar cambios' : 'Generar documento' }}</button>
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
                                <a class="ml-2 text-blue-700 underline" target="_blank" rel="noopener noreferrer" href="{{ $documentVersion->url }}">Ver documento</a>
                            @endif
                            @if(is_string($documentVersion->drive_folder_id) && preg_match('/^[A-Za-z0-9_-]+$/', $documentVersion->drive_folder_id))
                                <a class="ml-2 text-blue-700 underline" target="_blank" rel="noopener noreferrer" href="https://drive.google.com/drive/folders/{{ rawurlencode($documentVersion->drive_folder_id) }}">Abrir carpeta en Drive</a>
                            @endif
                            @if($documentVersion->status === \App\Models\ContractDocumentVersion::STATUS_FAILED)
                                <span class="block text-red-700">{{ $documentVersion->last_error }}</span>
                                <form class="inline" method="POST" action="{{ route('contratos.borradores.document-preview.retry', [$draft, $documentVersion]) }}">
                                    @csrf
                                    <button class="document-retry-button mt-2">Reintentar generación</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    <div class="document-generation-overlay" data-document-generation-overlay aria-live="assertive" aria-modal="true" role="dialog">
        <div class="document-generation-dialog"><div class="document-generation-spinner" aria-hidden="true"></div><h3 class="font-semibold text-lg" data-document-generation-title>Generando contrato...</h3><p class="mt-2 text-gray-700" data-document-generation-message>Estamos creando el documento. Esto puede tardar algunos segundos.</p></div>
    </div>
    <script>
        document.querySelectorAll('[data-document-generation-form]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var revision = form.dataset.revision === 'true';
                var button = form.querySelector('button[type="submit"]');
                if (button) { button.disabled = true; button.textContent = revision ? 'Generando y publicando cambios...' : 'Generando contrato...'; }
                var overlay = document.querySelector('[data-document-generation-overlay]');
                if (overlay) {
                    overlay.querySelector('[data-document-generation-title]').textContent = revision ? 'Generando documento y publicando cambios...' : 'Generando contrato...';
                    overlay.querySelector('[data-document-generation-message]').textContent = revision ? 'Estamos creando el documento y aplicando los cambios al contrato definitivo.' : 'Estamos creando el documento. Esto puede tardar algunos segundos.';
                    overlay.classList.add('is-visible');
                }
            });
        });
    </script>
</x-app-layout>
