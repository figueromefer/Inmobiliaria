<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Borradores internos de contrato y solicitudes públicas</h2>
                <p class="mt-1 text-sm text-gray-500">Captura técnica V1; incluye solicitudes internas, públicas y legacy.</p>
            </div>
            <a href="{{ route('contratos.borradores.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Nuevo borrador</a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto mt-6 bg-white p-6 rounded-lg shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3">ID</th>
                        <th class="text-left px-4 py-3">Origen</th>
                        <th class="text-left px-4 py-3">Estado</th>
                        <th class="text-left px-4 py-3">Versión actual</th>
                        <th class="text-left px-4 py-3">Creado por</th>
                        <th class="text-left px-4 py-3">Actualizado</th>
                        <th class="text-left px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($drafts as $draft)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium">#{{ $draft->id }}</td>
                            <td class="px-4 py-3"><span class="rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-800">{{ $draft->source }}</span></td>
                            <td class="px-4 py-3"><span class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-700">{{ $draft->status }}</span></td>
                            <td class="px-4 py-3">{{ $draft->currentVersion?->draft_version ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $draft->createdBy?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $draft->updated_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-3"><a class="inline-flex bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-3 py-1 rounded" href="{{ route('contratos.borradores.show', $draft) }}">Ver borrador</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Aún no hay solicitudes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $drafts->links() }}</div>
    </div>
</x-app-layout>
