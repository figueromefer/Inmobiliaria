{{-- resources/views/users/create.blade.php --}}
@extends('layouts.app')
@section('content')
<div class="max-w-xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">Nuevo usuario</h1>
    <form method="POST" action="{{ route('users.store') }}">
        @include('users._form')
    </form>
</div>
@endsection
