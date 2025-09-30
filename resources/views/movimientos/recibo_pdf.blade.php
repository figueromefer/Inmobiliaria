<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body    { font-family: DejaVu Sans, sans-serif; font-size: 14px; }
        .title  { text-align: center; font-size: 20px; font-weight: bold; margin-bottom: 20px; }
        .info   { margin-bottom: 10px; }
        .label  { font-weight: bold; }
        .amount { font-size: 18px; font-weight: bold; }
    </style>
</head>
<body>
    @php
        $conceptMap = ['deposito' => 'Depósito en garantía', 'renta' => 'Pago de renta'];
    @endphp
    <div class="title">Recibo de {{ $conceptMap[$movimiento->concepto] ?? $movimiento->concepto }}</div>
    <div class="info"><span class="label">Fecha:</span> {{ optional($movimiento->fecha)->format('d/m/Y') }}</div>
    <div class="info"><span class="label">Cliente:</span> {{ $movimiento->cliente->nombre ?? '—' }}</div>
    @if($movimiento->propiedad)
      <div class="info"><span class="label">Propiedad:</span> {{ $movimiento->propiedad->alias }}</div>
    @endif
    <div class="info"><span class="label">Forma de pago:</span> {{ ucfirst($movimiento->forma_pago) }}</div>
    <div class="info amount"><span class="label">Importe:</span> ${{ number_format($movimiento->importe, 2) }}</div>
    @if(!empty($movimiento->notas))
      <div class="info"><span class="label">Notas:</span> {{ $movimiento->notas }}</div>
    @endif
</body>
</html>
