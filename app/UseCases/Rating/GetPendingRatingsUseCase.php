<?php

namespace App\UseCases\Rating;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Rating;
use App\Models\Shipping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPendingRatingsUseCase
{
    /**
     * Obtener productos y vendedores pendientes de valoración para un usuario
     * VERSIÓN TEMPORAL: Funciona con estructura actual (Order + Shipping)
     *
     * @param  int  $userId  ID del usuario
     * @param  bool  $includeRated  Incluir también los ya valorados
     */
    public function execute(int $userId, bool $includeRated = false): array
    {
        try {
            Log::info('Iniciando GetPendingRatingsUseCase (temporal - estructura actual)', [
                'user_id' => $userId,
                'include_rated' => $includeRated,
            ]);

            $pendingRatings = [
                'products' => [],
                'sellers' => [],
            ];

            // TEMPORAL: Buscar Orders que tengan shipping "delivered"
            $ordersWithDeliveredShipping = Order::with([
                'items.product:id,name,slug,images,seller_id',
                'seller:id,store_name,user_id',
            ])
                ->where('user_id', $userId)
                ->whereHas('items') // Que tenga items
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('shippings')
                        ->whereColumn('shippings.order_id', 'orders.id')
                        ->where('shippings.status', 'delivered');
                })
                ->get();

            Log::info('Órdenes con shipping entregado encontradas (temporal)', [
                'count' => $ordersWithDeliveredShipping->count(),
            ]);

            foreach ($ordersWithDeliveredShipping as $order) {
                $this->processDeliveredOrder($order, $userId, $pendingRatings, $includeRated);
            }

            Log::info('Pending ratings generados (temporal)', [
                'productos_count' => count($pendingRatings['products']),
                'vendedores_count' => count($pendingRatings['sellers']),
            ]);

            return [
                'status' => 'success',
                'data' => $pendingRatings,
            ];
        } catch (\Exception $e) {
            Log::error('Error en GetPendingRatingsUseCase (temporal): '.$e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Error al obtener valoraciones pendientes: '.$e->getMessage(),
                'data' => [
                    'products' => [],
                    'sellers' => [],
                ],
            ];
        }
    }

    /**
     * Procesar una Order con shipping entregado (TEMPORAL)
     */
    private function processDeliveredOrder(
        Order $order,
        int $userId,
        array &$pendingRatings,
        bool $includeRated
    ): void {
        try {
            Log::info('Procesando Order con shipping entregado (temporal)', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'items_count' => $order->items->count(),
            ]);

            // Procesar cada producto individualmente
            foreach ($order->items as $orderItem) {
                $this->processOrderItem($orderItem, $order, $userId, $pendingRatings, $includeRated);
            }

            // Generar pending rating para el vendedor (si tiene seller_id)
            if ($order->seller_id) {
                $this->processSellerRating($order, $userId, $pendingRatings, $includeRated);
            }

        } catch (\Exception $e) {
            Log::error('Error procesando Order (temporal)', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Procesar un OrderItem individual (TEMPORAL)
     */
    private function processOrderItem(
        OrderItem $orderItem,
        Order $order,
        int $userId,
        array &$pendingRatings,
        bool $includeRated
    ): void {
        try {
            $product = $orderItem->product;

            if (! $product) {
                Log::warning('Producto no encontrado para OrderItem (temporal)', [
                    'order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                ]);

                return;
            }

            // Verificar si ya existe rating para este producto específico en esta orden
            $hasRatedProduct = Rating::where('user_id', $userId)
                ->where('order_id', $order->id)
                ->where('product_id', $product->id)
                ->where('type', 'user_to_product')
                ->exists();

            if (! $hasRatedProduct || $includeRated) {
                // Obtener imagen del producto
                $productImage = null;
                if ($product->images && is_array($product->images) && count($product->images) > 0) {
                    $productImage = $product->images[0]['thumbnail'] ?? $product->images[0]['url'] ?? null;
                }

                $pendingRatings['products'][] = [
                    'id' => $product->id,
                    'productId' => $product->id, // Para compatibilidad con frontend
                    'name' => $product->name, // Nombre del producto (no de la orden)
                    'display_name' => $product->name, // Para mayor claridad
                    'image' => $productImage,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_date' => $order->created_at->format('Y-m-d H:i:s'),
                    'seller_id' => $product->seller_id ?? $order->seller_id,
                    'quantity' => $orderItem->quantity,
                    'price' => $orderItem->price,
                    'is_rated' => $hasRatedProduct,
                    'rating_type' => 'product', // Indicar que es un rating de producto
                ];

                Log::info('Producto agregado a pending ratings (temporal)', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'order_id' => $order->id,
                    'has_rated' => $hasRatedProduct,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error procesando OrderItem (temporal)', [
                'order_item_id' => $orderItem->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Procesar rating de vendedor (TEMPORAL)
     */
    private function processSellerRating(
        Order $order,
        int $userId,
        array &$pendingRatings,
        bool $includeRated
    ): void {
        try {
            $seller = $order->seller;

            if (! $seller) {
                Log::warning('Vendedor no encontrado (temporal)', [
                    'seller_id' => $order->seller_id,
                ]);

                return;
            }

            // Obtener el primer producto para asociar al vendedor
            $firstProduct = $order->items->first();
            $productIdForSeller = $firstProduct ? $firstProduct->product_id : null;

            // Verificar si ya existe rating para este vendedor en esta orden
            // Considerando el producto asociado para permitir múltiples ratings por vendedor
            $hasRatedSeller = Rating::where('user_id', $userId)
                ->where('order_id', $order->id)
                ->where('seller_id', $order->seller_id)
                ->where('product_id', $productIdForSeller)
                ->where('type', 'user_to_seller')
                ->exists();

            if (! $hasRatedSeller || $includeRated) {
                $pendingRatings['sellers'][] = [
                    'id' => $seller->id,
                    'seller_id' => $seller->id,
                    'name' => $seller->store_name ?? "Vendedor #{$seller->id}",
                    'display_name' => $seller->store_name ?? "Vendedor #{$seller->id}", // Para mayor claridad
                    'image' => null,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'date' => $order->created_at->format('Y-m-d H:i:s'),
                    'productId' => $productIdForSeller, // Producto asociado
                    'is_rated' => $hasRatedSeller,
                    'rating_type' => 'seller', // Indicar que es un rating de vendedor
                ];

                Log::info('Vendedor agregado a pending ratings (temporal)', [
                    'seller_id' => $seller->id,
                    'store_name' => $seller->store_name,
                    'order_id' => $order->id,
                    'product_id_asociado' => $productIdForSeller,
                    'has_rated' => $hasRatedSeller,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error procesando seller rating (temporal)', [
                'order_id' => $order->id,
                'seller_id' => $order->seller_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
