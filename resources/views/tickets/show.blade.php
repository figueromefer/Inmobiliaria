@php use Illuminate\Support\Facades\Storage; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $ticket->title }}
        </h2>
    </x-slot>

    <div class="sm:px-6 lg:px-8 mt-6 flex">
        <div class="mx-auto max-w-full flex">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                                <x-ticket-status :status="$ticket->status"/>
                                <x-ticket-priority :priority="$ticket->priority"/>
                                <span class="text-gray-500">Propiedad: <strong>{{ optional($ticket->property)->alias }}</strong></span>
                                <span class="text-gray-500">Creado por: {{ optional($ticket->creator)->name }}</span>
                                <span class="text-gray-500">Creado: {{ $ticket->created_at->format('d/m/Y H:i') }}</span>
                                @if($ticket->due_date)
                                    <span class="text-gray-500">Vence: {{ $ticket->due_date->format('d/m/Y') }}</span>
                                @endif
                                @if($ticket->closed_at)
                                    <span class="text-gray-500">Cerrado: {{ $ticket->closed_at->format('d/m/Y H:i') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="text-sm text-gray-500">Asignado a</div>
                            <div class="font-medium">{{ optional($ticket->assignee)->name ?? '—' }}</div>
                            <div class="mt-2 flex gap-2">
                                <a href="{{ route('tickets.edit',$ticket) }}" class="px-3 py-1.5 text-sm border rounded-md">Editar</a>
                                <form method="POST" action="{{ route('tickets.destroy',$ticket) }}" onsubmit="return confirm('¿Eliminar ticket?')">
                                    @csrf @method('DELETE')
                                    <button class="px-3 py-1.5 text-sm border rounded-md text-red-700">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 prose max-w-none">
                        <p>{{ $ticket->description ?: 'Sin descripción.' }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border">
                    <div class="p-4 border-b flex items-center justify-between">
                        <h2 class="font-medium">Comentarios ({{ $ticket->comments->count() }})</h2>
                    </div>
                    <div class="divide-y">
                        @forelse($ticket->comments as $c)
                            <div class="p-4">
                                <div class="text-sm text-gray-500">
                                    <strong>{{ optional($c->author)->name }}</strong> — {{ $c->created_at->format('d/m/Y H:i') }}
                                </div>
                                <div class="mt-1 whitespace-pre-line">{{ $c->body }}</div>
                                @if($c->attachments)
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($c->attachments as $path)
                                            <a class="text-blue-600 text-sm underline" target="_blank" href="{{ Storage::disk('public')->url($path) }}">Archivo</a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="p-6 text-gray-500">Aún no hay comentarios.</div>
                        @endforelse
                    </div>

                    <div class="p-4 border-t">
                        <form method="POST" action="{{ route('tickets.comments.store',$ticket) }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-sm mb-1">Nuevo comentario</label>
                                <textarea name="body" rows="3" class="w-full border rounded-md px-3 py-2" required>{{ old('body') }}</textarea>
                            </div>
                            <div>
                                <label class="block text-sm mb-1">Adjuntos</label>
                                <input type="file" name="attachments[]" multiple class="block w-full text-sm">
                                <p class="text-xs text-gray-500 mt-1">Máx. 10MB por archivo.</p>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button class="px-4 py-2 bg-blue-600 text-white rounded-md">Comentar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border p-4">
                    <div class="text-sm font-medium mb-2">Cambiar estatus</div>
                    <form method="POST" action="{{ route('tickets.status.update',$ticket) }}" class="flex items-center gap-2">
                        @csrf @method('PATCH')
                        <select name="status" class="border rounded-md px-2 py-1.5">
                            <option value="open" @selected($ticket->status==='open')>Abierto</option>
                            <option value="in_progress" @selected($ticket->status==='in_progress')>En proceso</option>
                            <option value="completed" @selected($ticket->status==='completed')>Completado</option>
                            <option value="canceled" @selected($ticket->status==='canceled')>Cancelado</option>
                        </select>
                        <button class="px-3 py-1.5 rounded-md border">Actualizar</button>
                    </form>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-4">
                    <div class="text-sm text-gray-500">ID Ticket</div>
                    <div class="font-mono">{{ $ticket->id }}</div>
                </div>
            </aside>
        </div>
        
    </div>
</x-app-layout>
