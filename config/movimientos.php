<?php

return [
    'comprobantes' => [
        'disk' => env('MOVIMIENTOS_COMPROBANTES_DISK', 'r2'),
        'max_kib' => (int) env('MOVIMIENTOS_COMPROBANTE_MAX_KIB', 51200),
        'allowed_disks' => ['public', 'r2'],
        'temporary_url_minutes' => 5,
    ],
];
