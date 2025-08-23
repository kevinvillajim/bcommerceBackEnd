<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\SellerOrderEntity;

interface SellerOrderRepositoryInterface
{
    /**
     * Encuentra una orden de vendedor por su ID
     */
    public function findById(int $id): ?SellerOrderEntity;

    /**
     * Encuentra órdenes de vendedor por ID de orden principal
     */
    public function findByOrderId(int $orderId): array;

    /**
     * Encuentra órdenes para un vendedor específico
     */
    public function findBySellerId(int $sellerId, int $limit = 10, int $offset = 0): array;

    /**
     * Obtiene órdenes para un vendedor con filtros aplicados
     */
    public function getFilteredOrdersForSeller(int $sellerId, array $filters, int $limit = 10, int $offset = 0): array;

    /**
     * Crea una nueva orden de vendedor
     */
    public function create(SellerOrderEntity $sellerOrderEntity): SellerOrderEntity;

    /**
     * Guarda una orden de vendedor
     */
    public function save(SellerOrderEntity $sellerOrderEntity): SellerOrderEntity;

    /**
     * Actualiza el estado de una orden de vendedor
     */
    public function updateStatus(int $id, string $status): bool;

    /**
     * Actualiza la información de envío
     */
    public function updateShippingInfo(int $id, array $shippingInfo): bool;

    /**
     * Cuenta el número total de órdenes para un vendedor
     */
    public function countBySellerId(int $sellerId): int;

    /**
     * Cuenta el número de órdenes por estado para un vendedor
     */
    public function countByStatus(int $sellerId, string $status): int;

    /**
     * Obtiene estadísticas de órdenes para un vendedor
     */
    public function getSellerOrderStats(int $sellerId): array;
}
