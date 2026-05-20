<?php

/**
 * TEMPORARY DEPLOY RUNNER
 *
 * Usage:
 * 1. Upload/pull this file to production.
 * 2. Open: https://your-domain.com/deploy-run.php?token=CHANGE_THIS_TOKEN
 * 3. Delete this file immediately after deployment.
 */

$token = 'CAMBIA_ESTE_TOKEN_LARGO_ANTES_DE_USAR';

if (!isset($_GET['token']) || !hash_equals($token, $_GET['token'])) {
    http_response_code(403);
    exit('Forbidden');
}

set_time_limit(0);
ini_set('display_errors', '1');
error_reporting(E_ALL);

$root = dirname(__DIR__);
chdir($root);

header('Content-Type: text/plain; charset=UTF-8');

echo "Deploy runner started\n";
echo "Project root: {$root}\n\n";

$commands = [
    'php -v',
    'composer install --no-dev --optimize-autoloader 2>&1',
    'php artisan migrate --force 2>&1',
    'php artisan storage:link 2>&1',
    'php artisan optimize:clear 2>&1',
    'php artisan config:cache 2>&1',
    'php artisan route:cache 2>&1',
    'php artisan view:cache 2>&1',
];

foreach ($commands as $command) {
    echo "\n============================================================\n";
    echo "$ {$command}\n";
    echo "============================================================\n";

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    echo implode("\n", $output) . "\n";
    echo "Exit code: {$exitCode}\n";

    if ($exitCode !== 0) {
        echo "\nCommand failed. Stopping deploy.\n";
        exit($exitCode);
    }
}

echo "\nDeploy completed successfully.\n";
echo "IMPORTANT: Delete public/deploy-run.php now.\n";
