<?php

namespace App\UseCases\Rating;

use App\Domain\Entities\RatingEntity;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\RatingRepositoryInterface;
use App\Domain\Repositories\SellerRepositoryInterface;
use App\Models\Rating;
use App\Services\ConfigurationService;

class RateSellerUseCase
{
    private RatingRepositoryInterface $ratingRepository;

    private SellerRepositoryInterface $sellerRepository;

    private OrderRepositoryInterface $orderRepository;

    private ConfigurationService $configService;

    /**
     * Constructor
     */
    public function __construct(
        RatingRepositoryInterface $ratingRepository,
        SellerRepositoryInterface $sellerRepository,
        OrderRepositoryInterface $orderRepository,
        ConfigurationService $configService
    ) {
        $this->ratingRepository = $ratingRepository;
        $this->sellerRepository = $sellerRepository;
        $this->orderRepository = $orderRepository;
        $this->configService = $configService;
    }

    /**
     * Execute the use case
     */
    public function execute(
        int $userId,
        int $sellerId,
        float $rating,
        ?int $orderId = null,
        ?string $title = null,
        ?string $comment = null,
        ?int $productId = null
    ): RatingEntity {
        // Validación rating value
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('La valoración debe estar entre 1 y 5');
        }

        // Comprobar si el vendedor existe
        $seller = $this->sellerRepository->findById($sellerId);
        if (! $seller) {
            throw new \InvalidArgumentException('Vendedor no encontrado');
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

            // Comprobar si la orden es de este vendedor
            if ($order->getSellerId() !== $sellerId) {
                throw new \InvalidArgumentException('La orden no es de este vendedor');
            }

            // Comprobar si la orden está completada, entregada o enviada
            if ($order->getStatus() !== 'completed' && $order->getStatus() !== 'delivered' && $order->getStatus() !== 'shipped') {
                throw new \InvalidArgumentException('Solo se pueden valorar órdenes completadas, entregadas o enviadas');
            }

            // Comprobar si el usuario ya valoró este vendedor para este producto en esta orden
            if ($this->ratingRepository->hasUserRatedSeller($userId, $sellerId, $orderId, $productId)) {
                throw new \InvalidArgumentException('El usuario ya ha valorado este vendedor para este producto en esta orden');
            }
        } else {
            // Comprobar si el usuario ya valoró a este vendedor para este producto (sin una orden)
            if ($this->ratingRepository->hasUserRatedSeller($userId, $sellerId, null, $productId)) {
                throw new \InvalidArgumentException('El usuario ya ha valorado a este vendedor para este producto');
            }
        }

        // Determinar el estado del rating según la configuración y el valor
        $status = $this->determineRatingStatus($rating);

        // Crear entidad de valoración
        $ratingEntity = new RatingEntity(
            $userId,
            $rating,
            Rating::TYPE_USER_TO_SELLER,
            $sellerId,
            $orderId,
            $productId, // ✅ USAR EL PRODUCT_ID EN LUGAR DE NULL
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
