<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Bitácora del Sistema
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm rounded p-4 mb-4">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <select name="user_id" class="border rounded p-2">
                        <option value="">Usuario</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>

                    <select name="action" class="border rounded p-2">
                        <option value="">Acción</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}">{{ $action }}</option>
                        @endforeach
                    </select>

                    <select name="module" class="border rounded p-2">
                        <option value="">Módulo</option>
                        @foreach($modules as $module)
                            <option value="{{ $module }}">{{ $module }}</option>
                        @endforeach
                    </select>

                    <input type="date" name="from" class="border rounded p-2">
                    <input type="date" name="to" class="border rounded p-2">

                    <button class="bg-blue-600 text-white px-4 py-2 rounded">Filtrar</button>
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
                                        <pre class="mt-2 max-w-xl overflow-x-auto rounded bg-gray-900 p-3 text-gray-100">{{ $log->technical_detail_json }}</pre>
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
