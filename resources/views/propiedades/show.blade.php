<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalles de la Propiedad</h2>
    </x-slot>

    <div class="py-6 max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div>
            <h3 class="font-semibold text-lg leading-6 text-gray-900">Alias</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $propiedad->alias }}</p>
        </div>

        <div>
            <h3 class="font-semibold text-lg leading-6 text-gray-900">Cliente</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $propiedad->cliente->nombre ?? 'N/A' }}</p>
        </div>

        <div>
            <h3 class="font-semibold text-lg leading-6 text-gray-900">Domicilio</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $propiedad->domicilio }}</p>
        </div>

        <!-- Repite para los demás campos -->

        <div>
            <h3 class="font-semibold text-lg leading-6 text-gray-900">siapa</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $propiedad->siapa }}</p>
        </div>

        <div>
            <h3 class="font-semibold text-lg leading-6 text-gray-900">CFE</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $propiedad->cfe }}</p>
        </div>

        <div>
            <h3 class="font-semibold text-lg leading-6 text-gray-900">Predial</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $propiedad->predial }}</p>
        </div>

        <div>
            <h3 class="font-semibold text-lg leading-6 text-gray-900">Banco para mantenimiento</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $propiedad->mantenimiento_banco }}</p>
        </div>

        <div>
            <h3 class="font-semibold text-lg leading-6 text-gray-900">Cuenta para mantenimiento</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $propiedad->mantenimiento_cuenta }}</p>
        </div>

        <div>
            <h3 class="font-semibold text-lg leading-6 text-gray-900">Monto de mantenimiento</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $propiedad->mantenimiento_monto }}</p>
        </div>

        <div>
            <h3 class="font-semibold text-lg leading-6 text-gray-900">Latitud</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $propiedad->latitud }}</p>
        </div>

        <div>
            <h3 class="font-semibold text-lg leading-6 text-gray-900">Longitud</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $propiedad->longitud }}</p>
        </div>

        <div class="flex justify-between">
            <a href="{{ route('propiedades.index') }}" class="btn btn-secondary">Volver</a>
            <a href="{{ route('propiedades.edit', ['propiedad' => $propiedad->pk_propiedad]) }}" class="btn btn-primary">Editar</a>
        </div>
    </div>
</x-app-layout>
