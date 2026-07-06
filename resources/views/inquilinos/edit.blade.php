<x-app-layout>
<x-slot name="header">
<h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar Inquilino</h2>
</x-slot>
<div class="py-6">
<div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
<div class="bg-white rounded-xl shadow-sm p-6">
<form method="POST" action="{{ route('inquilinos.update',$inquilino) }}">
@csrf
@method('PUT')
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div><label>Nombre</label><input type="text" name="nombre" value="{{ old('nombre',$inquilino->nombre) }}" class="w-full rounded border-gray-300"></div>
<div><label>Nacionalidad</label><input type="text" name="nacionalidad" value="{{ old('nacionalidad',$inquilino->nacionalidad) }}" class="w-full rounded border-gray-300"></div>
<div><label>Teléfono</label><input type="text" name="telefono" value="{{ old('telefono',$inquilino->telefono) }}" inputmode="tel" pattern="[+0-9 ]+" placeholder="+52 3312345678" class="w-full rounded border-gray-300"></div>
<div><label>Correo</label><input type="email" name="correo" value="{{ old('correo',$inquilino->correo) }}" class="w-full rounded border-gray-300"></div>
<div class="md:col-span-2"><label>Domicilio</label><textarea name="domicilio" rows="3" class="w-full rounded border-gray-300">{{ old('domicilio',$inquilino->domicilio) }}</textarea></div>
</div>
<div class="flex justify-end gap-3 mt-5">
<a href="{{ route('inquilinos.show',$inquilino) }}" class="px-4 py-2 border rounded">Cancelar</a>
<button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Guardar cambios</button>
</div>
</form>
</div>
</div>
</div>
</x-app-layout>
