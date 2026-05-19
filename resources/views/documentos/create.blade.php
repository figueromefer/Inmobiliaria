<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nuevo Documento') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if ($errors->any())
                    <div class="mb-4 rounded bg-red-100 border border-red-400 text-red-700 px-4 py-3">
                        <strong>Revisa los siguientes campos:</strong>
                        <ul class="mt-2 list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('documentos.store') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="titulo">Título</label>
                        <input type="text" name="titulo" id="titulo" value="{{ old('titulo') }}"
                            class="form-input mt-1 block w-full rounded-md shadow-sm border-gray-300"
                            placeholder="Ej. Recibo predial 2026" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="tipo">Tipo de documento <span class="text-red-600">*</span></label>
                        <select name="tipo" id="tipo" required class="form-select mt-1 block w-full rounded-md shadow-sm border-gray-300">
                            <option value="">— Seleccione tipo —</option>
                            @foreach($tipos ?? [] as $key => $label)
                                <option value="{{ $key }}" @selected(old('tipo') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="archivo">Archivo <span class="text-red-600">*</span></label>
                        <input type="file" name="archivo" id="archivo" required
                            class="form-input mt-1 block w-full rounded-md shadow-sm border-gray-300" />
                        <p class="text-xs text-gray-500 mt-1">Máximo 10MB.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="fk_cliente">Asignar a cliente</label>
                            <select name="fk_cliente" id="fk_cliente"
                                class="form-select mt-1 block w-full rounded-md shadow-sm border-gray-300">
                                <option value="">— Sin cliente —</option>
                                @foreach($clientes as $cliente)
                                    <option value="{{ $cliente->pk_cliente }}" @selected(old('fk_cliente', $clienteId ?? null) == $cliente->pk_cliente)>
                                        {{ $cliente->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="fk_propiedad">Asignar a propiedad</label>
                            <select name="fk_propiedad" id="fk_propiedad"
                                class="form-select mt-1 block w-full rounded-md shadow-sm border-gray-300">
                                <option value="">— Sin propiedad —</option>
                                @foreach($propiedades as $propiedad)
                                    <option value="{{ $propiedad->pk_propiedad }}" @selected(old('fk_propiedad', $propiedadId ?? null) == $propiedad->pk_propiedad)>
                                        {{ $propiedad->alias }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('documentos.index') }}" class="px-4 py-2 rounded border text-gray-600 hover:bg-gray-50">Cancelar</a>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Guardar documento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
