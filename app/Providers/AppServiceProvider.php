<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Inquilino;
use App\Models\Movimiento;
use App\Models\Propiedad;
use App\Observers\ActivityObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::define('manage-users', fn($user) => $user->role === 'admin');

        Gate::define('manage-records', fn($user) =>
            in_array($user->role, ['admin', 'agent'])
        );

        Gate::define('delete-anything', fn($user) => $user->role === 'admin');

        Cliente::observe(ActivityObserver::class);
        Propiedad::observe(ActivityObserver::class);
        Contrato::observe(ActivityObserver::class);
        Inquilino::observe(ActivityObserver::class);
        Movimiento::observe(ActivityObserver::class);
    }
}
