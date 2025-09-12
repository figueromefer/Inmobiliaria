{{-- resources/views/users/_form.blade.php --}}
@csrf
<div class="grid gap-4">
    <div>
        <label class="block text-sm font-medium">Nombre</label>
        <input name="name" value="{{ old('name', $user->name ?? '') }}" class="w-full border rounded p-2"/>
        @error('name')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium">Email</label>
        <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="w-full border rounded p-2"/>
        @error('email')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium">Rol</label>
        <select name="role" class="w-full border rounded p-2">
            <option value="admin" @selected(old('role', $user->role ?? '')==='admin')>Administrador</option>
            <option value="agent" @selected(old('role', $user->role ?? '')==='agent')>Agente</option>
        </select>
        @error('role')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium">Contraseña (opcional)</label>
        <input type="password" name="password" class="w-full border rounded p-2" placeholder="Mínimo 8 caracteres"/>
        @error('password')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
    </div>
</div>
<div class="mt-6">
    <button class="bg-gold-700 text-white px-4 py-2 rounded">Guardar</button>
</div>
