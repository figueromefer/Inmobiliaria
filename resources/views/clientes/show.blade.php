<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Perfil del Cliente</h2>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto space-y-6">

        <!-- HEADER -->
        <div class="bg-white p-6 rounded shadow flex justify-between">
            <div>
                <h1 class="text-2xl font-bold">{{ $cliente->nombre }}</h1>
                <p class="text-sm text-gray-500">RFC: {{ $cliente->rfc ?: '—' }}</p>
            </div>

            <div class="space-x-2">
                <a href="{{ route('clientes.index') }}" class="bg-gray-200 px-4 py-2 rounded">Regresar</a>
                <a href="{{ route('clientes.edit', $cliente) }}" class="bg-blue-600 text-white px-4 py-2 rounded">Editar</a>
            </div>
        </div>

        <!-- RESUMEN -->
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded shadow">
                <div class="text-sm text-gray-500">Propiedades</div>
                <div class="text-2xl font-bold">{{ $cliente->propiedades->count() }}</div>
            </div>

            <div class="bg-white p-4 rounded shadow">
                <div class="text-sm text-gray-500">Contratos</div>
                <div class="text-2xl font-bold">{{ $cliente->contratos->count() }}</div>
            </div>

            <div class="bg-white p-4 rounded shadow">
                <div class="text-sm text-gray-500">Documentos</div>
                <div class="text-2xl font-bold">{{ $cliente->documentos->count() }}</div>
            </div>
        </div>

        <!-- PROPIEDADES -->
        <div class="bg-white p-6 rounded shadow">
            <div class="flex justify-between mb-4">
                <h3 class="font-bold">Propiedades</h3>
                <a href="{{ route('propiedades.create', ['cliente_id' => $cliente->pk_cliente]) }}" class="bg-gray-800 text-white px-3 py-1 rounded">
                    + Agregar
                </a>
            </div>

            @foreach($cliente->propiedades as $p)
                <div class="border p-3 mb-2 rounded">
                    <div class="flex justify-between items-center">
    <span class="font-semibold">{{ $p->alias }}</span>

    <span class="px-2 py-1 text-xs rounded text-white
        @if($p->estatus_informacion == 'pendiente_critico') bg-red-500
        @elseif($p->estatus_informacion == 'pendiente') bg-orange-400
        @else bg-green-500
        @endif
    ">
        {{ $p->estatus_informacion }}
    </span>
</div>
                    <div class="text-sm text-gray-500">{{ $p->domicilio }}</div>
                    <div class="text-sm">Contratos: {{ $p->contratos->count() }}</div>
                </div>
            @endforeach
        </div>

        <!-- CONTRATOS -->
        <div class="bg-white p-6 rounded shadow">
            <h3 class="font-bold mb-3">Contratos</h3>

            @foreach($cliente->contratos as $c)
                <div class="border p-3 mb-2 rounded">
                    <div><strong>Propiedad:</strong> {{ $c->propiedad?->alias }}</div>
                    <div><strong>Inquilino:</strong> {{ $c->inquilino?->nombre }}</div>
                    <div><strong>Periodo:</strong> {{ $c->fecha_inicio }} - {{ $c->fecha_fin }}</div>
                </div>
            @endforeach
        </div>

        <!-- DOCUMENTOS -->
        <div class="bg-white p-6 rounded shadow">
            <h3 class="font-bold mb-3">Documentos</h3>

             <a href="{{ route('documentos.create', ['cliente' => $cliente->pk_cliente]) }}"
       class="bg-gray-800 text-white px-3 py-1 rounded">
        + Subir documento
    </a>

            @foreach($cliente->documentos as $d)
                <div class="flex justify-between border p-2 mb-2 rounded">
                    <span>{{ $d->nombre ?? 'Documento' }}</span>
                    <a href="{{ route('documentos.view', $d) }}" class="text-blue-600">Ver</a>
                </div>
            @endforeach
        </div>

    </div>
</x-app-layout>