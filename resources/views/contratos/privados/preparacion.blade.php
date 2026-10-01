<x-app-layout>
  <style>
    .preparation-primary, .preparation-secondary { align-items:center; border-radius:.5rem; cursor:pointer; display:inline-flex; font-weight:700; min-height:2.5rem; padding:.6rem 1rem; text-decoration:none; }
    .preparation-primary { background:#155e75; border:1px solid #0f4c5c; color:#fff; }.preparation-primary:hover { background:#0f4c5c; color:#fff; }
    .preparation-secondary { background:#fff; border:1px solid #64748b; color:#334155; }.preparation-secondary:hover { background:#f1f5f9; color:#0f172a; }
    .preparation-state { border-radius:9999px; display:inline-block; font-size:.75rem; font-weight:700; padding:.3rem .6rem; }.preparation-state-ready { background:#dcfce7; color:#166534; }.preparation-state-pending { background:#f1f5f9; color:#475569; }
  </style>
  <x-slot name="header">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div><h2 class="font-semibold text-xl text-gray-800">Nuevo contrato privado</h2><p class="mt-1 text-sm text-gray-500">Paso 1 — Preparación y conciliación</p></div>
      <a href="{{ route('contratos.index') }}" class="preparation-secondary">Volver a contratos</a>
    </div>
  </x-slot>

  <div class="max-w-6xl mx-auto mt-6 space-y-5 px-4 pb-8 lg:px-8">
    @if(session('success'))<div class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-900">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-900">{{ $errors->first() }}</div>@endif

    <section class="rounded-lg border bg-white p-5 shadow-sm">
      <h3 class="font-semibold text-lg text-gray-900">Datos generales</h3>
      <p class="mt-1 text-sm text-gray-600">Estos datos pueden completarse después; se guardan como una nueva versión del borrador.</p>
      <form method="POST" action="{{ route('contratos.privados.preparacion.generales', $draft) }}" class="mt-4 grid gap-4 md:grid-cols-2">@csrf @method('PUT')
        <input type="hidden" name="expected_version_id" value="{{ $draft->currentVersion->id }}">
        <label class="text-sm font-medium">Fecha del contrato<input type="date" name="contract_date" value="{{ data_get($payload, 'metadata.contract_date') }}" class="mt-1 w-full rounded border px-3 py-2"></label>
        <label class="text-sm font-medium">Referencia interna<input name="contract_reference" value="{{ data_get($payload, 'metadata.contract_reference') }}" class="mt-1 w-full rounded border px-3 py-2"></label>
        <label class="text-sm font-medium">Alias de propiedad<input name="property_alias" value="{{ data_get($payload, 'leased_property.alias') }}" class="mt-1 w-full rounded border px-3 py-2"></label>
        <label class="text-sm font-medium">Domicilio de propiedad<input name="property_address" value="{{ data_get($payload, 'leased_property.address') }}" class="mt-1 w-full rounded border px-3 py-2"></label>
        <div class="md:col-span-2"><button class="preparation-secondary" type="submit">Guardar preparación</button></div>
      </form>
    </section>

    @php
      $entityCards = [
        'cliente' => ['title' => 'Arrendador', 'label' => 'cliente', 'linked' => $draft->cliente, 'name' => $draft->cliente?->nombre],
        'propiedad' => ['title' => 'Propiedad', 'label' => 'propiedad', 'linked' => $draft->propiedad, 'name' => $draft->propiedad?->alias],
        'inquilino' => ['title' => 'Arrendatario', 'label' => 'inquilino', 'linked' => $draft->inquilino, 'name' => $draft->inquilino?->nombre],
      ];
    @endphp
    @foreach($entityCards as $entity => $card)
      <section class="rounded-lg border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-semibold text-lg text-gray-900">{{ $card['title'] }}</h3><p class="mt-1 text-sm text-gray-600">@if($card['linked']) Conciliado con {{ $card['name'] }}. Datos disponibles copiados sólo en campos vacíos. @else Busca y selecciona un {{ $card['label'] }} existente. @endif</p></div><span class="preparation-state {{ $card['linked'] ? 'preparation-state-ready' : 'preparation-state-pending' }}">{{ $card['linked'] ? 'Conciliado · datos copiados' : 'Sin conciliar' }}</span></div>

        @if($entity === 'propiedad' && $draft->cliente && $draft->propiedad && (int) $draft->cliente_id !== (int) $draft->propiedad->fk_cliente)
          <p class="mt-3 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">La propiedad pertenece a otro cliente. El arrendador seleccionado se conserva y no fue reemplazado.</p>
        @endif

        <form method="GET" action="{{ route('contratos.privados.preparacion.show', $draft) }}" class="mt-4 flex flex-wrap gap-2">
          <label class="sr-only" for="{{ $entity }}_q">Buscar {{ $card['label'] }}</label>
          <input id="{{ $entity }}_q" name="{{ $entity }}_q" value="{{ $searches[$entity]['query'] }}" placeholder="Buscar {{ $card['label'] }}" class="min-w-64 rounded border px-3 py-2">
          <button class="preparation-secondary" type="submit">Buscar</button>
        </form>

        @if($searches[$entity]['results'])
          <div class="mt-4 overflow-x-auto border rounded"><table class="min-w-full text-sm"><tbody>
            @foreach($searches[$entity]['results'] as $row)
              <tr class="border-b last:border-b-0"><td class="px-3 py-2"><div class="font-medium">{{ $entity === 'propiedad' ? $row->alias : $row->nombre }}</div><div class="text-xs text-gray-500">{{ $entity === 'cliente' ? ($row->rfc ?: $row->correo) : ($entity === 'propiedad' ? $row->domicilio : ($row->correo ?: $row->telefono)) }}</div></td><td class="px-3 py-2 text-right"><form method="POST" action="{{ route('contratos.privados.preparacion.conciliacion', [$draft, $entity]) }}">@csrf<input type="hidden" name="expected_version_id" value="{{ $draft->currentVersion->id }}"><input type="hidden" name="entity_id" value="{{ $row->getKey() }}"><button class="preparation-primary" type="submit">Seleccionar</button></form></td></tr>
            @endforeach
          </tbody></table></div>
          <div class="mt-3">{{ $searches[$entity]['results']->links() }}</div>
        @endif
      </section>
    @endforeach

    <section class="rounded-lg border bg-white p-5 shadow-sm">
      <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-semibold text-lg text-gray-900">Fiador</h3><p class="mt-1 text-sm text-gray-600">No existe maestro de fiadores todavía. La captura manual se completa en el siguiente paso.</p></div><span class="preparation-state {{ data_get($payload, 'guarantor.type') === 'none' ? 'preparation-state-pending' : 'preparation-state-ready' }}">{{ data_get($payload, 'guarantor.type') === 'none' ? 'Sin fiador' : 'Captura manual pendiente' }}</span></div>
      <form method="POST" action="{{ route('contratos.privados.preparacion.fiador', $draft) }}" class="mt-4 flex flex-wrap gap-3">@csrf @method('PUT')<input type="hidden" name="expected_version_id" value="{{ $draft->currentVersion->id }}"><label class="inline-flex items-center gap-2"><input type="radio" name="guarantor_mode" value="none" @checked(data_get($payload, 'guarantor.type') === 'none')> Sin fiador</label><label class="inline-flex items-center gap-2"><input type="radio" name="guarantor_mode" value="manual" @checked(data_get($payload, 'guarantor.type') !== 'none')> Capturar fiador manualmente</label><button class="preparation-secondary" type="submit">Guardar fiador</button></form>
    </section>

    <form method="POST" action="{{ route('contratos.privados.preparacion.next', $draft) }}" class="flex justify-end">@csrf<button class="preparation-primary" type="submit">Siguiente</button></form>
  </div>
</x-app-layout>
