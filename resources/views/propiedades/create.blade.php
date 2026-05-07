<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva Propiedad</h2>
    </x-slot>

    <div class="py-6 max-w-3xl mx-auto sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('propiedades.store') }}">
            @csrf

            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label for="fk_cliente" class="block font-medium text-sm text-gray-700">Cliente</label>
                    <select name="fk_cliente" id="fk_cliente" class="form-select rounded-md shadow-sm mt-1 block w-full">
                        <option value="">Seleccione un cliente</option>
                        @foreach($clientes as $cliente)
                            <option value="{{ $cliente->pk_cliente }}"
                                {{ (old('fk_cliente') ?? $clientePreseleccionado ?? '') == $cliente->pk_cliente ? 'selected' : '' }}>
                                {{ $cliente->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="alias" class="block font-medium text-sm text-gray-700">Alias</label>
                    <input type="text" name="alias" id="alias" value="{{ old('alias') }}" class="form-input mt-1 block w-full" />
                </div>

                <div>
                    <label for="domicilio" class="block font-medium text-sm text-gray-700">Domicilio</label>
                    <input type="text" name="domicilio" id="domicilio" value="{{ old('domicilio') }}" class="form-input mt-1 block w-full" />
                </div>

                <div>
                    <label for="siapa" class="block font-medium text-sm text-gray-700">Agua</label>
                    <input type="text" name="siapa" id="siapa" value="{{ old('siapa') }}" class="form-input mt-1 block w-full" />
                </div>

                <div>
                    <label for="cfe" class="block font-medium text-sm text-gray-700">CFE</label>
                    <input type="text" name="cfe" id="cfe" value="{{ old('cfe') }}" class="form-input mt-1 block w-full" />
                </div>

                <div>
                    <label for="predial" class="block font-medium text-sm text-gray-700">Predial</label>
                    <input type="text" name="predial" id="predial" value="{{ old('predial') }}" class="form-input mt-1 block w-full" />
                </div>

                <div class="bg-gray-50 p-4 rounded">
                    <h3 class="font-semibold mb-2">Datos para mantenimiento</h3>

                    <input type="text" name="mantenimiento_banco" placeholder="Banco" value="{{ old('mantenimiento_banco') }}" class="form-input mb-2 w-full" />
                    <input type="text" name="mantenimiento_cuenta" placeholder="Cuenta" value="{{ old('mantenimiento_cuenta') }}" class="form-input mb-2 w-full" />
                    <input type="number" step="0.01" name="mantenimiento_monto" placeholder="Monto" value="{{ old('mantenimiento_monto') }}" class="form-input w-full" />
                </div>

                <div>
                    <label>Latitud</label>
                    <input type="text" name="latitud" value="{{ old('latitud') }}" class="form-input w-full" />
                </div>

                <div>
                    <label>Longitud</label>
                    <input type="text" name="longitud" value="{{ old('longitud') }}" class="form-input w-full" />
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Estatus de información</label>

                    <select name="estatus_informacion" class="form-select mt-1 w-full">
                        <option value="pendiente_critico">🔴 Pendiente crítico</option>
                        <option value="pendiente">🟠 Pendiente</option>
                        <option value="completo">🟢 Completo</option>
                    </select>
                </div>

                <div class="flex justify-end">
                    <button class="bg-blue-600 text-white px-4 py-2 rounded">Guardar</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
