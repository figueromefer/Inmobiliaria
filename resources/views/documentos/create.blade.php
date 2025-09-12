<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nuevo Documento') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('documentos.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700" for="titulo">Título (opcional)</label>
                        <input type="text" name="titulo" id="titulo" value="{{ old('titulo') }}"
                               class="form-input mt-1 block w-full rounded-md shadow-sm border-gray-300" />
                        @error('titulo')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700" for="archivo">Archivo<span class="text-red-600">*</span></label>
                        <input type="file" name="archivo" id="archivo" required
                               class="form-input mt-1 block w-full rounded-md shadow-sm border-gray-300" />
                        @error('archivo')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700" for="fk_cliente">Asignar a cliente (opcional)</label>
                        <select name="fk_cliente" id="fk_cliente"
                                class="form-select mt-1 block w-full rounded-md shadow-sm border-gray-300">
                            <option value="">— Seleccione un cliente —</option>
                            @foreach($clientes as $cliente)
                                <option value="{{ $cliente->pk_cliente }}" {{ old('fk_cliente') == $cliente->pk_cliente ? 'selected' : '' }}>
                                    {{ $cliente->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @error('fk_cliente')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700" for="fk_propiedad">Asignar a propiedad (opcional)</label>
                        <select name="fk_propiedad" id="fk_propiedad"
                                class="form-select mt-1 block w-full rounded-md shadow-sm border-gray-300">
                            <option value="">— Seleccione una propiedad —</option>
                            @foreach($propiedades as $propiedad)
                                <option value="{{ $propiedad->pk_propiedad }}" {{ old('fk_propiedad') == $propiedad->pk_propiedad ? 'selected' : '' }}>
                                    {{ $propiedad->alias }}
                                </option>
                            @endforeach
                        </select>
                        @error('fk_propiedad')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Guardar
                        </button>
                        <a href="{{ route('documentos.index') }}"
                           class="ml-2 text-gray-600 hover:text-gray-800">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
