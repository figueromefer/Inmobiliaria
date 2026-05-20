@php use Illuminate\Support\Facades\Storage; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $ticket->title }}</h2>
                <p class="text-sm text-gray-500 mt-1">Detalle del ticket y seguimiento</p>
            </div>

            <a href="{{ route('tickets.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">
                Volver a tickets
            </a>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ticket-status :status="$ticket->status" />
                                <x-ticket-priority :priority="$ticket->priority" />

                                @if($ticket->due_date && $ticket->due_date->isPast() && !in_array($ticket->status, ['completed', 'canceled']))
                                    <span class="inline-flex items-center rounded-full bg-red-100 text-red-800 px-2.5 py-0.5 text-xs font-semibold">
                                        Vencido
                                    </span>
                                @endif
                            </div>

                            <div class="prose max-w-none text-gray-700">
                                <p>{{ $ticket->description ?: 'Sin descripción.' }}</p>
                            </div>
                        </div>

                        @can('manage-records')
                            <div class="flex gap-2 shrink-0">
                                <a href="{{ route('tickets.edit', $ticket) }}" class="px-3 py-2 text-sm border rounded-lg hover:bg-gray-50">
                                    Editar
                                </a>

                                @can('delete-anything')
                                    <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" onsubmit="return confirm('¿Eliminar ticket?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="px-3 py-2 text-sm border rounded-lg text-red-700 hover:bg-red-50">
                                            Eliminar
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        @endcan
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <h3 class="font-bold text-lg text-gray-900 mb-4">Información del ticket</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs uppercase text-gray-500 mb-1">Propiedad</div>
                            <div class="font-medium text-gray-800">
                                @if($ticket->property)
                                    <a href="{{ route('propiedades.show', $ticket->property) }}" class="text-blue-600 hover:underline">
                                        {{ $ticket->property->alias }}
                                    </a>
                                @else
                                    —
                                @endif
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs uppercase text-gray-500 mb-1">Asignado a</div>
                            <div class="font-medium text-gray-800">{{ $ticket->assignee?->name ?? 'Sin asignar' }}</div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs uppercase text-gray-500 mb-1">Creado por</div>
                            <div class="font-medium text-gray-800">{{ $ticket->creator?->name ?? '—' }}</div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs uppercase text-gray-500 mb-1">Creado</div>
                            <div class="font-medium text-gray-800">{{ $ticket->created_at->format('d/m/Y H:i') }}</div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs uppercase text-gray-500 mb-1">Fecha límite</div>
                            <div class="font-medium text-gray-800">{{ $ticket->due_date?->format('d/m/Y') ?? 'Sin fecha' }}</div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs uppercase text-gray-500 mb-1">Cerrado</div>
                            <div class="font-medium text-gray-800">{{ $ticket->closed_at?->format('d/m/Y H:i') ?? '—' }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border">
                    <div class="p-5 border-b flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-lg text-gray-900">Comentarios</h3>
                            <p class="text-sm text-gray-500">{{ $ticket->comments->count() }} comentario(s)</p>
                        </div>
                    </div>

                    <div class="divide-y">
                        @forelse($ticket->comments as $comment)
                            <div class="p-5 flex gap-4">
                                <div class="h-9 w-9 rounded-full bg-gray-800 text-white flex items-center justify-center text-sm font-bold shrink-0">
                                    {{ strtoupper(substr($comment->author?->name ?? 'U', 0, 1)) }}
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-gray-900">{{ $comment->author?->name ?? 'Usuario' }}</span>
                                        <span class="text-xs text-gray-500">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                                    </div>

                                    <div class="mt-2 text-gray-700 whitespace-pre-line">{{ $comment->body }}</div>

                                    @if($comment->attachments)
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach($comment->attachments as $path)
                                                <a class="inline-flex items-center rounded-lg border px-3 py-1 text-sm text-blue-600 hover:bg-blue-50" target="_blank" href="{{ route('tickets.attachment', [
    'ticket' => $ticket,
    'encodedPath' => base64_encode($path)
]) }}"href="{{ Storage::disk('public')->url($path) }}">
                                                    Ver archivo
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-gray-500 bg-gray-50 m-5 rounded-lg">Aún no hay comentarios.</div>
                        @endforelse
                    </div>

                    @can('manage-records')
                        <div class="p-5 border-t bg-gray-50 rounded-b-xl">
                            <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" enctype="multipart/form-data" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nuevo comentario</label>
                                    <textarea name="body" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm" required>{{ old('body') }}</textarea>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Adjuntos</label>
                                    <input type="file" name="attachments[]" multiple class="block w-full text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Máx. 10MB por archivo.</p>
                                </div>

                                <div class="flex justify-end">
                                    <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                                        Agregar comentario
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endcan
                </div>
            </div>

            <aside class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <h3 class="font-bold text-gray-900 mb-4">Cambiar estatus</h3>

                    @can('manage-records')
                        <form method="POST" action="{{ route('tickets.status.update', $ticket) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <select name="status" class="w-full border-gray-300 rounded-lg shadow-sm">
                                <option value="open" @selected($ticket->status === 'open')>Abierto</option>
                                <option value="in_progress" @selected($ticket->status === 'in_progress')>En proceso</option>
                                <option value="completed" @selected($ticket->status === 'completed')>Completado</option>
                                <option value="canceled" @selected($ticket->status === 'canceled')>Cancelado</option>
                            </select>
                            <button class="w-full px-3 py-2 rounded-lg bg-gray-800 hover:bg-gray-700 text-white">
                                Actualizar estatus
                            </button>
                        </form>
                    @else
                        <p class="text-sm text-gray-500">Solo visualización.</p>
                    @endcan
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <h3 class="font-bold text-gray-900 mb-4">Resumen</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">ID</span>
                            <span class="font-mono">#{{ $ticket->id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Estatus</span>
                            <x-ticket-status :status="$ticket->status" />
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Prioridad</span>
                            <x-ticket-priority :priority="$ticket->priority" />
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
