<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Cliente;
use App\Models\Propiedad;
use App\Models\Contrato;
use App\Models\Inquilino;
use App\Observers\ActivityObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-users', fn($user) => $user->role === 'admin');
        Gate::define('delete-anything', fn($user) => $user->role === 'admin');
        Cliente::observe(ActivityObserver::class);
    Propiedad::observe(ActivityObserver::class);
    Contrato::observe(ActivityObserver::class);
    Inquilino::observe(ActivityObserver::class);
    }
}
