<?php

namespace App\Providers;

use App\Domain\Repositories\NotificationRepositoryInterface;
use App\Infrastructure\Repositories\EloquentNotificationRepository;
use App\Infrastructure\Services\NotificationService;
use App\UseCases\Notification\CheckProductUpdatesForUsersUseCase;
use App\UseCases\Notification\DeleteNotificationUseCase;
use App\UseCases\Notification\GetUserNotificationsUseCase;
use App\UseCases\Notification\MarkAllNotificationsAsReadUseCase;
use App\UseCases\Notification\MarkNotificationAsReadUseCase;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar el repositorio
        $this->app->bind(NotificationRepositoryInterface::class, EloquentNotificationRepository::class);

        // Registrar el servicio de notificaciones
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService(
                $app->make(NotificationRepositoryInterface::class)
            );
        });

        // Registrar casos de uso
        $this->app->bind(GetUserNotificationsUseCase::class, function ($app) {
            return new GetUserNotificationsUseCase(
                $app->make(NotificationRepositoryInterface::class)
            );
        });

        $this->app->bind(MarkNotificationAsReadUseCase::class, function ($app) {
            return new MarkNotificationAsReadUseCase(
                $app->make(NotificationRepositoryInterface::class)
            );
        });

        $this->app->bind(MarkAllNotificationsAsReadUseCase::class, function ($app) {
            return new MarkAllNotificationsAsReadUseCase(
                $app->make(NotificationRepositoryInterface::class)
            );
        });

        $this->app->bind(DeleteNotificationUseCase::class, function ($app) {
            return new DeleteNotificationUseCase(
                $app->make(NotificationRepositoryInterface::class)
            );
        });

        $this->app->bind(CheckProductUpdatesForUsersUseCase::class, function ($app) {
            return new CheckProductUpdatesForUsersUseCase(
                $app->make(NotificationService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
