<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Nuevo borrador interno</h2></x-slot>
    <div class="max-w-6xl mx-auto mt-6 bg-white p-6 rounded-lg shadow">
        <p class="mb-5 text-sm text-gray-600">Se permite guardar información incompleta. Esta pantalla no publica contratos ni genera documentos.</p>
        <form method="POST" action="{{ route('contratos.borradores.store') }}" class="space-y-6">
            @csrf
            @include('contratos.borradores._form', ['submitLabel' => 'Crear borrador'])
        </form>
    </div>
</x-app-layout>
