<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
      {{ __('Reporte mensual') }}
    </h2>
  </x-slot>

  <div class="max-w-7xl mx-auto p-4 bg-white mt-6">
    {{-- Filtro --}}
    <form method="GET" action="{{ route('reportes.mensual') }}" class="mb-6 grid gap-4 sm:grid-cols-4">
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium">Cliente</label>
        <select name="cliente_id" class="js-searchable-select mt-1 w-full border rounded px-3 py-2" required>
          <option value="">— Selecciona —</option>
          @foreach ($clientes as $c)
            <option value="{{ $c->id }}" @selected((int)$clienteId === (int)$c->id)>{{ $c->nombre }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium">Mes</label>
        <input type="month" name="mes" value="{{ $mes }}" class="mt-1 w-full border rounded px-3 py-2" required>
      </div>
      <div class="self-end">
        <button class="border rounded px-4 py-2">Generar</button>
      </div>
    </form>

    @if ($clienteId && $mes)
      <div class="mb-6 flex justify-end">
        <a href="{{ route('reportes.mensual.pdf', ['cliente_id' => $clienteId, 'mes' => $mes]) }}" target="_blank" rel="noopener noreferrer" class="rounded bg-gray-800 px-4 py-2 font-semibold text-white hover:bg-gray-700">
          Exportar PDF
        </a>
        <a href="{{ route('reportes.mensual.anexos', ['cliente_id' => $clienteId, 'mes' => $mes]) }}" class="ml-2 rounded bg-gray-800 px-4 py-2 font-semibold text-white hover:bg-gray-700">
          Descargar reporte con anexos (.zip)
        </a>
      </div>

      {{-- 1) Rentas recabadas --}}
      @php($mostrarFechaLiquidacion = $rentasRecabadas->contains(fn ($movimiento) => ! empty($movimiento->fecha_liquidacion)))
      <h3 class="text-lg font-semibold mb-2">Rentas recabadas</h3>
      <div class="overflow-x-auto bg-white border rounded mb-6">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-3 py-2">Periodo / fecha a la que corresponde</th>
              @if($mostrarFechaLiquidacion)<th class="text-left px-3 py-2">Fecha de liquidación</th>@endif
              <th class="text-left px-3 py-2">Propiedad</th>
              <th class="text-right px-3 py-2">Importe</th>
              <th class="text-left px-3 py-2">Forma</th>
              <th class="text-left px-3 py-2">Notas</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($rentasRecabadas as $m)
              <tr class="border-b">
                <td class="px-3 py-2">{{ optional($m->fecha)->format('Y-m-d') }}</td>
                @if($mostrarFechaLiquidacion)<td class="px-3 py-2">{{ $m->fecha_liquidacion ? $m->fecha_liquidacion->format('Y-m-d') : '—' }}</td>@endif
                <td class="px-3 py-2">{{ $m->propiedad->alias ?? '—' }}</td>
                <td class="px-3 py-2 text-right">${{ number_format((float) $m->importe, 2) }}</td>
                <td class="px-3 py-2">{{ ucfirst($m->forma_pago) }}</td>
                <td class="px-3 py-2">{{ $m->notas ?? '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="{{ $mostrarFechaLiquidacion ? 6 : 5 }}" class="px-3 py-4 text-center text-gray-500">Sin datos</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- 2) Rentas adelantadas --}}
      <h3 class="text-lg font-semibold mb-2">Rentas adelantadas</h3>
      <div class="overflow-x-auto bg-white border rounded mb-6">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-3 py-2">Creado</th>
              <th class="text-left px-3 py-2">Periodo / fecha a la que corresponde</th>
              <th class="text-left px-3 py-2">Propiedad</th>
              <th class="text-right px-3 py-2">Importe</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($rentasAdelantadas as $m)
              <tr class="border-b">
                <td class="px-3 py-2">{{ optional($m->created_at)->format('Y-m-d H:i') }}</td>
                <td class="px-3 py-2">{{ optional($m->fecha)->format('Y-m-d') }}</td>
                <td class="px-3 py-2">{{ $m->propiedad->alias ?? '—' }}</td>
                <td class="px-3 py-2 text-right">${{ number_format((float) $m->importe, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">Sin datos</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- 3) Pagos extras --}}
      <h3 class="text-lg font-semibold mb-2">Pagos extras</h3>
      <div class="overflow-x-auto bg-white border rounded mb-6">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-3 py-2">Periodo / fecha a la que corresponde</th>
              <th class="text-left px-3 py-2">Concepto</th>
              <th class="text-left px-3 py-2">Propiedad</th>
              <th class="text-right px-3 py-2">Importe</th>
              <th class="text-left px-3 py-2">Notas</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($pagosExtras as $m)
              <tr class="border-b">
                <td class="px-3 py-2">{{ optional($m->fecha)->format('Y-m-d') }}</td>
                <td class="px-3 py-2">{{ ucfirst($m->concepto) }}</td>
                <td class="px-3 py-2">{{ $m->propiedad->alias ?? '—' }}</td>
                <td class="px-3 py-2 text-right">${{ number_format((float) $m->importe, 2) }}</td>
                <td class="px-3 py-2">{{ $m->notas ?? '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">Sin datos</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- 4) Desocupadas --}}
      <h3 class="text-lg font-semibold mb-2">Desocupadas</h3>
      <div class="overflow-x-auto bg-white border rounded mb-6">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-3 py-2">Propiedad</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($desocupadas as $p)
              <tr class="border-b">
                <td class="px-3 py-2">{{ $p->alias ?? ('#'.$p->pk_propiedad) }}</td>
              </tr>
            @empty
              <tr><td class="px-3 py-4 text-center text-gray-500">Sin datos</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- 5) Gastos del cliente --}}
      <h3 class="text-lg font-semibold mb-2">Gastos del cliente</h3>
      <div class="overflow-x-auto bg-white border rounded mb-6">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-3 py-2">Periodo / fecha a la que corresponde</th>
              <th class="text-left px-3 py-2">Notas</th>
              <th class="text-right px-3 py-2">Importe</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($gastosCliente as $m)
              <tr class="border-b">
                <td class="px-3 py-2">{{ optional($m->fecha)->format('Y-m-d') }}</td>
                <td class="px-3 py-2">{{ $m->notas ?? '—' }}</td>
                <td class="px-3 py-2 text-right">${{ number_format((float) $m->importe, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="3" class="px-3 py-4 text-center text-gray-500">Sin datos</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- 6) Gastos de la propiedad --}}
      <h3 class="text-lg font-semibold mb-2">Gastos de la propiedad</h3>
      <div class="overflow-x-auto bg-white border rounded mb-6">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-3 py-2">Periodo / fecha a la que corresponde</th>
              <th class="text-left px-3 py-2">Propiedad</th>
              <th class="text-left px-3 py-2">Notas</th>
              <th class="text-right px-3 py-2">Importe</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($gastosPropiedad as $m)
              <tr class="border-b">
                <td class="px-3 py-2">{{ optional($m->fecha)->format('Y-m-d') }}</td>
                <td class="px-3 py-2">{{ $m->propiedad->alias ?? '—' }}</td>
                <td class="px-3 py-2">{{ $m->notas ?? '—' }}</td>
                <td class="px-3 py-2 text-right">${{ number_format((float) $m->importe, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">Sin datos</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- 7) Igualas / comisiones de administración --}}
      <h3 class="text-lg font-semibold mb-2">Igualas / Comisiones de administración</h3>
      <div class="overflow-x-auto bg-white border rounded mb-6">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-3 py-2">Periodo / fecha a la que corresponde</th>
              <th class="text-left px-3 py-2">Folio</th>
              <th class="text-left px-3 py-2">Propiedad</th>
              <th class="text-left px-3 py-2">Notas</th>
              <th class="text-right px-3 py-2">Importe</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($igualas as $m)
              <tr class="border-b">
                <td class="px-3 py-2">{{ optional($m->fecha)->format('Y-m-d') }}</td>
                <td class="px-3 py-2">{{ $m->folio ?? '—' }}</td>
                <td class="px-3 py-2">{{ $m->propiedad->alias ?? '—' }}</td>
                <td class="px-3 py-2">{{ $m->notas ?? '—' }}</td>
                <td class="px-3 py-2 text-right">${{ number_format((float) $m->importe, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">Sin datos</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- 4) Pagos al cliente --}}
        <h3 class="text-lg font-semibold mb-2">Pagos al cliente</h3>
        <div class="overflow-x-auto bg-white border rounded mb-6">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-3 py-2">Periodo / fecha a la que corresponde</th>
                <th class="text-right px-3 py-2">Importe</th>
                <th class="text-left px-3 py-2">Forma</th>
                <th class="text-left px-3 py-2">Notas</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($pagosCliente as $m)
            <tr class="border-b">
                <td class="px-3 py-2">{{ optional($m->fecha)->format('Y-m-d') }}</td>
                <td class="px-3 py-2 text-right">${{ number_format((float) $m->importe, 2) }}</td>
                <td class="px-3 py-2">{{ ucfirst($m->forma_pago) }}</td>
                <td class="px-3 py-2">{{ $m->notas ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">Sin datos</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>


      {{-- 9) Resumen --}}
      <h3 class="text-lg font-semibold mb-2">Resumen</h3>
      <div class="overflow-x-auto bg-white border rounded">
        <table class="min-w-full text-sm">
          <tbody>
            @php($summaryRows = [
              ['label' => 'INGRESOS DEL PERIODO', 'importe' => $resumen['ingresos_efectivo'] ?? 0],
              ['label' => 'TOTAL DEPOSITOS', 'importe' => $resumen['total_depositos'] ?? 0],
              ['label' => 'EGRESOS DEL PERIODO', 'importe' => $resumen['gastos_efectivo'] ?? 0],
              ['label' => 'TOTAL DESPUÉS DE GASTOS', 'importe' => $resumen['total_despues_gastos'] ?? 0, 'rowClass' => 'border-t', 'labelClass' => 'font-semibold', 'valueClass' => 'font-semibold'],
              ['label' => 'IGUALA / COMISIÓN DE ADMINISTRACIÓN (INCLUIDA EN EGRESOS)', 'importe' => $resumen['iguala'] ?? 0, 'rowClass' => 'border-t', 'labelClass' => 'font-semibold', 'valueClass' => 'font-semibold'],
              ['label' => 'PAGOS AL CLIENTE (MES)', 'importe' => $resumen['pagos_cliente_mes'] ?? 0],
              ['label' => 'SALDO DE MESES ANTERIORES', 'importe' => $resumen['saldo_anterior'] ?? 0, 'rowClass' => 'border-t', 'labelClass' => 'font-semibold', 'valueClass' => 'font-semibold'],
              ['label' => 'SALDO ANTERIOR CONTABLE', 'importe' => $resumen['saldo_anterior_contable'] ?? 0],
              ['label' => 'SALDO ANTERIOR LIQUIDADO', 'importe' => $resumen['saldo_anterior_liquidado'] ?? 0],
              ['label' => 'PENDIENTE POR COBRAR', 'importe' => $resumen['pendiente_por_cobrar'] ?? 0],
              ['label' => 'PENDIENTE POR PAGAR / LIQUIDAR', 'importe' => $resumen['pendiente_por_pagar_o_liquidar'] ?? 0],
              ['label' => 'TOTAL A PAGAR DEL MES', 'importe' => $resumen['total_mes'] ?? 0, 'rowClass' => 'border-t', 'labelClass' => 'font-semibold', 'valueClass' => 'font-semibold'],
              ['label' => 'SALDO PERIODO LIQUIDADO', 'importe' => $resumen['saldo_periodo_liquidado'] ?? 0],
              ['label' => 'SALDO CONTABLE FINAL', 'importe' => $resumen['saldo_contable'] ?? $resumen['total_incluye_saldos'] ?? 0, 'rowClass' => 'border-t', 'labelClass' => 'text-lg font-bold', 'valueClass' => 'text-lg font-bold'],
              ['label' => 'SALDO LIQUIDADO / DISPONIBLE', 'importe' => $resumen['saldo_liquidado'] ?? 0, 'labelClass' => 'text-lg font-bold', 'valueClass' => 'text-lg font-bold'],
            ])
            @foreach($summaryRows as $row)
              @if((float) $row['importe'] !== 0.0)
                <tr class="{{ $row['rowClass'] ?? '' }}"><td class="px-3 py-2 {{ $row['labelClass'] ?? '' }}">{{ $row['label'] }}</td><td class="px-3 py-2 text-right {{ $row['valueClass'] ?? '' }}">${{ number_format((float) $row['importe'], 2) }}</td></tr>
              @endif
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</x-app-layout>
