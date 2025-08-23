<?php

namespace App\Providers;

use App\Domain\Formatters\ProductFormatter;
use App\Domain\Formatters\UserProfileFormatter;
use App\Domain\Interfaces\RecommendationEngineInterface;
use App\Domain\Services\DemographicProfileGenerator;
use App\Domain\Services\ProfileCompletenessCalculator;
use App\Domain\Services\RecommendationStrategies\CategoryBasedStrategy;
use App\Domain\Services\RecommendationStrategies\DemographicBasedStrategy;
use App\Domain\Services\RecommendationStrategies\FavoritesBasedStrategy;
use App\Domain\Services\RecommendationStrategies\HistoryBasedStrategy;
use App\Domain\Services\RecommendationStrategies\InterestBasedStrategy;
use App\Domain\Services\RecommendationStrategies\PopularProductsStrategy;
use App\Domain\Services\RecommendationStrategies\TagBasedStrategy;
use App\Domain\Services\UserProfileEnricher;
use App\Infrastructure\Services\RecommendationService;
use App\Infrastructure\Services\UserInteractionService;
use Illuminate\Support\ServiceProvider;

class RecommendationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar servicios básicos
        $this->app->singleton(DemographicProfileGenerator::class);
        $this->app->singleton(ProfileCompletenessCalculator::class);
        $this->app->singleton(ProductFormatter::class);

        // Registrar formateador de perfil de usuario
        $this->app->singleton(UserProfileFormatter::class, function ($app) {
            return new UserProfileFormatter(
                $app->make(ProfileCompletenessCalculator::class),
                $app->make(ProductFormatter::class)
            );
        });

        // Registrar enriquecedor de perfil de usuario
        $this->app->singleton(UserProfileEnricher::class, function ($app) {
            return new UserProfileEnricher(
                $app->make(DemographicProfileGenerator::class)
            );
        });

        // Registrar estrategias
        $this->app->singleton(PopularProductsStrategy::class, function ($app) {
            return new PopularProductsStrategy(
                $app->make(ProductFormatter::class)
            );
        });

        $this->app->singleton(DemographicBasedStrategy::class, function ($app) {
            return new DemographicBasedStrategy(
                $app->make(DemographicProfileGenerator::class),
                $app->make(ProductFormatter::class)
            );
        });

        $this->app->singleton(CategoryBasedStrategy::class, function ($app) {
            return new CategoryBasedStrategy(
                $app->make('App\Domain\Repositories\UserProfileRepositoryInterface'),
                $app->make(ProductFormatter::class)
            );
        });

        $this->app->singleton(HistoryBasedStrategy::class, function ($app) {
            return new HistoryBasedStrategy(
                $app->make(ProductFormatter::class)
            );
        });

        $this->app->singleton(TagBasedStrategy::class, function ($app) {
            return new TagBasedStrategy(
                $app->make(ProductFormatter::class)
            );
        });

        $this->app->singleton(InterestBasedStrategy::class, function ($app) {
            return new InterestBasedStrategy(
                $app->make(ProductFormatter::class)
            );
        });

        // Servicio de interacciones
        $this->app->singleton(UserInteractionService::class, function ($app) {
            return new UserInteractionService(
                $app->make('App\Domain\Repositories\UserProfileRepositoryInterface')
            );
        });

        // Servicio de Favoritos
        $this->app->singleton(FavoritesBasedStrategy::class, function ($app) {
            return new FavoritesBasedStrategy(
                $app->make(\App\Domain\Repositories\FavoriteRepositoryInterface::class),
                $app->make(\App\Domain\Repositories\ProductRepositoryInterface::class),
                $app->make(ProductFormatter::class)
            );
        });

        // Registrar el servicio principal de recomendaciones
        $this->app->singleton(RecommendationEngineInterface::class, function ($app) {
            // CORREGIDO: Orden correcto de los parámetros en el constructor
            $service = new RecommendationService(
                $app->make('App\Domain\Repositories\UserProfileRepositoryInterface'),
                $app->make('App\Domain\Repositories\ProductRepositoryInterface'),
                $app->make(UserProfileEnricher::class),
                $app->make(DemographicProfileGenerator::class),
                $app->make(ProfileCompletenessCalculator::class), // Ahora es ProfileCompletenessCalculator
                $app->make(ProductFormatter::class),
                $app->make(UserProfileFormatter::class),
                [] // Este array vacío es para las estrategias, que se agregan después
            );

            // Agregar todas las estrategias
            $service->addStrategy($app->make(PopularProductsStrategy::class));
            $service->addStrategy($app->make(DemographicBasedStrategy::class));
            $service->addStrategy($app->make(CategoryBasedStrategy::class));
            $service->addStrategy($app->make(HistoryBasedStrategy::class));
            $service->addStrategy($app->make(TagBasedStrategy::class));
            $service->addStrategy($app->make(InterestBasedStrategy::class));
            $service->addStrategy($app->make(FavoritesBasedStrategy::class));

            return $service;
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
