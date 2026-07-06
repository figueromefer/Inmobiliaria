<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tickets</h2>
                <p class="text-sm text-gray-500 mt-1">Seguimiento operativo por estatus, prioridad y vencimiento</p>
            </div>

            @can('manage-records')
                <a href="{{ route('tickets.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
                    + Nuevo ticket
                </a>
            @endcan
        </div>
    </x-slot>

    @php
        $collection = $tickets->getCollection();
        $pendingCount = $collection->where('status', 'open')->count();
        $progressCount = $collection->where('status', 'in_progress')->count();
        $urgentCount = $collection->where('priority', 'high')->count();
        $overdueCount = $collection->filter(fn($ticket) => $ticket->due_date && $ticket->due_date->isPast() && !in_array($ticket->status, ['completed', 'canceled']))->count();
    @endphp

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-6 space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 max-w-5xl">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm min-h-[105px]">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase font-semibold text-amber-700">Pendientes</div>
                        <div class="text-3xl font-bold text-amber-700 mt-1">{{ $pendingCount }}</div>
                    </div>
                    <div class="h-11 w-11 rounded-full bg-amber-200 text-amber-800 flex items-center justify-center text-xl">●</div>
                </div>
            </div>

            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm min-h-[105px]">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase font-semibold text-blue-700">En proceso</div>
                        <div class="text-3xl font-bold text-blue-700 mt-1">{{ $progressCount }}</div>
                    </div>
                    <div class="h-11 w-11 rounded-full bg-blue-200 text-blue-800 flex items-center justify-center text-xl">↻</div>
                </div>
            </div>

            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 shadow-sm min-h-[105px]">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase font-semibold text-red-700">Urgentes</div>
                        <div class="text-3xl font-bold text-red-700 mt-1">{{ $urgentCount }}</div>
                    </div>
                    <div class="h-11 w-11 rounded-full bg-red-600 text-white flex items-center justify-center text-xl animate-pulse">!</div>
                </div>
            </div>

            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm min-h-[105px]">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase font-semibold text-rose-700">Vencidos</div>
                        <div class="text-3xl font-bold text-rose-700 mt-1">{{ $overdueCount }}</div>
                    </div>
                    <div class="h-11 w-11 rounded-full bg-rose-200 text-rose-800 flex items-center justify-center text-xl">⌛</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="p-5 border-b bg-gray-50">
                <form method="GET" class="grid md:grid-cols-5 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Propiedad</label>
                        <select name="property_id" class="js-searchable-select w-full border-gray-300 rounded-lg shadow-sm">
                            <option value="">Todas</option>
                            @foreach($properties as $p)
                                <option value="{{ $p->id ?? $p->pk_propiedad }}" @selected(request('property_id')==($p->id ?? $p->pk_propiedad))>
                                    {{ $p->alias }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Estatus</label>
                        <select name="status" class="w-full border-gray-300 rounded-lg shadow-sm">
                            <option value="">Todos</option>
                            <option value="open" @selected(request('status')==='open')>Pendiente</option>
                            <option value="in_progress" @selected(request('status')==='in_progress')>En proceso</option>
                            <option value="completed" @selected(request('status')==='completed')>Completado</option>
                            <option value="canceled" @selected(request('status')==='canceled')>Cancelado</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Prioridad</label>
                        <select name="priority" class="w-full border-gray-300 rounded-lg shadow-sm">
                            <option value="">Todas</option>
                            <option value="low" @selected(request('priority')==='low')>Baja</option>
                            <option value="medium" @selected(request('priority')==='medium')>Media</option>
                            <option value="high" @selected(request('priority')==='high')>Alta / Urgente</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Buscar</label>
                        <input type="text" name="search" value="{{ request('search') }}" class="w-full border-gray-300 rounded-lg shadow-sm" placeholder="Título o descripción...">
                    </div>

                    <div class="md:col-span-5 flex justify-end gap-2">
                        <a href="{{ route('tickets.index') }}" class="px-4 py-2 rounded-lg border hover:bg-white">Limpiar</a>
                        <button class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white">Filtrar</button>
                    </div>
                </form>
            </div>

            <div class="p-5 space-y-4">
                @forelse($tickets as $ticket)
                    @php
                        $isOverdue = $ticket->due_date && $ticket->due_date->isPast() && !in_array($ticket->status, ['completed', 'canceled']);
                        $cardClass = $ticket->priority === 'high'
                            ? 'border-red-300 bg-red-50/60 ring-1 ring-red-100'
                            : ($isOverdue ? 'border-rose-300 bg-rose-50/60 ring-1 ring-rose-100' : 'border-gray-200 bg-white');
                        $leftBar = $ticket->priority === 'high'
                            ? 'border-l-red-600'
                            : ($isOverdue ? 'border-l-rose-600' : ($ticket->status === 'completed' ? 'border-l-emerald-500' : ($ticket->status === 'in_progress' ? 'border-l-blue-500' : 'border-l-amber-500')));
                    @endphp

                    <a href="{{ route('tickets.show', $ticket) }}" class="block rounded-xl border-l-4 {{ $leftBar }} border {{ $cardClass }} p-4 hover:shadow-md transition">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                            <div class="min-w-0 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-bold text-gray-900 truncate">{{ $ticket->title }}</h3>
                                    <x-ticket-status :status="$ticket->status" />
                                    <x-ticket-priority :priority="$ticket->priority" />
                                    @if($isOverdue)
                                        <span class="inline-flex items-center rounded-full bg-rose-700 text-white px-3 py-1 text-xs font-bold uppercase tracking-wide">
                                            Vencido
                                        </span>
                                    @endif
                                </div>

                                <p class="text-sm text-gray-600 line-clamp-2">{{ $ticket->description ?: 'Sin descripción' }}</p>

                                <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                                    <span>Propiedad: <strong class="text-gray-700">{{ $ticket->property?->alias ?? '—' }}</strong></span>
                                    <span>Creado: {{ $ticket->created_at->format('d/m/Y H:i') }}</span>
                                    <span>Vence: {{ $ticket->due_date?->format('d/m/Y') ?? 'Sin fecha' }}</span>
                                </div>
                            </div>

                            <div class="lg:text-right shrink-0">
                                <div class="text-xs uppercase text-gray-500">Asignado a</div>
                                <div class="font-semibold text-gray-900">{{ $ticket->assignee?->name ?? 'Sin asignar' }}</div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="p-8 text-center text-gray-500 bg-gray-50 rounded-xl">Sin resultados</div>
                @endforelse
            </div>

            <div class="p-5 border-t bg-gray-50">
                {{ $tickets->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
