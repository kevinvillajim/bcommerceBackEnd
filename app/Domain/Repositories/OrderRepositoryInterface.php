<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\OrderEntity;

interface OrderRepositoryInterface
{
    /**
     * Find an order by ID
     */
    public function findById(int|string $id): ?OrderEntity;

    /**
     * Create a new order
     */
    public function create(OrderEntity $orderEntity): OrderEntity;

    /**
     * Create order from webhook data
     */
    public function createFromWebhook(array $orderData): OrderEntity;

    /**
     * Get orders for a user
     */
    public function getOrdersForUser(int $userId, int $limit = 10, int $offset = 0): array;

    /**
     * Get orders for a seller
     */
    public function getOrdersForSeller(int $sellerId, int $limit = 10, int $offset = 0): array;

    /**
     * Get filtered orders for a seller
     *
     * @param  array  $filters  Array of filters (status, payment_status, date_from, date_to, search)
     */
    public function getFilteredOrdersForSeller(int $sellerId, array $filters, int $limit = 10, int $offset = 0): array;

    /**
     * Check if an order contains a specific product
     */
    public function orderContainsProduct(int $orderId, int $productId): bool;

    /**
     * Save an order
     */
    public function save(OrderEntity $order): OrderEntity;

    /**
     * Update payment information
     */
    public function updatePaymentInfo(int $orderId, array $paymentInfo): bool;

    /**
     * Find orders by user ID
     */
    public function findOrdersByUserId(int $userId): array;

    /**
     * Get order details including related information
     */
    public function getOrderDetails(int $orderId): array;

    /**
     * Get order statistics for a seller
     *
     * @return array Statistics including counts by status and total sales
     */
    public function getSellerOrderStats(int $sellerId): array;

    /**
     * Count orders by status for a seller
     */
    public function countOrdersByStatus(int $sellerId, string $status): int;

    /**
     * Get total sales amount for a seller
     *
     * @param  string  $dateFrom  Optional start date in Y-m-d format
     * @param  string  $dateTo  Optional end date in Y-m-d format
     */
    public function getTotalSalesForSeller(int $sellerId, ?string $dateFrom = null, ?string $dateTo = null): float;

    /**
     * Get recent orders for a seller
     */
    public function getRecentOrdersForSeller(int $sellerId, int $limit = 5): array;

    /**
     * Get popular products from seller's orders
     */
    public function getPopularProductsForSeller(int $sellerId, int $limit = 5): array;

    /**
     * Search orders for a seller by order number, customer name, or email
     */
    public function searchSellerOrders(int $sellerId, string $query, int $limit = 10, int $offset = 0): array;

    /**
     * Count total orders for a seller
     */
    public function countTotalOrdersForSeller(int $sellerId): int;

    /**
     * Get sales data by period (day, week, month) for seller
     *
     * @param  string  $period  One of 'day', 'week', 'month', 'year'
     * @param  int  $limit  The number of periods to return
     */
    public function getSellerSalesByPeriod(int $sellerId, string $period = 'day', int $limit = 30): array;

    /**
     * Get order count by status for a specific seller
     *
     * @return array Associative array with status as key and count as value
     */
    public function getOrderCountByStatus(int $sellerId): array;

    /**
     * Get customer list for a seller with their order counts and total spent
     */
    public function getSellerCustomers(int $sellerId, int $limit = 10, int $offset = 0): array;

    /**
     * Get orders with specific product
     */
    public function getOrdersWithProduct(int $sellerId, int $productId, int $limit = 10, int $offset = 0): array;

    /**
     * Cancel an order
     */
    public function cancelOrder(int $orderId, string $reason = ''): bool;

    /**
     * Get orders awaiting shipment (processing status)
     */
    public function getOrdersAwaitingShipment(int $sellerId, int $limit = 10, int $offset = 0): array;

    /**
     * Update order shipping information
     */
    public function updateShippingInfo(int $orderId, array $shippingInfo): bool;

    /**
     * Update order status without affecting order items
     */
    public function updateStatus(int $orderId, string $status): bool;

    /**
     * Get average order value for a seller
     *
     * @param  string  $dateFrom  Optional start date in Y-m-d format
     * @param  string  $dateTo  Optional end date in Y-m-d format
     */
    public function getAverageOrderValue(int $sellerId, ?string $dateFrom = null, ?string $dateTo = null): float;

    /**
     * Find completed or delivered orders for a user
     */
    public function findCompletedOrdersByUserId(int $userId): array;

    /**
     * Get orders with specific product for a user
     */
    public function getOrdersWithProductForUser(int $userId, int $productId): array;

    /**
     * Count total orders for a user
     */
    public function countTotalOrdersForUser(int $userId): int;

    /**
     * Get total amount spent by a user
     */
    public function getTotalSpentForUser(int $userId): float;
}
