<?php

namespace App\Providers;

use App\Domain\Repositories\AdminDiscountCodeRepositoryInterface;
use App\Domain\Repositories\AdminLogRepositoryInterface;
use App\Domain\Repositories\CategoryRepositoryInterface;
// Interfaces
use App\Domain\Repositories\ChatRepositoryInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\RatingRepositoryInterface;
use App\Domain\Repositories\SellerRepositoryInterface;
use App\Domain\Repositories\ShippingRepositoryInterface;
use App\Domain\Repositories\ShoppingCartRepositoryInterface;
use App\Domain\Repositories\UserProfileRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Infrastructure\Repositories\EloquentAdminDiscountCodeRepository;
use App\Infrastructure\Repositories\EloquentAdminLogRepository;
use App\Infrastructure\Repositories\EloquentCategoryRepository;
// Implementaciones
use App\Infrastructure\Repositories\EloquentChatRepository;
use App\Infrastructure\Repositories\EloquentOrderRepository;
use App\Infrastructure\Repositories\EloquentProductRepository;
use App\Infrastructure\Repositories\EloquentRatingRepository;
use App\Infrastructure\Repositories\EloquentSellerRepository;
use App\Infrastructure\Repositories\EloquentShippingRepository;
use App\Infrastructure\Repositories\EloquentShoppingCartRepository;
use App\Infrastructure\Repositories\EloquentUserProfileRepository;
use App\Infrastructure\Repositories\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Mapeo de interfaces con sus implementaciones concretas
     *
     * @var array
     */
    protected $repositories = [
        UserRepositoryInterface::class => EloquentUserRepository::class,
        UserProfileRepositoryInterface::class => EloquentUserProfileRepository::class,
        ProductRepositoryInterface::class => EloquentProductRepository::class,
        ShoppingCartRepositoryInterface::class => EloquentShoppingCartRepository::class,
        OrderRepositoryInterface::class => EloquentOrderRepository::class,
        ShippingRepositoryInterface::class => EloquentShippingRepository::class, // Descomentar cuando tengas esta clase
        ChatRepositoryInterface::class => EloquentChatRepository::class, // Descomentar cuando tengas esta clase
        SellerRepositoryInterface::class => EloquentSellerRepository::class,
        RatingRepositoryInterface::class => EloquentRatingRepository::class,
        CategoryRepositoryInterface::class => EloquentCategoryRepository::class,
        AdminDiscountCodeRepositoryInterface::class => EloquentAdminDiscountCodeRepository::class,
        AdminLogRepositoryInterface::class => EloquentAdminLogRepository::class,

    ];

    /**
     * Register all application repositories.
     */
    public function register(): void
    {
        // Registrar todos los repositorios definidos en el mapeo
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }

        // PaymentGatewayInterface ahora se registra en InterfacesServiceProvider
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
