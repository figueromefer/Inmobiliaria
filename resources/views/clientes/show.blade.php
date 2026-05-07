<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Detalles del Cliente') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div>
                        <strong>Nombre:</strong>
                        <p>{{ $cliente->nombre }}</p>
                    </div>

                    <div>
                        <strong>RFC:</strong>
                        <p>{{ $cliente->rfc }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <strong>Domicilio:</strong>
                        <p>{{ $cliente->domicilio }}</p>
                    </div>

                    <div>
                        <strong>Teléfono fijo:</strong>
                        <p>{{ $cliente->fijo }}</p>
                    </div>

                    <div>
                        <strong>Celular:</strong>
                        <p>{{ $cliente->celular }}</p>
                    </div>

                    <div>
                        <strong>Correo:</strong>
                        <p>{{ $cliente->correo }}</p>
                    </div>

                    <div>
                        <strong>Banco:</strong>
                        <p>{{ $cliente->banco }}</p>
                    </div>

                    <div>
                        <strong>Cuenta:</strong>
                        <p>{{ $cliente->cuenta }}</p>
                    </div>

                    <div>
                        <strong>CLABE:</strong>
                        <p>{{ $cliente->clabe }}</p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-lg leading-6 text-gray-900">Notas</h3>
                        <p class="mt-1 text-sm text-gray-600 whitespace-pre-line">{{ $cliente->notas }}</p>
                    </div>

                </div>

                <div class="mt-8">
                    <h3 class="text-lg font-bold mb-2">Propiedades</h3>

                    @forelse($cliente->propiedades as $propiedad)
                        <div class="border p-3 mb-3 rounded bg-gray-50">
                            <div class="font-semibold">
                                {{ $propiedad->alias ?? 'Sin alias' }}
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ $propiedad->domicilio }}
                            </div>

                            <div class="mt-2 text-sm">
                                <strong>Contratos:</strong>
                                {{ $propiedad->contratos->count() }}
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500">Sin propiedades registradas</p>
                    @endforelse
                </div>

                <div class="mt-8">
                    <h3 class="text-lg font-bold mb-2">Contratos</h3>

                    @forelse($cliente->contratos as $contrato)
                        <div class="border p-3 mb-3 rounded">
                            <div>
                                <strong>Propiedad:</strong>
                                {{ $contrato->propiedad?->alias }}
                            </div>

                            <div>
                                <strong>Inquilino:</strong>
                                {{ $contrato->inquilino?->nombre }}
                            </div>

                            <div>
                                <strong>Periodo:</strong>
                                {{ $contrato->fecha_inicio }} - {{ $contrato->fecha_fin }}
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500">Sin contratos</p>
                    @endforelse
                </div>

                <div class="mt-8">
                    <h3 class="text-lg font-bold mb-2">Documentos</h3>

                    @forelse($cliente->documentos as $doc)
                        <div class="border p-2 mb-2 rounded flex justify-between">
                            <span>{{ $doc->nombre ?? 'Documento' }}</span>

                            <a href="{{ route('documentos.view', $doc) }}" class="text-blue-600">
                                Ver
                            </a>
                        </div>
                    @empty
                        <p class="text-gray-500">Sin documentos</p>
                    @endforelse
                </div>

                <div class="mt-6 flex justify-end space-x-4">
                    <a href="{{ route('clientes.index') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded">
                        Regresar
                    </a>
                    <a href="{{ route('clientes.edit', $cliente->pk_cliente) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
                        Editar
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
