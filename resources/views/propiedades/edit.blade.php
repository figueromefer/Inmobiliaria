<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar Propiedad</h2>
    </x-slot>

    <div class="py-6 max-w-3xl mx-auto sm:px-6 lg:px-8">
        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                <p class="font-semibold mb-2">No se pudo guardar la propiedad. Revisa estos campos:</p>
                <ul class="list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('propiedades.update', $propiedad) }}" class="bg-white border rounded-lg p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label for="fk_cliente" class="block font-medium text-sm text-gray-700">Cliente <span class="text-red-600">*</span></label>
                <select name="fk_cliente" id="fk_cliente" class="form-select rounded-md shadow-sm mt-1 block w-full border-gray-300">
                    <option value="">Seleccione un cliente</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->pk_cliente }}" {{ (old('fk_cliente', $propiedad->fk_cliente) == $cliente->pk_cliente) ? 'selected' : '' }}>
                            {{ $cliente->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('fk_cliente') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="alias" class="block font-medium text-sm text-gray-700">Alias <span class="text-red-600">*</span></label>
                <input type="text" name="alias" id="alias" value="{{ old('alias', $propiedad->alias) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                @error('alias') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="estatus_informacion" class="block font-medium text-sm text-gray-700">Estatus de información <span class="text-red-600">*</span></label>
                <select name="estatus_informacion" id="estatus_informacion" class="form-select rounded-md shadow-sm mt-1 block w-full border-gray-300">
                    @foreach([
                        'completo' => 'Completo',
                        'pendiente_completar' => 'Pendiente de completar',
                        'pendiente_critico' => 'Pendiente crítico',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(old('estatus_informacion', $propiedad->estatus_informacion ?: 'completo') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('estatus_informacion') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="domicilio" class="block font-medium text-sm text-gray-700">Domicilio</label>
                <input type="text" name="domicilio" id="domicilio" value="{{ old('domicilio', $propiedad->domicilio) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                @error('domicilio') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="calle" class="block font-medium text-sm text-gray-700">Calle</label>
                    <input type="text" name="calle" id="calle" value="{{ old('calle', $propiedad->calle) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                </div>
                <div>
                    <label for="numero_exterior" class="block font-medium text-sm text-gray-700">Número exterior</label>
                    <input type="text" name="numero_exterior" id="numero_exterior" value="{{ old('numero_exterior', $propiedad->numero_exterior) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                </div>
                <div>
                    <label for="numero_interior" class="block font-medium text-sm text-gray-700">Número interior</label>
                    <input type="text" name="numero_interior" id="numero_interior" value="{{ old('numero_interior', $propiedad->numero_interior) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                </div>
                <div>
                    <label for="colonia" class="block font-medium text-sm text-gray-700">Colonia</label>
                    <input type="text" name="colonia" id="colonia" value="{{ old('colonia', $propiedad->colonia) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                </div>
                <div>
                    <label for="codigo_postal" class="block font-medium text-sm text-gray-700">Código postal</label>
                    <input type="text" name="codigo_postal" id="codigo_postal" value="{{ old('codigo_postal', $propiedad->codigo_postal) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                </div>
                <div>
                    <label for="municipio" class="block font-medium text-sm text-gray-700">Municipio</label>
                    <input type="text" name="municipio" id="municipio" value="{{ old('municipio', $propiedad->municipio) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                </div>
                <div>
                    <label for="estado" class="block font-medium text-sm text-gray-700">Estado</label>
                    <input type="text" name="estado" id="estado" value="{{ old('estado', $propiedad->estado) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                </div>
            </div>

            <div>
                <label for="siapa" class="block font-medium text-sm text-gray-700">SIAPA</label>
                <input type="text" name="siapa" id="siapa" value="{{ old('siapa', $propiedad->siapa) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                @error('siapa') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="cfe" class="block font-medium text-sm text-gray-700">CFE</label>
                <input type="text" name="cfe" id="cfe" value="{{ old('cfe', $propiedad->cfe) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                @error('cfe') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="predial" class="block font-medium text-sm text-gray-700">Predial</label>
                <input type="text" name="predial" id="predial" value="{{ old('predial', $propiedad->predial) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                @error('predial') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="mantenimiento_banco" class="block font-medium text-sm text-gray-700">Banco para mantenimiento</label>
                    <input type="text" name="mantenimiento_banco" id="mantenimiento_banco" value="{{ old('mantenimiento_banco', $propiedad->mantenimiento_banco) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                    @error('mantenimiento_banco') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="mantenimiento_cuenta" class="block font-medium text-sm text-gray-700">Cuenta para mantenimiento</label>
                    <input type="text" name="mantenimiento_cuenta" id="mantenimiento_cuenta" value="{{ old('mantenimiento_cuenta', $propiedad->mantenimiento_cuenta) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                    @error('mantenimiento_cuenta') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="mantenimiento_monto" class="block font-medium text-sm text-gray-700">Monto de mantenimiento</label>
                    <input type="number" step="0.01" name="mantenimiento_monto" id="mantenimiento_monto" value="{{ old('mantenimiento_monto', $propiedad->mantenimiento_monto) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                    @error('mantenimiento_monto') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="mantenimiento_fecha_pago" class="block font-medium text-sm text-gray-700">Fecha pago mantenimiento</label>
                    <input type="date" name="mantenimiento_fecha_pago" id="mantenimiento_fecha_pago" value="{{ old('mantenimiento_fecha_pago', optional($propiedad->mantenimiento_fecha_pago)->format('Y-m-d')) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                    @error('mantenimiento_fecha_pago') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="latitud" class="block font-medium text-sm text-gray-700">Latitud</label>
                    <input type="text" name="latitud" id="latitud" value="{{ old('latitud', $propiedad->latitud) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                    @error('latitud') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="longitud" class="block font-medium text-sm text-gray-700">Longitud</label>
                    <input type="text" name="longitud" id="longitud" value="{{ old('longitud', $propiedad->longitud) }}" class="form-input rounded-md shadow-sm mt-1 block w-full border-gray-300" />
                    @error('longitud') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('propiedades.show', $propiedad) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">Cancelar</a>
                <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">Guardar cambios</button>
            </div>
        </form>
    </div>
</x-app-layout>
