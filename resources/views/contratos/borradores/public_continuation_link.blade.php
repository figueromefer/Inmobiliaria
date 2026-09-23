<x-app-layout>
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
            <button type="button" id="copy-continuation-link" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Copiar enlace</button>
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
