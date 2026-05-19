<x-app-layout>
<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800">Tareas</h2>
</x-slot>

<div class="py-6 max-w-7xl mx-auto px-4">

    <form method="POST" action="{{ route('tasks.store') }}" class="bg-white rounded-xl shadow-sm p-4 mb-6 flex gap-3">
        @csrf
        <input type="text" name="title" placeholder="Nueva tarea..." class="flex-1 border-gray-300 rounded-lg" required>
        <input type="date" name="due_date" class="border-gray-300 rounded-lg">
        <button class="bg-blue-600 hover:bg-blue-700 text-white px-5 rounded-lg">Agregar</button>
    </form>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">

@foreach(['pending'=>'Pendientes','in_progress'=>'En proceso','done'=>'Completadas'] as $status=>$label)
<div class="bg-gray-100 rounded-2xl p-4 min-h-[500px]">

<div class="flex justify-between items-center mb-4">
<h3 class="font-bold text-lg">{{ $label }}</h3>
<span class="bg-white px-3 py-1 rounded-full text-sm shadow">
{{ $tasks->where('status',$status)->count() }}
</span>
</div>

@foreach($tasks->where('status',$status) as $task)

<div class="bg-white rounded-xl shadow-sm p-4 mb-4 border-l-4
@if($status=='pending') border-yellow-400
@elseif($status=='in_progress') border-blue-400
@else border-green-500 @endif">

<div class="flex justify-between items-start gap-3">
<div>
<div class="font-semibold text-gray-800">{{ $task->title }}</div>

@if($task->description)
<div class="text-sm text-gray-500 mt-1">
{{ Str::limit($task->description,80) }}
</div>
@endif

@if($task->due_date)
<div class="mt-3 text-xs text-gray-400">
📅 {{ $task->due_date->format('d/m/Y') }}
</div>
@endif
</div>

<form method="POST" action="{{ route('tasks.updateStatus',$task) }}">
@csrf
@method('PATCH')
<select
name="status"
onchange="this.form.submit()"
class="text-xs rounded-lg border-gray-300"
>
<option value="pending" {{ $task->status=='pending'?'selected':'' }}>Pendiente</option>
<option value="in_progress" {{ $task->status=='in_progress'?'selected':'' }}>Proceso</option>
<option value="done" {{ $task->status=='done'?'selected':'' }}>Listo</option>
</select>
</form>

</div>

</div>
@endforeach

</div>
@endforeach

</div>
</div>
</x-app-layout>
