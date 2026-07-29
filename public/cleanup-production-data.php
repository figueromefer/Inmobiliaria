<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const CLEANUP_TOKEN = 'REEMPLAZA_ESTE_TOKEN_LARGO_ANTES_DE_SUBIR_8f4f1b0d62c94759b6b5bb4f6d6d4f75';
const CONFIRMATION_PHRASE = 'BORRAR DATOS OPERATIVOS DE INMOBILIARIA';

$markerPath = storage_path('app/cleanup-production-data.executed.json');

$operationalTables = [
    'activity_logs',
    'maintenance_comments',
    'maintenance_tickets',
    'documentos',
    'movimientos',
    'tasks',
    'contratos_pendientes',
    'contratos',
    'propiedades',
    'inquilinos',
    'clientes',
];

$technicalTables = [
    'jobs',
    'failed_jobs',
    'job_batches',
    'cache_locks',
    'cache',
    'sessions',
    'password_reset_tokens',
];

$tablesToClean = array_values(array_unique(array_merge($operationalTables, $technicalTables)));

function respond(int $status, string $title, array $payload = []): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . cleanupEsc($title) . '</title>';
    echo '<style>body{font-family:Arial,sans-serif;margin:24px;line-height:1.4;color:#111827}';
    echo 'table{border-collapse:collapse;width:100%;margin-top:16px}td,th{border:1px solid #d1d5db;padding:8px;text-align:left}';
    echo 'th{background:#f3f4f6}.ok{color:#047857}.warn{color:#b45309}.error{color:#b91c1c}';
    echo 'code,pre{background:#f3f4f6;padding:2px 4px;border-radius:4px}pre{padding:12px;overflow:auto}</style>';
    echo '</head><body>';
    echo '<h1>' . cleanupEsc($title) . '</h1>';

    if (isset($payload['message'])) {
        echo '<p>' . cleanupEsc((string) $payload['message']) . '</p>';
    }

    if (isset($payload['results']) && is_array($payload['results'])) {
        echo '<table><thead><tr><th>Tabla</th><th>Estado</th><th>Antes</th><th>Eliminados</th><th>Después</th></tr></thead><tbody>';
        foreach ($payload['results'] as $row) {
            echo '<tr>';
            echo '<td><code>' . cleanupEsc((string) ($row['table'] ?? '')) . '</code></td>';
            echo '<td>' . cleanupEsc((string) ($row['status'] ?? '')) . '</td>';
            echo '<td>' . cleanupEsc((string) ($row['before'] ?? '-')) . '</td>';
            echo '<td>' . cleanupEsc((string) ($row['deleted'] ?? '-')) . '</td>';
            echo '<td>' . cleanupEsc((string) ($row['after'] ?? '-')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    if (isset($payload['details'])) {
        echo '<h2>Detalle</h2><pre>' . cleanupEsc(json_encode($payload['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
    }

    echo '<p class="warn"><strong>Importante:</strong> elimina este archivo del servidor inmediatamente después de usarlo.</p>';
    echo '</body></html>';
    exit;
}

function cleanupEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function configuredTokenIsSafe(): bool
{
    return ! str_starts_with(CLEANUP_TOKEN, 'REEMPLAZA_')
        && strlen(CLEANUP_TOKEN) >= 48;
}

function tableCount(string $table): ?int
{
    if (! Schema::hasTable($table)) {
        return null;
    }

    return (int) DB::table($table)->count();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'Metodo no permitido', [
        'message' => 'Este script solo acepta POST con token. No ejecuta preview ni limpieza por GET.',
    ]);
}

if (! configuredTokenIsSafe()) {
    respond(403, 'Token no configurado', [
        'message' => 'Antes de subir este archivo, reemplaza CLEANUP_TOKEN por un token largo y privado.',
    ]);
}

$token = (string) ($_POST['token'] ?? '');
$mode = (string) ($_POST['mode'] ?? 'preview');
$confirmation = (string) ($_POST['confirmation'] ?? '');

if (! hash_equals(CLEANUP_TOKEN, $token)) {
    respond(403, 'Token invalido', [
        'message' => 'Token incorrecto.',
    ]);
}

if (! in_array($mode, ['preview', 'run'], true)) {
    respond(422, 'Modo invalido', [
        'message' => 'Usa mode=preview o mode=run.',
    ]);
}

if (file_exists($markerPath)) {
    respond(409, 'Limpieza ya ejecutada', [
        'message' => 'Existe el marcador de ejecucion. No se permite repetir la limpieza con este archivo.',
        'details' => ['marker' => $markerPath],
    ]);
}

$results = [];

foreach ($tablesToClean as $table) {
    $count = tableCount($table);
    $results[] = [
        'table' => $table,
        'status' => $count === null ? 'no existe' : 'pendiente',
        'before' => $count,
        'deleted' => 0,
        'after' => $count,
    ];
}

if ($mode === 'preview') {
    respond(200, 'Preview de limpieza de datos operativos', [
        'message' => 'No se modificaron datos. Revisa conteos, backup y orden antes de ejecutar mode=run.',
        'results' => $results,
        'details' => [
            'confirmation_required_for_run' => CONFIRMATION_PHRASE,
            'foreign_key_checks' => 'No se desactivan. Las tablas se limpian en orden de dependencias.',
            'preserved_tables' => ['users', 'migrations'],
        ],
    ]);
}

if (! hash_equals(CONFIRMATION_PHRASE, $confirmation)) {
    respond(422, 'Confirmacion requerida', [
        'message' => 'Para ejecutar la limpieza real debes enviar la frase exacta de confirmacion.',
        'details' => ['confirmation_phrase' => CONFIRMATION_PHRASE],
    ]);
}

$storageDir = dirname($markerPath);
if (! is_dir($storageDir) && ! mkdir($storageDir, 0755, true) && ! is_dir($storageDir)) {
    respond(500, 'Storage no disponible', [
        'message' => 'No se pudo preparar storage/app para crear el marcador de ejecucion.',
    ]);
}

if (! is_writable($storageDir)) {
    respond(500, 'Storage no escribible', [
        'message' => 'storage/app no es escribible. No se ejecuta la limpieza porque no podria crear el marcador.',
    ]);
}

try {
    $runResults = DB::transaction(function () use ($tablesToClean): array {
        $output = [];

        foreach ($tablesToClean as $table) {
            if (! Schema::hasTable($table)) {
                $output[] = [
                    'table' => $table,
                    'status' => 'no existe',
                    'before' => null,
                    'deleted' => 0,
                    'after' => null,
                ];
                continue;
            }

            $before = (int) DB::table($table)->count();
            $deleted = DB::table($table)->delete();
            $after = (int) DB::table($table)->count();

            $output[] = [
                'table' => $table,
                'status' => 'limpiada',
                'before' => $before,
                'deleted' => $deleted,
                'after' => $after,
            ];
        }

        return $output;
    });

    $marker = [
        'executed_at' => now()->toDateTimeString(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'tables' => array_column($runResults, 'table'),
    ];

    if (file_put_contents($markerPath, json_encode($marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        respond(500, 'Limpieza ejecutada, marcador no creado', [
            'message' => 'Los datos se limpiaron, pero no se pudo crear el marcador. Elimina este archivo inmediatamente.',
            'results' => $runResults,
        ]);
    }

    respond(200, 'Limpieza ejecutada', [
        'message' => 'Datos operativos limpiados. Elimina este archivo inmediatamente del servidor.',
        'results' => $runResults,
        'details' => ['marker' => $markerPath],
    ]);
} catch (Throwable $exception) {
    report($exception);

    respond(500, 'Error durante la limpieza', [
        'message' => 'La transaccion fue revertida. Revisa laravel.log. No se muestran detalles internos en pantalla.',
    ]);
}
