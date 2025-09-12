<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Tickets
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-6 bg-white">
        <a href="{{ route('tickets.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mt-4 ml-2 inline-block">+ Nuevo ticket</a>
        <div class="p-4 border-b">

            <form method="GET" class="grid md:grid-cols-5 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Propiedad</label>
                    <select name="property_id" class="w-full border rounded-md px-2 py-1.5">
                        <option value="">Todas</option>
                        @foreach($properties as $p)
                            {{-- Si tu PK es pk_propiedad, usa $p->pk_propiedad o aliaséalas en el controlador --}}
                            <option value="{{ $p->id ?? $p->pk_propiedad }}" @selected(request('property_id')==($p->id ?? $p->pk_propiedad))>
                                {{ $p->alias }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Estatus</label>
                    <select name="status" class="w-full border rounded-md px-2 py-1.5">
                        <option value="">Todos</option>
                        <option value="open" @selected(request('status')==='open')>Abierto</option>
                        <option value="in_progress" @selected(request('status')==='in_progress')>En proceso</option>
                        <option value="completed" @selected(request('status')==='completed')>Completado</option>
                        <option value="canceled" @selected(request('status')==='canceled')>Cancelado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Prioridad</label>
                    <select name="priority" class="w-full border rounded-md px-2 py-1.5">
                        <option value="">Todas</option>
                        <option value="low" @selected(request('priority')==='low')>Baja</option>
                        <option value="medium" @selected(request('priority')==='medium')>Media</option>
                        <option value="high" @selected(request('priority')==='high')>Alta</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Buscar</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="w-full border rounded-md px-3 py-1.5" placeholder="Título o descripción...">
                </div>
                <div class="md:col-span-5 flex justify-end gap-2">
                    <a href="{{ route('tickets.index') }}" class="px-3 py-1.5 rounded-md border">Limpiar</a>
                    <button class="px-3 py-1.5 rounded-md bg-blue-600 text-white">Filtrar</button>
                </div>
            </form>
        </div>

        <div class="divide-y">
            @forelse($tickets as $t)
                <a href="{{ route('tickets.show',$t) }}" class="block p-4 hover:bg-gray-50">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="font-medium truncate">{{ $t->title }}</h3>
                            <p class="text-sm text-gray-600 line-clamp-2">{{ $t->description }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                                <x-ticket-status :status="$t->status"/>
                                <x-ticket-priority :priority="$t->priority"/>
                                <span class="text-gray-500">Propiedad: <strong>{{ optional($t->property)->alias }}</strong></span>
                                <span class="text-gray-500">Creado: {{ $t->created_at->format('d/m/Y H:i') }}</span>
                                @if($t->due_date)
                                    <span class="text-gray-500">Vence: {{ $t->due_date->format('d/m/Y') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-sm text-gray-500">Asignado a</div>
                            <div class="font-medium">{{ optional($t->assignee)->name ?? '—' }}</div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="p-6 text-center text-gray-500">Sin resultados</div>
            @endforelse
        </div>

        <div class="p-4">
            {{ $tickets->links() }}
        </div>
    </div>
</x-app-layout>
