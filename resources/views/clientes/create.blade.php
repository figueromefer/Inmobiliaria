<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nuevo Cliente') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('clientes.store') }}">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Nombre -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700" for="nombre">Nombre</label>
                            <input name="nombre" id="nombre" type="text" value="{{ old('nombre') }}" required
                                class="form-input rounded-md shadow-sm mt-1 block w-full" />
                        </div>

                        <!-- RFC -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700" for="rfc">RFC</label>
                            <input name="rfc" id="rfc" type="text" value="{{ old('rfc') }}" required
                                class="form-input rounded-md shadow-sm mt-1 block w-full" />
                        </div>

                        <!-- Domicilio -->
                        <div class="md:col-span-2">
                            <label class="block font-medium text-sm text-gray-700" for="domicilio">Domicilio</label>
                            <input name="domicilio" id="domicilio" type="text" value="{{ old('domicilio') }}" required
                                class="form-input rounded-md shadow-sm mt-1 block w-full" />
                        </div>

                        <!-- Teléfono fijo -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700" for="fijo">Teléfono fijo</label>
                            <input name="fijo" id="fijo" type="text" value="{{ old('fijo') }}"
                                class="form-input rounded-md shadow-sm mt-1 block w-full" />
                        </div>

                        <!-- Teléfono celular -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700" for="celular">Celular</label>
                            <input name="celular" id="celular" type="text" value="{{ old('celular') }}"
                                class="form-input rounded-md shadow-sm mt-1 block w-full" />
                        </div>

                        <!-- Correo electrónico -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700" for="correo">Correo</label>
                            <input name="correo" id="correo" type="email" value="{{ old('correo') }}"
                                class="form-input rounded-md shadow-sm mt-1 block w-full" />
                        </div>

                        <!-- Banco -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700" for="banco">Banco</label>
                            <input name="banco" id="banco" type="text" value="{{ old('banco') }}"
                                class="form-input rounded-md shadow-sm mt-1 block w-full" />
                        </div>

                        <!-- Cuenta -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700" for="cuenta">Cuenta</label>
                            <input name="cuenta" id="cuenta" type="text" value="{{ old('cuenta') }}"
                                class="form-input rounded-md shadow-sm mt-1 block w-full" />
                        </div>

                        <!-- CLABE -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700" for="clabe">CLABE</label>
                            <input name="clabe" id="clabe" type="text" value="{{ old('clabe') }}"
                                class="form-input rounded-md shadow-sm mt-1 block w-full" />
                        </div>

                         <!-- Notas -->
                       <div class="md:col-span-2">
                            <label for="notas" class="block font-medium text-sm text-gray-700">Notas</label>
                            <textarea name="notas" id="notas" rows="4" 
                                class="form-textarea rounded-md shadow-sm mt-1 block w-full">{{ old('notas', $cliente->notas ?? '') }}</textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <a href="{{ route('clientes.index') }}" class="text-gray-600 hover:underline mr-4">
                            Cancelar
                        </a>
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
