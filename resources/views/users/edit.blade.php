{{-- resources/views/users/edit.blade.php --}}
@extends('layouts.app')
@section('content')
<div class="max-w-xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">Editar usuario</h1>
    <form method="POST" action="{{ route('users.update',$user) }}">
        @method('PUT')
        @include('users._form')
    </form>
</div>
@endsection
