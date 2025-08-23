<?php

namespace App\UseCases\Product;

use App\Domain\Repositories\ProductRepositoryInterface;
use App\UseCases\Recommendation\TrackUserInteractionsUseCase;

class IncrementProductViewUseCase
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
     * Incrementa el contador de vistas de un producto
     *
     * @param  array  $metadata  Datos adicionales sobre la vista
     */
    public function execute(int $productId, ?int $userId = null, array $metadata = []): bool
    {
        // Incrementar el contador en la base de datos
        $success = $this->productRepository->incrementViewCount($productId);

        // Si hay un usuario autenticado, registrar la interacción
        if ($success && $userId && $this->trackUserInteractionsUseCase) {
            // Obtener detalles del producto si es necesario
            $product = $this->productRepository->findById($productId);

            if ($product) {
                $interactionData = array_merge($metadata, [
                    'view_time' => $metadata['view_time'] ?? time(),
                    'product_category' => $product->getCategoryId(),
                    'product_price' => $product->getPrice(),
                    'product_name' => $product->getName(),
                    'product_tags' => $product->getTags(),
                    'product_colors' => $product->getColors(),
                    'product_sizes' => $product->getSizes(),
                ]);

                $this->trackUserInteractionsUseCase->execute(
                    $userId,
                    'view_product',
                    $productId,
                    $interactionData
                );
            }
        }

        return $success;
    }
}
