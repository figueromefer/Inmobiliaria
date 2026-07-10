<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
      {{ __('Movimientos') }}
    </h2>
  </x-slot>

  <div class="max-w-7xl mx-auto mt-6 bg-white lg:px-8 py-6">
    @if (session('ok'))
      <div class="mb-3 p-2 bg-green-100 border text-green-800 rounded">{{ session('ok') }}</div>
    @endif

    @if (session('error'))
      <div class="mb-3 p-2 bg-red-100 border text-red-800 rounded">{{ session('error') }}</div>
    @endif

    <div class="flex justify-end">
        <a href="{{ route('movimientos.create') }}" class="bg-gray-800 hover:bg-gold-700 text-white font-bold py-2 px-4 rounded">+ Nuevo movimiento</a>
    </div>

    <div class="min-w-full divide-y divide-gray-200 mt-6 mb-6">
      <form method="GET" action="{{ route('movimientos.index') }}" class="flex gap-2">
        <div>
          <label class="block text-sm font-medium">Buscar</label>
          <input type="text" name="q" value="{{ $q }}" class="mt-1 border rounded px-3 py-2" placeholder="Folio, cliente, propiedad, concepto, estatus">
        </div>
        <div>
          <label class="block text-sm font-medium">Por página</label>
          <select name="perPage" class="mt-1 border rounded px-3 py-2">
            @foreach([10,15,25,50,100] as $pp)
              <option value="{{ $pp }}" @selected($perPage==$pp)>{{ $pp }}</option>
            @endforeach
          </select>
        </div>
        <div class="self-end vertical-middle">
          <button class="inline-flex items-center bg-gray-800 hover:bg-gold-700 text-white font-bold py-2 px-4 rounded">Aplicar</button>
          <a href="{{ route('movimientos.index') }}" class="inline-flex items-center bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">Limpiar</a>
        </div>
      </form>
    </div>

    <div class="overflow-x-auto bg-white border rounded">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-2">Fecha</th>
            <th class="text-left px-4 py-2">Folio</th>
            <th class="text-left px-4 py-2">Cliente</th>
            <th class="text-left px-4 py-2">Propiedad</th>
            <th class="text-left px-4 py-2">Asignado a</th>
            <th class="text-left px-4 py-2">Concepto</th>
            <th class="text-left px-4 py-2">Forma de pago</th>
            <th class="text-right px-4 py-2">Importe</th>
            <th class="text-left px-4 py-2">Estatus</th>
            <th class="text-left px-4 py-2">Estado de pago</th>
            <th class="text-right px-4 py-2">Comprobante</th>
            <th class="text-right px-4 py-2">Recibo</th>
            <th class="text-right px-4 py-2">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($movimientos as $m)
            @php
              $map = [
                'deposito' => 'Depósito en garantía',
                'renta' => 'Pago de renta',
                'gasto' => 'Gasto de la propiedad',
                'gasto_cliente' => 'Gastos del cliente',
                'iguala' => 'Iguala / Comisión de administración',
                'pago_cliente' => 'Pago al cliente',
              ];

              $status = $m->approval_status ?? \App\Models\Movimiento::STATUS_APPROVED;
              $statusMeta = match($status) {
                \App\Models\Movimiento::STATUS_PENDING => ['label' => 'Pendiente aprobación', 'class' => 'bg-yellow-100 text-yellow-800 border-yellow-200', 'icon' => '🟡'],
                \App\Models\Movimiento::STATUS_REJECTED => ['label' => 'Rechazado', 'class' => 'bg-red-100 text-red-800 border-red-200', 'icon' => '🔴'],
                default => ['label' => 'Aprobado', 'class' => 'bg-green-100 text-green-800 border-green-200', 'icon' => '🟢'],
              };

              $assignmentType = $m->asignado_a_tipo ?? ($m->propiedad_id ? 'propiedad' : 'cliente');
              $assignmentLabel = match($assignmentType) {
                'propiedad' => 'Propiedad',
                'inquilino' => 'Inquilino',
                default => 'Cliente',
              };
              $assignmentName = $m->asignado_nombre;

              $paymentStatus = $m->estado_pago ?? \App\Models\Movimiento::PAYMENT_LIQUIDATED;
              $paymentMeta = match($paymentStatus) {
                \App\Models\Movimiento::PAYMENT_PENDING => ['label' => 'Pendiente', 'class' => 'bg-yellow-50 text-yellow-800 border-yellow-200'],
                \App\Models\Movimiento::PAYMENT_CANCELED => ['label' => 'Cancelado', 'class' => 'bg-red-50 text-red-800 border-red-200'],
                default => ['label' => 'Liquidado', 'class' => 'bg-green-50 text-green-800 border-green-200'],
              };
            @endphp

            <tr class="border-b hover:bg-gray-50">
              <td class="px-4 py-2">{{ optional($m->fecha)->format('Y-m-d') }}</td>
              <td class="px-4 py-2 font-semibold text-gray-700">{{ $m->folio ?? '—' }}</td>
              <td class="px-4 py-2">{{ $m->cliente->nombre ?? '—' }}</td>
              <td class="px-4 py-2">{{ $m->propiedad->alias ?? '—' }}</td>
              <td class="px-4 py-2">
                <span class="inline-flex items-center rounded-full border bg-gray-50 px-2.5 py-1 text-xs font-bold text-gray-700">{{ $assignmentLabel }}</span>
                <div class="text-xs text-gray-500 mt-1">{{ $assignmentName }}</div>
              </td>
              <td class="px-4 py-2">{{ $map[$m->concepto] ?? $m->concepto }}</td>
              <td class="px-4 py-2">{{ $m->forma_pago ? ucfirst($m->forma_pago) : '—' }}</td>
              <td class="px-4 py-2 text-right">${{ number_format((float) $m->importe, 2) }}</td>

              <td class="px-4 py-2">
                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-bold {{ $statusMeta['class'] }}">
                  <span class="mr-1">{{ $statusMeta['icon'] }}</span>
                  {{ $statusMeta['label'] }}
                </span>

                @if($m->approval_status === \App\Models\Movimiento::STATUS_APPROVED && $m->approver)
                  <div class="text-xs text-gray-500 mt-1">
                    Aprobó: {{ $m->approver->name }}
                    @if($m->approved_at)
                      <br>{{ $m->approved_at->format('d/m/Y H:i') }}
                    @endif
                  </div>
                @endif
              </td>

              <td class="px-4 py-2">
                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-bold {{ $paymentMeta['class'] }}">
                  {{ $paymentMeta['label'] }}
                </span>
                @if($m->fecha_liquidacion)
                  <div class="text-xs text-gray-500 mt-1">{{ $m->fecha_liquidacion->format('Y-m-d') }}</div>
                @endif
              </td>

              <td class="px-4 py-2 text-right">
                @if($m->comprobante)
                  <a href="{{ asset('storage/' . $m->comprobante) }}" target="_blank" class="text-blue-600 hover:text-blue-800 underline">
                    Ver
                  </a>
                @else
                  —
                @endif
              </td>

              <td class="px-4 py-2 text-right">
                @if(in_array($m->concepto, ['deposito','renta']) && $m->approval_status === \App\Models\Movimiento::STATUS_APPROVED)
                  <a href="{{ route('movimientos.recibo', $m->id) }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 underline">
                    Ver Recibo
                  </a>
                @elseif(in_array($m->concepto, ['deposito','renta']))
                  <span class="text-xs text-gray-400">Pendiente aprobación</span>
                @else
                  —
                @endif
              </td>

              <td class="px-4 py-2 text-right">
                @if(auth()->user()?->role === 'admin' && $m->approval_status === \App\Models\Movimiento::STATUS_PENDING)
                  <form action="{{ route('movimientos.approve', $m) }}" method="POST" onsubmit="return confirm('¿Aprobar este movimiento?');">
                    @csrf
                    @method('PATCH')
                    <button class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold px-3 py-1 rounded">
                      Aprobar
                    </button>
                  </form>
                @else
                  —
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="13" class="px-4 py-8 text-center text-gray-500">No hay movimientos.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-4">
      {{ $movimientos->onEachSide(1)->links() }}
    </div>
  </div>
</x-app-layout>
