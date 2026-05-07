<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    public function boot(): void
    {
        // Solo admin puede eliminar
        Gate::define('delete-anything', fn($user) => $user->canDeleteRecords());

        // Admin y agent pueden crear/editar
        Gate::define('manage-records', fn($user) => $user->canManageRecords());

        // Solo admin puede gestionar usuarios
        Gate::define('manage-users', fn($user) => $user->isAdmin());
    }
}
