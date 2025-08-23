<?php

namespace App\Providers;

use App\Domain\Repositories\DiscountCodeRepositoryInterface;
use App\Domain\Repositories\FeedbackRepositoryInterface;
use App\Infrastructure\Repositories\EloquentDiscountCodeRepository;
use App\Infrastructure\Repositories\EloquentFeedbackRepository;
use App\UseCases\Feedback\ApplyDiscountCodeUseCase;
use App\UseCases\Feedback\GenerateDiscountCodeUseCase;
use App\UseCases\Feedback\MakeSellerFeaturedUseCase;
use App\UseCases\Feedback\ReviewFeedbackUseCase;
use App\UseCases\Feedback\SubmitFeedbackUseCase;
use Illuminate\Support\ServiceProvider;

class FeedbackServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register repositories
        $this->app->bind(FeedbackRepositoryInterface::class, EloquentFeedbackRepository::class);
        $this->app->bind(DiscountCodeRepositoryInterface::class, EloquentDiscountCodeRepository::class);

        // Register use cases
        $this->app->bind(SubmitFeedbackUseCase::class, function ($app) {
            return new SubmitFeedbackUseCase(
                $app->make(FeedbackRepositoryInterface::class),
                $app->make(\App\Domain\Repositories\SellerRepositoryInterface::class)
            );
        });

        $this->app->bind(ReviewFeedbackUseCase::class, function ($app) {
            return new ReviewFeedbackUseCase(
                $app->make(FeedbackRepositoryInterface::class),
                $app->make(\App\UseCases\Feedback\MakeSellerFeaturedUseCase::class)
            );
        });

        $this->app->bind(GenerateDiscountCodeUseCase::class, function ($app) {
            return new GenerateDiscountCodeUseCase(
                $app->make(DiscountCodeRepositoryInterface::class),
                $app->make(FeedbackRepositoryInterface::class),
                $app->make(\App\Services\NotificationService::class)
            );
        });

        $this->app->bind(ApplyDiscountCodeUseCase::class, function ($app) {
            return new ApplyDiscountCodeUseCase(
                $app->make(DiscountCodeRepositoryInterface::class),
                $app->make(\App\Domain\Repositories\ProductRepositoryInterface::class)
            );
        });

        $this->app->bind(MakeSellerFeaturedUseCase::class, function ($app) {
            return new MakeSellerFeaturedUseCase(
                $app->make(\App\Domain\Repositories\SellerRepositoryInterface::class)
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
