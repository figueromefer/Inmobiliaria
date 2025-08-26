<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
      {{ __('Movimientos') }}
    </h2>
  </x-slot>

  <div class="max-w-7xl mx-auto p-4">
    @if (session('ok'))
      <div class="mb-3 p-2 bg-green-100 border text-green-800 rounded">{{ session('ok') }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-end gap-3">
      <form method="GET" action="{{ route('movimientos.index') }}" class="flex gap-2">
        <div>
          <label class="block text-sm font-medium">Buscar</label>
          <input type="text" name="q" value="{{ $q }}" class="mt-1 border rounded px-3 py-2" placeholder="Cliente, propiedad, concepto">
        </div>
        <div>
          <label class="block text-sm font-medium">Por página</label>
          <select name="perPage" class="mt-1 border rounded px-3 py-2">
            @foreach([10,15,25,50,100] as $pp)
              <option value="{{ $pp }}" @selected($perPage==$pp)>{{ $pp }}</option>
            @endforeach
          </select>
        </div>
        <div class="self-end">
          <button class="border rounded px-4 py-2">Aplicar</button>
          <a href="{{ route('movimientos.index') }}" class="border rounded px-4 py-2 ml-2">Limpiar</a>
        </div>
      </form>

      <a href="{{ route('movimientos.create') }}" class="ml-auto border rounded px-4 py-2">Nuevo movimiento</a>
    </div>

    <div class="overflow-x-auto bg-white border rounded">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-2">Fecha</th>
            <th class="text-left px-4 py-2">Cliente</th>
            <th class="text-left px-4 py-2">Propiedad</th>
            <th class="text-left px-4 py-2">Concepto</th>
            <th class="text-left px-4 py-2">Forma de pago</th>
            <th class="text-right px-4 py-2">Importe</th>
          </tr>
        </thead>
        <tbody>
          @forelse($movimientos as $m)
            <tr class="border-b hover:bg-gray-50">
              <td class="px-4 py-2">{{ optional($m->fecha)->format('Y-m-d') }}</td>
              <td class="px-4 py-2">{{ $m->cliente->nombre ?? '—' }}</td>
              <td class="px-4 py-2">{{ $m->propiedad->alias ?? '—' }}</td>
              <td class="px-4 py-2">
                @php
                  $map = ['deposito'=>'Depósito en garantía', 'renta'=>'Pago de renta', 'gasto'=>'Gasto de la propiedad', 'gasto_cliente'  => 'Gastos del cliente',];
                @endphp
                {{ $map[$m->concepto] ?? $m->concepto }}
              </td>
              <td class="px-4 py-2">{{ ucfirst($m->forma_pago) }}</td>
              <td class="px-4 py-2 text-right">{{ number_format($m->importe, 2) }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No hay movimientos.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-4">
      {{ $movimientos->onEachSide(1)->links() }}
    </div>
  </div>
</x-app-layout>
