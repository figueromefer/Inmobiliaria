<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Tareas</h2>
    </x-slot>

    <div class="py-6 max-w-5xl mx-auto space-y-6">

        <form method="POST" action="{{ route('tasks.store') }}" class="flex gap-2">
            @csrf
            <input type="text" name="title" placeholder="Nueva tarea" class="border rounded px-3 py-2 w-full" required>
            <input type="date" name="due_date" class="border rounded px-3 py-2">
            <button class="bg-blue-600 text-white px-4 py-2 rounded">Agregar</button>
        </form>

        @foreach(['pending' => 'Pendientes', 'in_progress' => 'En proceso', 'done' => 'Completadas'] as $status => $label)
            <div class="bg-white p-4 rounded shadow">
                <h3 class="font-bold mb-2">{{ $label }}</h3>

                @foreach($tasks->where('status', $status) as $task)
                    <div class="flex justify-between items-center border p-2 mb-2 rounded">
                        <div>
                            <div class="font-medium">{{ $task->title }}</div>
                            <div class="text-sm text-gray-500">{{ $task->due_date }}</div>
                        </div>

                        <form method="POST" action="{{ route('tasks.updateStatus', $task) }}">
                            @csrf
                            @method('PATCH')
                            <select name="status" onchange="this.form.submit()" class="border rounded">
                                <option value="pending" {{ $task->status=='pending'?'selected':'' }}>Pendiente</option>
                                <option value="in_progress" {{ $task->status=='in_progress'?'selected':'' }}>En proceso</option>
                                <option value="done" {{ $task->status=='done'?'selected':'' }}>Completado</option>
                            </select>
                        </form>
                    </div>
                @endforeach
            </div>
        @endforeach

    </div>
</x-app-layout>
