@php
    $filters = $movimientosPerfil['filters'];
    $reporte = $movimientosPerfil['reporte'];
    $movimientos = $movimientosPerfil['movimientos'];
    $conceptos = [
        '' => 'Todos',
        'deposito' => 'Depósito',
        'renta' => 'Renta',
        'gasto' => 'Gasto propiedad',
        'gasto_cliente' => 'Gasto cliente',
        'iguala' => 'Iguala',
        'pago_cliente' => 'Pago cliente',
    ];
    $estadosPago = [
        '' => 'Todos',
        'pendiente' => 'Pendiente',
        'liquidado' => 'Liquidado',
        'cancelado' => 'Cancelado',
    ];
    $approvalStatuses = [
        '' => 'Todos',
        'pending' => 'Pendiente aprobación',
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
    ];
    $assignmentLabels = [
        'cliente' => 'Cliente',
        'propiedad' => 'Propiedad',
        'inquilino' => 'Inquilino',
    ];
    $conceptLabel = fn ($concepto) => $conceptos[$concepto] ?? ucfirst((string) $concepto);
    $paymentLabel = fn ($estado) => $estadosPago[$estado ?: 'liquidado'] ?? ucfirst((string) $estado);
    $approvalLabel = fn ($status) => $approvalStatuses[$status ?: 'approved'] ?? ucfirst((string) $status);
@endphp

<div class="bg-white rounded-xl shadow-sm p-6">
    <div class="mb-4">
        <h3 class="font-bold text-lg text-gray-900">Movimientos</h3>
        <p class="text-sm text-gray-500">Movimientos relacionados con filtros por fecha, concepto y estado.</p>
    </div>

    <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3 mb-5">
        <div>
            <label class="block text-xs font-medium text-gray-500">Fecha inicio</label>
            <input type="date" name="fecha_inicio" value="{{ $filters['fecha_inicio'] }}" class="mt-1 w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500">Fecha fin</label>
            <input type="date" name="fecha_fin" value="{{ $filters['fecha_fin'] }}" class="mt-1 w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500">Concepto</label>
            <select name="concepto" class="mt-1 w-full border rounded px-3 py-2 text-sm">
                @foreach($conceptos as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['concepto'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500">Estado pago</label>
            <select name="estado_pago" class="mt-1 w-full border rounded px-3 py-2 text-sm">
                @foreach($estadosPago as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['estado_pago'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500">Aprobación</label>
            <select name="approval_status" class="mt-1 w-full border rounded px-3 py-2 text-sm">
                @foreach($approvalStatuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['approval_status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button class="bg-gray-800 text-white px-4 py-2 rounded text-sm">Filtrar</button>
            <a href="{{ url()->current() }}" class="border px-4 py-2 rounded text-sm">Limpiar</a>
        </div>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-5 text-sm">
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-gray-500">Ingresos</div>
            <div class="font-bold">${{ number_format((float) ($reporte['periodo']['ingresos_total'] ?? 0), 2) }}</div>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-gray-500">Egresos</div>
            <div class="font-bold">${{ number_format((float) ($reporte['periodo']['egresos_total'] ?? 0), 2) }}</div>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-gray-500">Pagos al cliente</div>
            <div class="font-bold">${{ number_format((float) ($reporte['periodo']['pagos_cliente'] ?? 0), 2) }}</div>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-gray-500">Saldo contable</div>
            <div class="font-bold">${{ number_format((float) ($reporte['saldo_contable'] ?? 0), 2) }}</div>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-gray-500">Saldo liquidado</div>
            <div class="font-bold">${{ number_format((float) ($reporte['saldo_liquidado'] ?? 0), 2) }}</div>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-gray-500">Pendiente por cobrar</div>
            <div class="font-bold">${{ number_format((float) ($reporte['pendientes']['por_cobrar'] ?? 0), 2) }}</div>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-gray-500">Pendiente por pagar/liquidar</div>
            <div class="font-bold">${{ number_format((float) ($reporte['pendientes']['por_pagar_o_liquidar'] ?? 0), 2) }}</div>
        </div>
    </div>

    <div class="overflow-x-auto border rounded-lg">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-3 py-2">Folio</th>
                    <th class="text-left px-3 py-2">Fecha</th>
                    <th class="text-left px-3 py-2">Concepto</th>
                    <th class="text-left px-3 py-2">Asignado a</th>
                    <th class="text-left px-3 py-2">Cliente</th>
                    <th class="text-left px-3 py-2">Propiedad</th>
                    <th class="text-left px-3 py-2">Inquilino</th>
                    <th class="text-right px-3 py-2">Importe</th>
                    <th class="text-left px-3 py-2">Estado pago</th>
                    <th class="text-left px-3 py-2">Aprobación</th>
                    <th class="text-right px-3 py-2">Recibo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movimientos as $movimiento)
                    <tr class="border-b">
                        <td class="px-3 py-2 font-semibold">{{ $movimiento->folio ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $movimiento->fecha?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $conceptLabel($movimiento->concepto) }}</td>
                        <td class="px-3 py-2">{{ $assignmentLabels[$movimiento->asignado_a_tipo] ?? ucfirst((string) $movimiento->asignado_a_tipo) }}</td>
                        <td class="px-3 py-2">{{ $movimiento->cliente?->nombre ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $movimiento->propiedad?->alias ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $movimiento->inquilino?->nombre ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">${{ number_format((float) $movimiento->importe, 2) }}</td>
                        <td class="px-3 py-2">{{ $paymentLabel($movimiento->estado_pago) }}</td>
                        <td class="px-3 py-2">{{ $approvalLabel($movimiento->approval_status) }}</td>
                        <td class="px-3 py-2 text-right">
                            @if(in_array($movimiento->concepto, ['deposito', 'renta'], true) && $movimiento->approval_status === \App\Models\Movimiento::STATUS_APPROVED)
                                <a href="{{ route('movimientos.recibo', $movimiento) }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Ver</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-3 py-5 text-center text-gray-500">Sin movimientos en el rango seleccionado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $movimientos->links() }}
    </div>
</div>
