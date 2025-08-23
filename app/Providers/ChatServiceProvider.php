<?php

namespace App\Providers;

use App\Domain\Interfaces\ChatFilterInterface;
use App\Domain\Repositories\ChatRepositoryInterface;
use App\Infrastructure\Repositories\EloquentChatRepository;
use App\Infrastructure\Services\ChatFilterService;
use Illuminate\Support\ServiceProvider;

class ChatServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ChatRepositoryInterface::class, EloquentChatRepository::class);
        $this->app->bind(ChatFilterInterface::class, ChatFilterService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
