<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Nuevo ticket
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-6">
        <form method="POST" action="{{ route('tickets.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm mb-1">Propiedad</label>
                <select name="property_id" class="w-full border rounded-md px-3 py-2" required>
                    <option value="">Selecciona…</option>
                    @foreach($properties as $p)
                        <option value="{{ $p->id ?? $p->pk_propiedad }}" @selected(old('property_id')==($p->id ?? $p->pk_propiedad))>
                            {{ $p->alias }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm mb-1">Título</label>
                <input name="title" value="{{ old('title') }}" class="w-full border rounded-md px-3 py-2" required>
            </div>

            <div>
                <label class="block text-sm mb-1">Descripción</label>
                <textarea name="description" rows="4" class="w-full border rounded-md px-3 py-2">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm mb-1">Prioridad</label>
                    <select name="priority" class="w-full border rounded-md px-3 py-2">
                        <option value="">—</option>
                        <option value="low" @selected(old('priority')==='low')>Baja</option>
                        <option value="medium" @selected(old('priority')==='medium')>Media</option>
                        <option value="high" @selected(old('priority')==='high')>Alta</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1">Asignar a</label>
                    <select name="assigned_to" class="w-full border rounded-md px-3 py-2">
                        <option value="">—</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(old('assigned_to')==$u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1">Fecha vencimiento</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}" class="w-full border rounded-md px-3 py-2">
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('tickets.index') }}" class="px-3 py-2 border rounded-md">Cancelar</a>
                <button class="px-4 py-2 bg-blue-600 text-white rounded-md">Crear</button>
            </div>
        </form>
    </div>
</x-app-layout>
