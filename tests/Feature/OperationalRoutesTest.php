<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OperationalRoutesTest extends TestCase
{
    private const OPERATIONAL_URIS = [
        '__backfill_contratos_fk__',
        '__migrate_status__',
        '__migrate_dry_run__',
        '__run_migrate__',
        '__clear_caches__',
    ];

    public function test_operational_routes_are_not_registered_when_route_list_is_built_for_production(): void
    {
        $process = new Process([
            PHP_BINARY,
            base_path('artisan'),
            'route:list',
            '--env=production',
            '--json',
        ], base_path());
        $process->setEnv([
            'APP_ENV' => 'production',
            'APP_KEY' => config('app.key'),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
        ]);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        $routes = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $uris = collect($routes)->pluck('uri')->all();

        foreach (self::OPERATIONAL_URIS as $uri) {
            $this->assertNotContains($uri, $uris);
        }
    }

    public function test_normal_web_and_forms_integration_routes_remain_registered(): void
    {
        foreach ([
            'clientes.index',
            'contratos.index',
            'movimientos.index',
            'movimientos.store',
            'contratos.justicia-alternativa',
        ] as $name) {
            $this->assertTrue(Route::has($name), "Missing normal route [{$name}].");
        }

        $route = Route::getRoutes()->match(Request::create('/api/forms/contratos', 'POST'));

        $this->assertSame('api/forms/contratos', $route->uri());
        $this->assertSame('App\\Http\\Controllers\\Api\\FormsIntakeController@storeContrato', $route->getActionName());
    }
}
