<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body    { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .title  { text-align: center; font-size: 20px; font-weight: bold; margin-bottom: 20px; }
        .info   { margin-bottom: 10px; }
        .label  { font-weight: bold; }
        .amount { font-size: 18px; font-weight: bold; }
        .folio { text-align: right; font-size: 13px; font-weight: bold; margin-bottom: 16px; }
        .firma  { margin-top: 40px; text-align: center; }
        .firma .fecha { font-weight: bold; margin-bottom: 20px; }
        .firma .atentamente { margin-bottom: 60px; font-weight:bold }
        .centrar {text-align:center;}
        .mb-50 { margin-bottom:50px; }
    </style>
</head>
<body>
    @php
        $conceptMap = [
            'deposito' => 'Depósito en garantía',
            'renta' => 'Pago de renta',
            'iguala' => 'Iguala / Comisión de administración',
        ];
        $estadoPagoMap = [
            'pendiente' => 'Pendiente',
            'liquidado' => 'Liquidado',
            'cancelado' => 'Cancelado',
        ];
        // Formatear la fecha actual en español: Martes 30 de Septiembre del 2025
        \Carbon\Carbon::setLocale('es');
        $fechaActual = \Carbon\Carbon::now()->isoFormat('dddd D [de] MMMM [del] YYYY');
    @endphp
     
    <div class="header">
        <div class="mb-50" style="text-align:center;">
            <img src="{{ public_path('images/logo.png') }}" alt="Logo de la inmobiliaria" style="width:250px;">
        </div>
        <div class="folio">Folio: {{ $movimiento->folio ?? '—' }}</div>

        @php
            $cliente = $movimiento->cliente_final?->nombre ?? 'cliente no especificado';
            $importe = number_format((float) $movimiento->importe, 2);
            $fecha = optional($movimiento->fecha);
            $mes = $fecha ? $fecha->translatedFormat('F') : '';
            $anio = $fecha ? $fecha->year : '';
            $propiedad = $movimiento->propiedad;
            $direccion = $propiedad
                ? trim(($propiedad->calle ?? '').' '.($propiedad->numero ?? '').', '.($propiedad->colonia ?? '').', '.($propiedad->ciudad ?? '').', '.($propiedad->estado ?? ''), ', ')
                : '';
            $propiedadTexto = $propiedad
                ? trim(($propiedad->alias ? 'PREDIO #'.$propiedad->alias : 'PREDIO').($direccion ? ', '.$direccion : ''), ', ')
                : 'MOVIMIENTO ASIGNADO A '.$movimiento->asignado_nombre;
            $inquilinoTexto = $movimiento->inquilino ? ' ARRENDATARIO: '.$movimiento->inquilino->nombre.'.' : '';
        @endphp

        <p style="margin-bottom:15px;">
            RECIBÍ EN REPRESENTACIÓN DE {{ $cliente }},
            LA CANTIDAD DE ${{ $importe }}
            POR CONCEPTO DE RENTA DEL MES DE {{ strtoupper($mes) }} Y AÑO {{ $anio }},
            RESPECTO A {{ $propiedadTexto }}.{{ $inquilinoTexto }}
        </p>

       
    </div>

   
    <div class="notes centrar">
        <strong>Forma de pago:</strong> {{ $movimiento->forma_pago }}
    </div>
    <div class="notes centrar">
        <strong>Estado de pago:</strong> {{ $estadoPagoMap[$movimiento->estado_pago] ?? 'Liquidado' }}
    </div>
    @if($movimiento->fecha_liquidacion)
    <div class="notes centrar">
        <strong>Fecha de liquidación:</strong> {{ $movimiento->fecha_liquidacion->format('Y-m-d') }}
    </div>
    @endif
    @if(!empty($movimiento->notas))
    <div class="notes  centrar">
        <strong>Notas:</strong> {{ $movimiento->notas }}
    </div>
    @endif

    <!-- Espacio para la firma -->
    <div class="firma">
        <div class="fecha">
            GUADALAJARA, JALISCO AL {{ strtoupper($fechaActual) }}
        </div>
        <div class="atentamente">
            A&nbsp;T&nbsp;E&nbsp;N&nbsp;T&nbsp;A&nbsp;M&nbsp;E&nbsp;N&nbsp;T&nbsp;E
        </div>
        <div style="border-top:1px solid #000; width:40%; margin:0 auto 5px auto;"></div>
        <div>
            Dorantes Aranda &amp; Asociados<br>
            Abogados e Inmobiliarios
        </div>
    </div>

<br>
    <div class="footer centrar">
        Este recibo es un comprobante de pago. Conserve para cualquier aclaración.
    </div>
</body>
</html>
