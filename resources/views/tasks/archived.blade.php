<x-app-layout>
<x-slot name="header">
    <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl">
            Tareas archivadas
        </h2>

        <a href="{{ route('tasks.index') }}"
           class="bg-gray-100 px-4 py-2 rounded-lg">
            ← Volver
        </a>
    </div>
</x-slot>

<div class="py-6 max-w-6xl mx-auto">

<div class="bg-white rounded-xl shadow-sm p-6">

@forelse($tasks as $task)

<div class="border rounded-xl p-4 mb-3">
    <div class="font-semibold">
        {{ $task->title }}
    </div>

    @if($task->due_date)
        <div class="text-sm text-gray-500 mt-2">
            📅 {{ $task->due_date->format('d/m/Y') }}
        </div>
    @endif
</div>

@empty

<div class="text-gray-500">
Sin tareas archivadas
</div>

@endforelse

</div>

</div>
</x-app-layout>