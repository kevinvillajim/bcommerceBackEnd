<?php

namespace App\UseCases\Order;

use App\Domain\Repositories\SellerOrderRepositoryInterface;

class GetSellerOrdersUseCase
{
    private SellerOrderRepositoryInterface $sellerOrderRepository;

    public function __construct(SellerOrderRepositoryInterface $sellerOrderRepository)
    {
        $this->sellerOrderRepository = $sellerOrderRepository;
    }

    /**
     * Obtiene las órdenes de un vendedor con filtros opcionales
     *
     * @param  int  $sellerId  ID del vendedor
     * @param  array  $filters  Filtros de búsqueda
     * @param  int  $limit  Límite de resultados por página
     * @param  int  $offset  Desplazamiento para paginación
     * @return array Array de entidades SellerOrderEntity
     */
    public function execute(int $sellerId, array $filters = [], int $limit = 10, int $offset = 0): array
    {
        // Si hay filtros, usamos getFilteredOrdersForSeller
        if (! empty($filters)) {
            return $this->sellerOrderRepository->getFilteredOrdersForSeller($sellerId, $filters, $limit, $offset);
        }

        // Si no hay filtros, usamos findBySellerId (más sencillo)
        return $this->sellerOrderRepository->findBySellerId($sellerId, $limit, $offset);
    }

    /**
     * Obtiene el número total de órdenes para un vendedor
     *
     * @param  int  $sellerId  ID del vendedor
     * @return int Número total de órdenes
     */
    public function countOrders(int $sellerId): int
    {
        return $this->sellerOrderRepository->countBySellerId($sellerId);
    }

    /**
     * Obtiene estadísticas de órdenes para un vendedor
     *
     * @param  int  $sellerId  ID del vendedor
     * @return array Estadísticas
     */
    public function getStats(int $sellerId): array
    {
        return $this->sellerOrderRepository->getSellerOrderStats($sellerId);
    }
}
