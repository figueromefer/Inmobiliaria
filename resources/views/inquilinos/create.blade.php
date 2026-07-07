<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo Inquilino</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-xl shadow-sm p-6">
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

                <form method="POST" action="{{ route('inquilinos.store') }}" class="space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="nombre">Nombre <span class="text-red-600">*</span></label>
                            <input type="text" name="nombre" id="nombre" value="{{ old('nombre') }}" required
                                class="js-phone-input form-input mt-1 block w-full rounded-md shadow-sm border-gray-300" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="nacionalidad">Nacionalidad</label>
                            <input type="text" name="nacionalidad" id="nacionalidad" value="{{ old('nacionalidad') }}"
                                class="form-input mt-1 block w-full rounded-md shadow-sm border-gray-300" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="telefono">Teléfono</label>
                            <input type="text" name="telefono" id="telefono" value="{{ old('telefono') }}"
                                inputmode="tel" pattern="[+0-9 ]+" placeholder="+52 3312345678"
                                class="form-input mt-1 block w-full rounded-md shadow-sm border-gray-300" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="correo">Correo</label>
                            <input type="email" name="correo" id="correo" value="{{ old('correo') }}"
                                class="form-input mt-1 block w-full rounded-md shadow-sm border-gray-300" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700" for="domicilio">Domicilio</label>
                            <textarea name="domicilio" id="domicilio" rows="3"
                                class="form-textarea mt-1 block w-full rounded-md shadow-sm border-gray-300">{{ old('domicilio') }}</textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('inquilinos.index') }}" class="px-4 py-2 rounded border text-gray-600 hover:bg-gray-50">Cancelar</a>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Guardar inquilino
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
