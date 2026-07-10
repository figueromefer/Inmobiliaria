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
        .signature {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
        }
        .signature .line {
            margin: 30px auto 5px auto;
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
    {{-- Logo corporativo; coloca tu logo en public/img/logo.png --}}
    <img src="{{ public_path('imgages/logo.png') }}" alt="Logo">
    <h2>Reporte mensual - {{ $reporteFecha->translatedFormat('F Y') }}</h2>
</div>

{{-- 1) Rentas recabadas --}}
@if(isset($rentasRecabadas) && $rentasRecabadas->isNotEmpty())
    <div class="section">
        <h3>Rentas recabadas</h3>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
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
                    <th>Fecha asignada</th>
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
                    <th>Fecha</th>
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
                    <th>Fecha</th>
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
                    <th>Fecha</th>
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
                    <th>Fecha</th>
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
                    <th>Fecha</th>
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

{{-- 9) Resumen --}}
@if(isset($resumen))
    <div class="section">
        <h3>Resumen</h3>
        <table class="summary-table">
            <tbody>
                <tr><td>INGRESOS DEL PERIODO</td><td class="text-right">${{ number_format((float) ($resumen['ingresos_efectivo'] ?? 0), 2) }}</td></tr>
                <tr><td>TOTAL DEPOSITOS</td><td class="text-right">${{ number_format((float) ($resumen['total_depositos'] ?? 0), 2) }}</td></tr>
                <tr><td>EGRESOS DEL PERIODO</td><td class="text-right">${{ number_format((float) ($resumen['gastos_efectivo'] ?? 0), 2) }}</td></tr>
                <tr><td>TOTAL DESPUÉS DE GASTOS</td><td class="text-right">${{ number_format((float) ($resumen['total_despues_gastos'] ?? 0), 2) }}</td></tr>
                <tr><td>IGUALA / COMISIÓN DE ADMINISTRACIÓN (INCLUIDA EN EGRESOS)</td><td class="text-right">${{ number_format((float) ($resumen['iguala'] ?? 0), 2) }}</td></tr>
                <tr><td>PAGOS AL CLIENTE (MES)</td><td class="text-right">${{ number_format((float) ($resumen['pagos_cliente_mes'] ?? 0), 2) }}</td></tr>
                <tr><td>SALDO DE MESES ANTERIORES</td><td class="text-right">${{ number_format((float) ($resumen['saldo_anterior'] ?? 0), 2) }}</td></tr>
                <tr><td>SALDO ANTERIOR CONTABLE</td><td class="text-right">${{ number_format((float) ($resumen['saldo_anterior_contable'] ?? 0), 2) }}</td></tr>
                <tr><td>SALDO ANTERIOR LIQUIDADO</td><td class="text-right">${{ number_format((float) ($resumen['saldo_anterior_liquidado'] ?? 0), 2) }}</td></tr>
                <tr><td>PENDIENTE POR COBRAR</td><td class="text-right">${{ number_format((float) ($resumen['pendiente_por_cobrar'] ?? 0), 2) }}</td></tr>
                <tr><td>PENDIENTE POR PAGAR / LIQUIDAR</td><td class="text-right">${{ number_format((float) ($resumen['pendiente_por_pagar_o_liquidar'] ?? 0), 2) }}</td></tr>
                <tr><td>TOTAL A PAGAR DEL MES</td><td class="text-right">${{ number_format((float) ($resumen['total_mes'] ?? 0), 2) }}</td></tr>
                <tr><td>SALDO PERIODO LIQUIDADO</td><td class="text-right">${{ number_format((float) ($resumen['saldo_periodo_liquidado'] ?? 0), 2) }}</td></tr>
                <tr><td>SALDO CONTABLE FINAL</td><td class="text-right">${{ number_format((float) ($resumen['saldo_contable'] ?? $resumen['total_incluye_saldos'] ?? 0), 2) }}</td></tr>
                <tr><td>SALDO LIQUIDADO / DISPONIBLE</td><td class="text-right">${{ number_format((float) ($resumen['saldo_liquidado'] ?? 0), 2) }}</td></tr>
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

</body>
</html>
