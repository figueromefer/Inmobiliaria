<section class="rounded-lg border bg-white p-5 shadow-sm">
<h3 class="text-lg font-semibold">Documentación</h3>
<ul class="mt-3 space-y-2">@foreach($documentChecklist as $item)<li class="rounded border px-3 py-2 {{ $item['present'] ? 'border-green-300 bg-green-50 text-green-900' : 'border-red-300 bg-red-50 text-red-900' }}"><strong>{{ $item['label'] }}</strong><span class="ml-2 text-sm">{{ $item['present'] ? '✓ Cargado' : 'Pendiente' }}</span></li>@endforeach</ul>
</section>
