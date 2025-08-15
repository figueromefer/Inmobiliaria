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
                            <option value="{{ $cliente->pk_cliente }}" {{ old('fk_cliente') == $cliente->pk_cliente ? 'selected' : '' }}>
                                {{ $cliente->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('fk_cliente') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="alias" class="block font-medium text-sm text-gray-700">Alias</label>
                    <input type="text" name="alias" id="alias" value="{{ old('alias') }}" class="form-input rounded-md shadow-sm mt-1 block w-full" />
                    @error('alias') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="domicilio" class="block font-medium text-sm text-gray-700">Domicilio</label>
                    <input type="text" name="domicilio" id="domicilio" value="{{ old('domicilio') }}" class="form-input rounded-md shadow-sm mt-1 block w-full" />
                    @error('domicilio') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="siapa" class="block font-medium text-sm text-gray-700">SIPA</label>
                    <input type="text" name="siapa" id="siapa" value="{{ old('siapa') }}" class="form-input rounded-md shadow-sm mt-1 block w-full" />
                    @error('siapa') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cfe" class="block font-medium text-sm text-gray-700">CFE</label>
                    <input type="text" name="cfe" id="cfe" value="{{ old('cfe') }}" class="form-input rounded-md shadow-sm mt-1 block w-full" />
                    @error('cfe') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="predial" class="block font-medium text-sm text-gray-700">Predial</label>
                    <input type="text" name="predial" id="predial" value="{{ old('predial') }}" class="form-input rounded-md shadow-sm mt-1 block w-full" />
                    @error('predial') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mantenimiento_banco" class="block font-medium text-sm text-gray-700">Banco para mantenimiento</label>
                    <input type="text" name="mantenimiento_banco" id="mantenimiento_banco" value="{{ old('mantenimiento_banco') }}" class="form-input rounded-md shadow-sm mt-1 block w-full" />
                    @error('mantenimiento_banco') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mantenimiento_cuenta" class="block font-medium text-sm text-gray-700">Cuenta para mantenimiento</label>
                    <input type="text" name="mantenimiento_cuenta" id="mantenimiento_cuenta" value="{{ old('mantenimiento_cuenta') }}" class="form-input rounded-md shadow-sm mt-1 block w-full" />
                    @error('mantenimiento_cuenta') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mantenimiento_monto" class="block font-medium text-sm text-gray-700">Monto de mantenimiento</label>
                    <input type="number" step="0.01" name="mantenimiento_monto" id="mantenimiento_monto" value="{{ old('mantenimiento_monto') }}" class="form-input rounded-md shadow-sm mt-1 block w-full" />
                    @error('mantenimiento_monto') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="latitud" class="block font-medium text-sm text-gray-700">Latitud</label>
                    <input type="text" name="latitud" id="latitud" value="{{ old('latitud') }}" class="form-input rounded-md shadow-sm mt-1 block w-full" />
                    @error('latitud') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="longitud" class="block font-medium text-sm text-gray-700">Longitud</label>
                    <input type="text" name="longitud" id="longitud" value="{{ old('longitud') }}" class="form-input rounded-md shadow-sm mt-1 block w-full" />
                    @error('longitud') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
