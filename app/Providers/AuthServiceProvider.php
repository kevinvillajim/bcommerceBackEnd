<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Las asignaciones de políticas para la aplicación.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Product::class => \App\Policies\ProductPolicy::class,
        \App\Models\Category::class => \App\Policies\CategoryPolicy::class,
        \App\Models\Rating::class => \App\Policies\RatingPolicy::class,
        // Agrega aquí más políticas
    ];

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // JwtServiceInterface ahora se registra en InterfacesServiceProvider
    }

    /**
     * Bootstrap any authentication services.
     */
    public function boot(): void
    {
        // Registrar todas las políticas
        $this->registerPolicies();

        // Define aquí tus Gates personalizados
        // Gate::define('admin', function ($user) {
        //     return $user->isAdmin();
        // });
    }
}
