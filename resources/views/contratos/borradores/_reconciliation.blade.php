@php
    $entities = [
        'cliente' => ['label' => 'Cliente / arrendador', 'relation' => 'cliente', 'query' => 'cliente_q', 'id' => 'pk_cliente'],
        'propiedad' => ['label' => 'Propiedad', 'relation' => 'propiedad', 'query' => 'propiedad_q', 'id' => 'pk_propiedad'],
        'inquilino' => ['label' => 'Inquilino / arrendatario', 'relation' => 'inquilino', 'query' => 'inquilino_q', 'id' => 'id'],
    ];
    $statusClass = ['coincide' => 'bg-green-100 text-green-800', 'diferente' => 'bg-orange-100 text-orange-800', 'sin dato en draft' => 'bg-gray-100 text-gray-700', 'sin dato en maestro' => 'bg-gray-100 text-gray-700'];
@endphp

<section class="bg-white rounded-lg shadow p-5 space-y-6">
    <div>
        <h3 class="font-semibold text-lg text-gray-800">Conciliación</h3>
        <p class="mt-1 text-sm text-gray-600">Los vínculos son operativos y requieren revisión humana. No copian datos, no alteran el snapshot contractual ni generan una versión nueva.</p>
    </div>

    @foreach($entities as $key => $entity)
        @php($linked = $draft->{$entity['relation']})
        <article class="border rounded-lg p-4 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h4 class="font-semibold text-gray-800">{{ $entity['label'] }}</h4>
                    <p class="text-sm {{ $linked ? 'text-green-700' : 'text-gray-500' }}">{{ $linked ? 'Vinculado' : 'No vinculado' }}</p>
                </div>
                @if($linked)
                    <form method="POST" action="{{ route('contratos.borradores.reconciliation.unlink', [$draft, $key]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="bg-gray-500 hover:bg-gray-700 text-white font-bold text-xs px-3 py-2 rounded">Quitar vínculo</button>
                    </form>
                @endif
            </div>

            @if($linked)
                <div class="rounded bg-gray-50 p-3 text-sm">
                    @if($key === 'cliente')
                        <span class="font-medium">#{{ $linked->pk_cliente }} · {{ $linked->nombre }}</span><span class="text-gray-600"> · RFC: {{ $linked->rfc ?: '—' }}</span>
                    @elseif($key === 'propiedad')
                        <span class="font-medium">#{{ $linked->pk_propiedad }} · {{ $linked->alias ?: 'Sin alias' }}</span><span class="text-gray-600"> · {{ $linked->domicilio ?: '—' }}</span>
                    @else
                        <span class="font-medium">#{{ $linked->id }} · {{ $linked->nombre }}</span><span class="text-gray-600"> · {{ $linked->correo ?: '—' }}</span>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b text-left text-gray-500"><tr><th class="py-2 pr-3">Campo</th><th class="py-2 pr-3">Snapshot del draft</th><th class="py-2 pr-3">Maestro actual</th><th class="py-2">Resultado</th></tr></thead>
                        <tbody>
                            @foreach($comparisons[$key] as $comparison)
                                <tr class="border-b"><td class="py-2 pr-3 font-medium">{{ $comparison['label'] }}</td><td class="py-2 pr-3">{{ $comparison['draft'] ?: '—' }}</td><td class="py-2 pr-3">{{ $comparison['master'] ?: '—' }}</td><td class="py-2"><span class="rounded-full px-2 py-1 text-xs {{ $statusClass[$comparison['status']] }}">{{ $comparison['status'] }}</span></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <form method="GET" action="{{ route('contratos.borradores.show', $draft) }}" class="flex flex-wrap items-end gap-2">
                <div class="flex-1 min-w-56"><label class="block text-sm font-medium" for="{{ $key }}_q">Buscar {{ strtolower($entity['label']) }}</label><input id="{{ $key }}_q" name="{{ $entity['query'] }}" value="{{ $searches[$key]['query'] }}" maxlength="100" class="mt-1 w-full border rounded px-3 py-2" placeholder="Escribe nombre, correo, RFC, alias o domicilio"></div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm px-4 py-2 rounded">Buscar</button>
            </form>

            @if($searches[$key]['results'])
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b text-left text-gray-500"><tr><th class="py-2 pr-3">Registro</th><th class="py-2 pr-3">Datos de referencia</th><th class="py-2">Acción</th></tr></thead>
                        <tbody>
                            @forelse($searches[$key]['results'] as $result)
                                <tr class="border-b">
                                    @if($key === 'cliente')
                                        <td class="py-2 pr-3 font-medium">#{{ $result->pk_cliente }} · {{ $result->nombre }}</td><td class="py-2 pr-3">RFC: {{ $result->rfc ?: '—' }} · {{ $result->correo ?: '—' }}</td>
                                    @elseif($key === 'propiedad')
                                        <td class="py-2 pr-3 font-medium">#{{ $result->pk_propiedad }} · {{ $result->alias ?: 'Sin alias' }}</td><td class="py-2 pr-3">{{ $result->domicilio ?: '—' }}</td>
                                    @else
                                        <td class="py-2 pr-3 font-medium">#{{ $result->id }} · {{ $result->nombre }}</td><td class="py-2 pr-3">{{ $result->correo ?: $result->telefono ?: '—' }}</td>
                                    @endif
                                    <td class="py-2"><form method="POST" action="{{ route('contratos.borradores.reconciliation.link', [$draft, $key]) }}">@csrf<input type="hidden" name="entity_id" value="{{ $result->{$entity['id']} }}"><button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-3 py-1 rounded">Vincular</button></form></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-3 text-center text-gray-500">Sin coincidencias para esta búsqueda.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div>{{ $searches[$key]['results']->links() }}</div>
            @endif
        </article>
    @endforeach
</section>
