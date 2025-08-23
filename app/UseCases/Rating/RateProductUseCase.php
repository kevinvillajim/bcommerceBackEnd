<?php

namespace App\UseCases\Rating;

use App\Domain\Entities\RatingEntity;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\RatingRepositoryInterface;
use App\Models\Rating;
use App\Services\ConfigurationService;

class RateProductUseCase
{
    private RatingRepositoryInterface $ratingRepository;

    private ProductRepositoryInterface $productRepository;

    private OrderRepositoryInterface $orderRepository;

    private ConfigurationService $configService;

    /**
     * Constructor
     */
    public function __construct(
        RatingRepositoryInterface $ratingRepository,
        ProductRepositoryInterface $productRepository,
        OrderRepositoryInterface $orderRepository,
        ConfigurationService $configService
    ) {
        $this->ratingRepository = $ratingRepository;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->configService = $configService;
    }

    /**
     * Execute the use case
     */
    public function execute(
        int $userId,
        int $productId,
        float $rating,
        ?int $orderId = null,
        ?string $title = null,
        ?string $comment = null
    ): RatingEntity {
        // Validación rating value
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('La valoración debe estar entre 1 y 5');
        }

        // Comprobar si el producto existe
        $product = $this->productRepository->findById($productId);
        if (! $product) {
            throw new \InvalidArgumentException('Producto no encontrado');
        }

        // Comprobar si el usuario ya valoró este producto en esta orden específica
        if ($orderId && $this->ratingRepository->hasUserRatedProduct($userId, $productId, $orderId)) {
            throw new \InvalidArgumentException('El usuario ya ha valorado este producto en esta orden');
        }

        // Si no hay orden específica, verificar si ya valoró este producto globalmente
        if (! $orderId && $this->ratingRepository->hasUserRatedProduct($userId, $productId)) {
            throw new \InvalidArgumentException('El usuario ya ha valorado este producto');
        }

        // Si se proporciona ID de orden, validarla
        if ($orderId) {
            $order = $this->orderRepository->findById($orderId);
            if (! $order) {
                throw new \InvalidArgumentException('Orden no encontrada');
            }

            // Comprobar si la orden pertenece a este usuario
            if ($order->getUserId() !== $userId) {
                throw new \InvalidArgumentException('La orden no pertenece a este usuario');
            }

            // Comprobar si la orden contiene este producto
            if (! $this->orderRepository->orderContainsProduct($orderId, $productId)) {
                throw new \InvalidArgumentException('La orden no contiene este producto');
            }

            // Comprobar si la orden está completada, entregada o enviada
            if ($order->getStatus() !== 'completed' && $order->getStatus() !== 'delivered' && $order->getStatus() !== 'shipped') {
                throw new \InvalidArgumentException('Solo se pueden valorar productos de órdenes completadas, entregadas o enviadas');
            }
        }

        // Determinar el estado del rating según la configuración y el valor
        $status = $this->determineRatingStatus($rating);

        // Crear entidad de valoración
        $ratingEntity = new RatingEntity(
            $userId,
            $rating,
            Rating::TYPE_USER_TO_PRODUCT,
            null, // No seller ID para valoraciones de productos
            $orderId,
            $productId,
            $title,
            $comment,
            $status
        );

        // Guardar valoración
        return $this->ratingRepository->create($ratingEntity);
    }

    /**
     * Determina el estado inicial de la valoración basado en la configuración y el valor
     *
     * @param  float  $rating  El valor de la valoración (1-5)
     * @return string El estado de la valoración ("pending" o "approved")
     */
    private function determineRatingStatus(float $rating): string
    {
        // Verificar si las aprobaciones automáticas están activadas
        $autoApproveAll = $this->configService->getConfig('ratings.auto_approve_all', false);

        if ($autoApproveAll) {
            return Rating::STATUS_APPROVED;
        }

        // Obtener el umbral de aprobación automática (por defecto 2)
        $autoApproveThreshold = $this->configService->getConfig('ratings.auto_approve_threshold', 2);

        // Aprobar automáticamente si la valoración es mayor que el umbral
        if ($rating > $autoApproveThreshold) {
            return Rating::STATUS_APPROVED;
        }

        // En caso contrario, marcar como pendiente para revisión
        return Rating::STATUS_PENDING;
    }
}
