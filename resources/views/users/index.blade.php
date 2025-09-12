{{-- resources/views/users/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto p-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Usuarios</h1>
        @can('manage-users')
        <a href="{{ route('users.create') }}" class="bg-gold-700 text-white px-4 py-2 rounded">+ Nuevo usuario</a>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-4 p-3 rounded bg-green-100 text-green-800">{{ session('status') }}</div>
    @endif

    <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left p-3">Nombre</th>
                    <th class="text-left p-3">Email</th>
                    <th class="text-left p-3">Rol</th>
                    <th class="text-right p-3">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $u)
                <tr class="border-t">
                    <td class="p-3">{{ $u->name }}</td>
                    <td class="p-3">{{ $u->email }}</td>
                    <td class="p-3">{{ $u->role === 'admin' ? 'Administrador' : 'Agente' }}</td>
                    <td class="p-3 text-right space-x-2">
                        <a href="{{ route('users.edit',$u) }}" class="underline">Editar</a>
                        @can('delete-anything')
                        <form action="{{ route('users.destroy',$u) }}" method="POST" class="inline"
                              onsubmit="return confirm('¿Eliminar usuario?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 underline">Eliminar</button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-3">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection
