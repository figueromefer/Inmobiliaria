<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Editar borrador #{{ $draft->id }}</h2></x-slot>
    <div class="max-w-6xl mx-auto mt-6 bg-white p-6 rounded-lg shadow">
        <p class="mb-5 text-sm text-gray-600">Guardar crea una nueva versión; la versión {{ $draft->currentVersion->draft_version }} permanecerá intacta.</p>
        @if($errors->has('expected_version_id'))
            <div class="mb-5 rounded border border-orange-300 bg-orange-50 p-4 text-sm text-orange-800">
                {{ $errors->first('expected_version_id') }} Tus cambios siguen en el formulario; revisa la versión nueva antes de volver a guardar.
            </div>
        @endif
        <form method="POST" action="{{ route('contratos.borradores.update', $draft) }}" class="space-y-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="expected_version_id" value="{{ $draft->currentVersion->id }}">
            @include('contratos.borradores._form', ['submitLabel' => 'Guardar nueva versión'])
        </form>
    </div>
</x-app-layout>
