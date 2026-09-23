<x-app-layout>
    <style>
        .client-link-button { align-items:center; background-color:#a16207; border:1px solid #854d0e; border-radius:.5rem; color:#fff; cursor:pointer; display:inline-flex; font-weight:700; line-height:1.25; padding:.625rem 1rem; text-decoration:none; }
        .client-link-button:hover { background-color:#854d0e; color:#fff; }
        .client-link-button-secondary { align-items:center; background-color:#1d4ed8; border:1px solid #1e40af; border-radius:.5rem; color:#fff; cursor:pointer; display:inline-flex; font-weight:700; line-height:1.25; padding:.625rem 1rem; text-decoration:none; }
        .client-link-button-secondary:hover { background-color:#1e40af; color:#fff; }
        .client-link-button:disabled, .client-link-button-secondary:disabled { cursor:not-allowed; opacity:.6; }
    </style>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Nuevo enlace de continuación</h2>
                <p class="text-sm text-gray-500">Solicitud del cliente</p>
            </div>
            <a href="{{ route('contratos.borradores.show', $draft) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">Volver al borrador</a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto mt-6">
        <section class="bg-white rounded-lg shadow p-6 space-y-4">
            <div class="rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                El enlace anterior dejó de funcionar. Comparte este enlace sólo con la persona que continuará la solicitud.
            </div>
            <div>
                <label for="continuation-link" class="block text-sm font-medium text-gray-700">Nuevo enlace de continuación</label>
                <input id="continuation-link" type="text" readonly value="{{ $continuationUrl }}" class="mt-1 w-full rounded border-gray-300 font-mono text-xs">
            </div>
            <div class="flex flex-wrap gap-3"><button type="button" id="copy-continuation-link" class="client-link-button-secondary bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Copiar enlace</button><a href="{{ $continuationUrl }}" target="_blank" rel="noopener" class="client-link-button-secondary">Abrir formulario</a></div>
            <p id="copy-status" class="text-sm text-gray-600" aria-live="polite"></p>
        </section>
    </div>

    <script>
        document.getElementById('copy-continuation-link').addEventListener('click', async function () {
            const input = document.getElementById('continuation-link');
            const status = document.getElementById('copy-status');

            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(input.value);
                status.textContent = 'Enlace copiado.';
                return;
            }

            input.select();
            document.execCommand('copy');
            status.textContent = 'Enlace copiado.';
        });
    </script>
</x-app-layout>
