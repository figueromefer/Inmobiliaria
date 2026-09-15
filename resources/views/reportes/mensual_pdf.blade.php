<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /*
         * Estilos básicos para el PDF del reporte mensual.
         * Ajusta los colores, tamaños y márgenes según tu identidad gráfica.
         */
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header img {
            max-height: 70px;
            margin-bottom: 10px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section h3 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 5px;
            vertical-align: top;
        }
        th {
            background: #f5f5f5;
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-top: 5px;
        }
        .summary-table td {
            padding: 5px;
        }
        .summary-table tr td:first-child {
            font-weight: bold;
        }
        .closing-block {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .signature {
            margin-top: 16px;
            text-align: center;
            font-size: 12px;
        }
        .signature .line {
            margin: 20px auto 5px auto;
            width: 70%;
            border-top: 1px solid #000;
        }
    </style>
</head>
<body>
@php
    // Ajustar a español
    \Carbon\Carbon::setLocale('es');
    // Construir la fecha del reporte a partir de $anio y $mes (si existen)
    try {
        if (isset($anio) && isset($mes)) {
            $reporteFecha = \Carbon\Carbon::parse($anio.'-'.$mes.'-01');
        } elseif (isset($mes) && preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $reporteFecha = \Carbon\Carbon::parse($mes.'-01');
        } else {
            $reporteFecha = \Carbon\Carbon::now();
        }
    } catch (\Exception $e) {
        $reporteFecha = \Carbon\Carbon::now();
    }
@endphp

<div class="header">
    <img src="{{ public_path('images/logo.png') }}" alt="Logo Dorantes Aranda &amp; Asociados">
    <h2>Reporte mensual — {{ $cliente->nombre ?? 'Cliente' }}</h2>
    <div>{{ $reporteFecha->translatedFormat('F Y') }}</div>
</div>

{{-- 1) Rentas recabadas --}}
@if(isset($rentasRecabadas) && $rentasRecabadas->isNotEmpty())
    @php($mostrarFechaLiquidacion = $rentasRecabadas->contains(fn ($movimiento) => ! empty($movimiento->fecha_liquidacion)))
    <div class="section">
        <h3>Rentas recabadas</h3>
        <table>
            <thead>
                <tr>
                    <th>Periodo / fecha a la que corresponde</th>
                    @if($mostrarFechaLiquidacion)<th>Fecha de liquidación</th>@endif
                    <th>Propiedad</th>
                    <th class="text-right">Importe</th>
                    <th>Forma</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rentasRecabadas as $m)
                    <tr>
                        <td>{{ optional($m->fecha)->format('d/m/Y') }}</td>
                        @if($mostrarFechaLiquidacion)<td>{{ $m->fecha_liquidacion ? $m->fecha_liquidacion->format('d/m/Y') : '—' }}</td>@endif
                        <td>{{ $m->propiedad->alias ?? '—' }}</td>
                        <td class="text-right">${{ number_format((float) $m->importe, 2) }}</td>
                        <td>{{ ucfirst($m->forma_pago) }}</td>
                        <td>{{ $m->notas ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- 2) Rentas adelantadas --}}
@if(isset($rentasAdelantadas) && $rentasAdelantadas->isNotEmpty())
    <div class="section">
        <h3>Rentas adelantadas</h3>
        <table>
            <thead>
                <tr>
                    <th>Creado</th>
                    <th>Periodo / fecha a la que corresponde</th>
                    <th>Propiedad</th>
                    <th class="text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rentasAdelantadas as $m)
                    <tr>
                        <td>{{ optional($m->created_at)->format('d/m/Y H:i') }}</td>
                        <td>{{ optional($m->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $m->propiedad->alias ?? '—' }}</td>
                        <td class="text-right">${{ number_format((float) $m->importe, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- 3) Pagos extras --}}
@if(isset($pagosExtras) && $pagosExtras->isNotEmpty())
    <div class="section">
        <h3>Pagos extras</h3>
        <table>
            <thead>
                <tr>
                    <th>Periodo / fecha a la que corresponde</th>
                    <th>Concepto</th>
                    <th>Propiedad</th>
                    <th class="text-right">Importe</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pagosExtras as $m)
                    <tr>
                        <td>{{ optional($m->fecha)->format('d/m/Y') }}</td>
                        <td>{{ ucfirst($m->concepto) }}</td>
                        <td>{{ $m->propiedad->alias ?? '—' }}</td>
                        <td class="text-right">${{ number_format((float) $m->importe, 2) }}</td>
                        <td>{{ $m->notas ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- 4) Desocupadas --}}
@if(isset($desocupadas) && $desocupadas->isNotEmpty())
    <div class="section">
        <h3>Desocupadas</h3>
        <table>
            <thead>
                <tr><th>Propiedad</th></tr>
            </thead>
            <tbody>
                @foreach($desocupadas as $p)
                    <tr><td>{{ $p->alias ?? ('#'.$p->pk_propiedad) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- 5) Gastos del cliente --}}
@if(isset($gastosCliente) && $gastosCliente->isNotEmpty())
    <div class="section">
        <h3>Gastos del cliente</h3>
        <table>
            <thead>
                <tr>
                    <th>Periodo / fecha a la que corresponde</th>
                    <th>Notas</th>
                    <th class="text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach($gastosCliente as $m)
                    <tr>
                        <td>{{ optional($m->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $m->notas ?? '—' }}</td>
                        <td class="text-right">${{ number_format((float) $m->importe, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- 6) Gastos de la propiedad --}}
@if(isset($gastosPropiedad) && $gastosPropiedad->isNotEmpty())
    <div class="section">
        <h3>Gastos de la propiedad</h3>
        <table>
            <thead>
                <tr>
                    <th>Periodo / fecha a la que corresponde</th>
                    <th>Propiedad</th>
                    <th>Notas</th>
                    <th class="text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach($gastosPropiedad as $m)
                    <tr>
                        <td>{{ optional($m->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $m->propiedad->alias ?? '—' }}</td>
                        <td>{{ $m->notas ?? '—' }}</td>
                        <td class="text-right">${{ number_format((float) $m->importe, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- 7) Igualas / comisiones de administración --}}
@if(isset($igualas) && $igualas->isNotEmpty())
    <div class="section">
        <h3>Igualas / Comisiones de administración</h3>
        <table>
            <thead>
                <tr>
                    <th>Periodo / fecha a la que corresponde</th>
                    <th>Folio</th>
                    <th>Propiedad</th>
                    <th>Notas</th>
                    <th class="text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach($igualas as $m)
                    <tr>
                        <td>{{ optional($m->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $m->folio ?? '—' }}</td>
                        <td>{{ $m->propiedad->alias ?? '—' }}</td>
                        <td>{{ $m->notas ?? '—' }}</td>
                        <td class="text-right">${{ number_format((float) $m->importe, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- 8) Pagos al cliente --}}
@if(isset($pagosCliente) && $pagosCliente->isNotEmpty())
    <div class="section">
        <h3>Pagos al cliente</h3>
        <table>
            <thead>
                <tr>
                    <th>Periodo / fecha a la que corresponde</th>
                    <th class="text-right">Importe</th>
                    <th>Forma</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pagosCliente as $m)
                    <tr>
                        <td>{{ optional($m->fecha)->format('d/m/Y') }}</td>
                        <td class="text-right">${{ number_format((float) $m->importe, 2) }}</td>
                        <td>{{ ucfirst($m->forma_pago) }}</td>
                        <td>{{ $m->notas ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="closing-block">
{{-- 9) Resumen --}}
@if(isset($resumen))
    <div class="section">
        <h3>Resumen</h3>
        <table class="summary-table">
            <tbody>
                @foreach([
                    'INGRESOS DEL PERIODO' => $resumen['ingresos_efectivo'] ?? 0,
                    'TOTAL DEPOSITOS' => $resumen['total_depositos'] ?? 0,
                    'EGRESOS DEL PERIODO' => $resumen['gastos_efectivo'] ?? 0,
                    'TOTAL DESPUÉS DE GASTOS' => $resumen['total_despues_gastos'] ?? 0,
                    'IGUALA / COMISIÓN DE ADMINISTRACIÓN (INCLUIDA EN EGRESOS)' => $resumen['iguala'] ?? 0,
                    'PAGOS AL CLIENTE (MES)' => $resumen['pagos_cliente_mes'] ?? 0,
                    'SALDO DE MESES ANTERIORES' => $resumen['saldo_anterior'] ?? 0,
                    'SALDO ANTERIOR CONTABLE' => $resumen['saldo_anterior_contable'] ?? 0,
                    'SALDO ANTERIOR LIQUIDADO' => $resumen['saldo_anterior_liquidado'] ?? 0,
                    'PENDIENTE POR COBRAR' => $resumen['pendiente_por_cobrar'] ?? 0,
                    'PENDIENTE POR PAGAR / LIQUIDAR' => $resumen['pendiente_por_pagar_o_liquidar'] ?? 0,
                    'TOTAL A PAGAR DEL MES' => $resumen['total_mes'] ?? 0,
                    'SALDO PERIODO LIQUIDADO' => $resumen['saldo_periodo_liquidado'] ?? 0,
                    'SALDO CONTABLE FINAL' => $resumen['saldo_contable'] ?? $resumen['total_incluye_saldos'] ?? 0,
                    'SALDO LIQUIDADO / DISPONIBLE' => $resumen['saldo_liquidado'] ?? 0,
                ] as $etiqueta => $importe)
                    @if((float) $importe !== 0.0)
                        <tr><td>{{ $etiqueta }}</td><td class="text-right">${{ number_format((float) $importe, 2) }}</td></tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Pie con la firma --}}
<div class="signature">
    GUADALAJARA, JALISCO AL {{ \Carbon\Carbon::now()->isoFormat('dddd D [de] MMMM [del] YYYY') }}<br><br>
    A&nbsp;T&nbsp;E&nbsp;N&nbsp;T&nbsp;A&nbsp;M&nbsp;E&nbsp;N&nbsp;T&nbsp;E<br><br>
    <div class="line"></div>
    Dorantes Aranda &amp; Asociados<br>
    Abogados e Inmobiliarios
</div>
</div>

</body>
</html>
