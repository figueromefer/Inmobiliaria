<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Editar ticket
        </h2>
    </x-slot>

    <div class="bg-white rounded-xl shadow-sm border p-6 max-w-3xl">
        <form method="POST" action="{{ route('tickets.update',$ticket) }}" class="space-y-4">
            @csrf @method('PUT')

            <div>
                <label class="block text-sm mb-1">Propiedad</label>
                <select name="property_id" class="js-searchable-select w-full border rounded-md px-3 py-2" required>
                    @foreach($properties as $p)
                        <option value="{{ $p->id ?? $p->pk_propiedad }}" @selected(old('property_id',$ticket->property_id)==($p->id ?? $p->pk_propiedad))>
                            {{ $p->alias }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm mb-1">Título</label>
                <input name="title" value="{{ old('title',$ticket->title) }}" class="w-full border rounded-md px-3 py-2" required>
            </div>

            <div>
                <label class="block text-sm mb-1">Descripción</label>
                <textarea name="description" rows="4" class="w-full border rounded-md px-3 py-2">{{ old('description',$ticket->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-sm mb-1">Prioridad</label>
                    <select name="priority" class="w-full border rounded-md px-3 py-2">
                        <option value="">—</option>
                        @foreach(['low'=>'Baja','medium'=>'Media','high'=>'Alta'] as $k=>$v)
                            <option value="{{ $k }}" @selected(old('priority',$ticket->priority)===$k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm mb-1">Asignar a</label>
                    <select name="assigned_to" class="js-searchable-select w-full border rounded-md px-3 py-2">
                        <option value="">—</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(old('assigned_to',$ticket->assigned_to)==$u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1">Vencimiento</label>
                    <input type="date" name="due_date" value="{{ old('due_date', optional($ticket->due_date)->format('Y-m-d')) }}" class="w-full border rounded-md px-3 py-2">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm mb-1">Estatus</label>
                    <select name="status" class="w-full border rounded-md px-3 py-2">
                        <option value="open" @selected(old('status',$ticket->status)==='open')>Abierto</option>
                        <option value="in_progress" @selected(old('status',$ticket->status)==='in_progress')>En proceso</option>
                        <option value="completed" @selected(old('status',$ticket->status)==='completed')>Completado</option>
                        <option value="canceled" @selected(old('status',$ticket->status)==='canceled')>Cancelado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1">Cerrado</label>
                    <input type="text" class="w-full border rounded-md px-3 py-2 bg-gray-50" value="{{ $ticket->closed_at? $ticket->closed_at->format('d/m/Y H:i') : '—' }}" disabled>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('tickets.show',$ticket) }}" class="px-3 py-2 border rounded-md">Cancelar</a>
                <button class="px-4 py-2 bg-blue-600 text-white rounded-md">Guardar</button>
            </div>
        </form>
    </div>
</x-app-layout>
