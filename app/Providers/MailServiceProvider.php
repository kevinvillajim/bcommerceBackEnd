<?php

namespace App\Providers;

use App\Services\ConfigurationService;
use App\Services\Mail\MailManager;
use App\Services\MailService;
use Illuminate\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the new MailManager as the primary mail service
        $this->app->singleton(MailManager::class, function ($app) {
            return new MailManager($app->make(ConfigurationService::class));
        });

        // Register the new MailService which uses MailManager internally
        $this->app->singleton(MailService::class, function ($app) {
            return new MailService($app->make(MailManager::class));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish email configuration
        $this->publishes([
            __DIR__.'/../../config/emails.php' => config_path('emails.php'),
        ], 'email-config');

        // Publish email templates
        $this->publishes([
            __DIR__.'/../../resources/views/emails' => resource_path('views/emails'),
        ], 'email-templates');
    }
}
