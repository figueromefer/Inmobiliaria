<x-app-layout>
<x-slot name="header">
<h2 class="font-semibold text-xl">Perfil del Inquilino</h2>
</x-slot>

@php
$documentTypeMeta=[
'comprobante_domicilio'=>['label'=>'Comprobante domicilio','class'=>'bg-slate-100 text-slate-800','icon'=>'🏠'],
'agua'=>['label'=>'Agua','class'=>'bg-blue-100 text-blue-800','icon'=>'🔵'],
'cfe'=>['label'=>'CFE','class'=>'bg-orange-100 text-orange-800','icon'=>'🟠'],
'predial'=>['label'=>'Predial','class'=>'bg-green-100 text-green-800','icon'=>'🟢'],
'recibo'=>['label'=>'Recibo','class'=>'bg-purple-100 text-purple-800','icon'=>'🟣'],
'otro'=>['label'=>'Otro','class'=>'bg-gray-100 text-gray-800','icon'=>'📄']
];
@endphp

<div class="py-6 max-w-7xl mx-auto space-y-6">
<div class="bg-white p-6 rounded-xl shadow-sm">
<div class="flex justify-between">
<div>
<h1 class="text-2xl font-bold">{{ $inquilino->nombre }}</h1>
<p class="text-gray-500">{{ $inquilino->correo ?: 'Sin correo' }}</p>
</div>
<div>
<a href="{{ route('inquilinos.index') }}" class="px-4 py-2 rounded bg-gray-100">Volver</a>
</div>
</div>
</div>

<div class="grid md:grid-cols-3 gap-4">
<div class="bg-white rounded-xl p-4 shadow-sm">
<div class="text-sm text-gray-500">Contratos</div>
<div class="text-3xl font-bold">{{ $inquilino->contratos->count() }}</div>
</div>
<div class="bg-white rounded-xl p-4 shadow-sm">
<div class="text-sm text-gray-500">Documentos</div>
<div class="text-3xl font-bold">{{ $inquilino->documentos->count() }}</div>
</div>
<div class="bg-white rounded-xl p-4 shadow-sm">
<div class="text-sm text-gray-500">Nacionalidad</div>
<div class="font-semibold">{{ $inquilino->nacionalidad ?: '—' }}</div>
</div>
</div>

<div class="bg-white rounded-xl p-6 shadow-sm">
<h3 class="font-bold mb-4">Contratos</h3>
@forelse($inquilino->contratos as $c)
<div class="border rounded p-3 mb-2">
<div><strong>Propiedad:</strong> {{ $c->propiedad?->alias }}</div>
<div><strong>Periodo:</strong> {{ $c->fecha_inicio }} - {{ $c->fecha_fin }}</div>
</div>
@empty
Sin contratos
@endforelse
</div>

<div class="bg-white rounded-xl p-6 shadow-sm">
<div class="flex justify-between mb-4">
<h3 class="font-bold">Documentos</h3>
<a href="{{ route('documentos.create',['inquilino'=>$inquilino->id]) }}" class="bg-gray-800 text-white px-3 py-1 rounded">+ Subir</a>
</div>

@forelse($inquilino->documentos->groupBy(fn($d)=>$d->tipo?:'otro') as $tipo=>$docs)
@php($meta=$documentTypeMeta[$tipo]??$documentTypeMeta['otro'])
<div class="border rounded-xl mb-4 overflow-hidden">
<div class="p-3 bg-gray-50 border-b">
<span class="px-3 py-1 rounded-full text-xs font-bold {{ $meta['class'] }}">{{ $meta['icon'] }} {{ $meta['label'] }}</span>
</div>
@foreach($docs as $d)
<div class="flex justify-between p-3 border-b">
<span>{{ $d->titulo?:'Documento' }}</span>
<a href="{{ route('documentos.view',$d) }}" target="_blank" rel="noopener noreferrer" class="text-blue-600">Ver</a>
</div>
@endforeach
</div>
@empty
<div class="bg-gray-50 rounded p-4 text-gray-500">Sin documentos</div>
@endforelse
</div>
</div>
</x-app-layout>
