<?php

namespace App\UseCases\Product;

use App\Domain\Entities\ProductEntity;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\UseCases\Recommendation\TrackUserInteractionsUseCase;

class GetProductDetailsUseCase
{
    private ProductRepositoryInterface $productRepository;

    private ?TrackUserInteractionsUseCase $trackUserInteractionsUseCase;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ?TrackUserInteractionsUseCase $trackUserInteractionsUseCase = null
    ) {
        $this->productRepository = $productRepository;
        $this->trackUserInteractionsUseCase = $trackUserInteractionsUseCase;
    }

    /**
     * Obtiene los detalles de un producto por su ID
     *
     * @param  int|null  $userId  ID del usuario que está viendo el producto
     */
    public function execute(int $productId, ?int $userId = null): ?ProductEntity
    {
        // Incrementar el contador de vistas
        $this->productRepository->incrementViewCount($productId);

        // Buscar el producto
        $product = $this->productRepository->findById($productId);

        if (! $product) {
            return null;
        }

        // Registrar la interacción del usuario si está disponible
        if ($userId && $this->trackUserInteractionsUseCase) {
            $this->trackUserInteractionsUseCase->execute(
                $userId,
                'view_product',
                $productId,
                [
                    'view_time' => time(), // Tiempo inicial de vista
                    'product_category' => $product->getCategoryId(),
                    'product_price' => $product->getPrice(),
                    'product_tags' => $product->getTags(),
                ]
            );
        }

        return $product;
    }

    /**
     * Obtiene los detalles de un producto por su slug
     *
     * @param  int|null  $userId  ID del usuario que está viendo el producto
     */
    public function executeBySlug(string $slug, ?int $userId = null): ?ProductEntity
    {
        // Buscar el producto por slug
        $product = $this->productRepository->findBySlug($slug);

        if (! $product) {
            return null;
        }

        // Incrementar el contador de vistas
        $this->productRepository->incrementViewCount($product->getId());

        // Registrar la interacción del usuario si está disponible
        if ($userId && $this->trackUserInteractionsUseCase) {
            $this->trackUserInteractionsUseCase->execute(
                $userId,
                'view_product',
                $product->getId(),
                [
                    'view_time' => time(), // Tiempo inicial de vista
                    'product_category' => $product->getCategoryId(),
                    'product_price' => $product->getPrice(),
                    'product_tags' => $product->getTags(),
                ]
            );
        }

        return $product;
    }
}
