<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Bitácora del Sistema
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm rounded p-4 mb-4">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                    <input type="text" name="q" value="{{ $q }}" class="border rounded p-2 md:col-span-2" placeholder="Buscar en bitácora">

                    <select name="user_id" class="border rounded p-2">
                        <option value="">Usuario</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>

                    <select name="action" class="border rounded p-2">
                        <option value="">Acción</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                        @endforeach
                    </select>

                    <select name="module" class="border rounded p-2">
                        <option value="">Módulo</option>
                        @foreach($modules as $module)
                            <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
                        @endforeach
                    </select>

                    <input type="date" name="from" value="{{ request('from') }}" class="border rounded p-2">
                    <input type="date" name="to" value="{{ request('to') }}" class="border rounded p-2">

                    <div class="flex gap-2 md:col-span-2">
                        <button class="bg-blue-600 text-white px-4 py-2 rounded">Buscar</button>
                        @if($q !== '' || request()->filled('user_id') || request()->filled('action') || request()->filled('module') || request()->filled('from') || request()->filled('to'))
                            <a href="{{ route('bitacora.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded">Limpiar</a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-2">Fecha</th>
                            <th class="p-2">Usuario</th>
                            <th class="p-2">Acción</th>
                            <th class="p-2">Módulo</th>
                            <th class="p-2">Registro</th>
                            <th class="p-2">Mensaje</th>
                            <th class="p-2">Detalle técnico</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr class="border-t">
                                <td class="p-2">{{ $log->created_at }}</td>
                                <td class="p-2">{{ $log->user?->name }}</td>
                                <td class="p-2">
                                    <span class="px-2 py-1 rounded text-white
                                        @if($log->action=='created') bg-green-500
                                        @elseif($log->action=='updated') bg-yellow-500
                                        @elseif($log->action=='deleted') bg-red-500
                                        @endif">
                                        {{ $log->action }}
                                    </span>
                                </td>
                                <td class="p-2">{{ $log->module }}</td>
                                <td class="p-2">{{ $log->record_label }}</td>
                                <td class="p-2">{{ $log->human_message }}</td>
                                <td class="p-2 text-xs">
                                    <details>
                                        <summary class="cursor-pointer text-blue-600 hover:underline">Ver JSON</summary>
                                        <div class="mt-2 grid gap-3">
                                            <div>
                                                <div class="mb-1 font-semibold text-gray-700">JSON previo</div>
                                                <pre class="max-w-xl overflow-x-auto rounded bg-gray-900 p-3 text-gray-100">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre>
                                            </div>
                                            <div>
                                                <div class="mb-1 font-semibold text-gray-700">JSON nuevo</div>
                                                <pre class="max-w-xl overflow-x-auto rounded bg-gray-900 p-3 text-gray-100">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre>
                                            </div>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="p-4">
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
