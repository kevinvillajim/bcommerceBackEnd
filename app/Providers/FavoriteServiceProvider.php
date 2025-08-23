<?php

namespace App\Providers;

use App\Domain\Repositories\FavoriteRepositoryInterface;
use App\Infrastructure\Repositories\EloquentFavoriteRepository;
use Illuminate\Support\ServiceProvider;

class FavoriteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(FavoriteRepositoryInterface::class, EloquentFavoriteRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
