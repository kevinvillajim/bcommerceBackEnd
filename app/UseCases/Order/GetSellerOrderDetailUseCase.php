<?php

namespace App\UseCases\Order;

use App\Domain\Repositories\OrderRepositoryInterface;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class GetSellerOrderDetailUseCase
{
    private $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * Obtiene detalles de una orden para un vendedor específico
     *
     * @param  int  $orderId  ID de la orden
     * @param  int  $sellerId  ID del vendedor para verificación
     */
    public function execute(int $orderId, int $sellerId): ?array
    {
        // Primero verificar si existe la orden y pertenece al vendedor
        $order = Order::where('id', $orderId)
            ->where('seller_id', $sellerId)
            ->first();

        if (! $order) {
            // Verificar si es una orden multi-vendedor (en SellerOrder)
            if (class_exists('\App\Models\SellerOrder')) {
                $sellerOrder = \App\Models\SellerOrder::where('order_id', $orderId)
                    ->where('seller_id', $sellerId)
                    ->first();

                if (! $sellerOrder) {
                    return null;
                }

                // Obtener la orden principal
                $order = Order::find($sellerOrder->order_id);
                if (! $order) {
                    return null;
                }
            } else {
                return null;
            }
        }

        // Obtener detalles del usuario comprador
        $user = User::find($order->user_id);

        // Preparar la respuesta con datos enriquecidos
        $orderDetails = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'date' => $order->created_at->format('Y-m-d H:i:s'),
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'total' => $order->total,
            'customer' => [
                'id' => $user->id ?? 0,
                'name' => $user->name ?? 'Cliente',
                'email' => $user->email ?? 'sin@email.com',
                'phone' => is_array($order->shipping_data) ? ($order->shipping_data['phone'] ?? null) : null,
            ],
            'shipping_data' => $order->shipping_data,
            'items' => [],
        ];

        // Obtener los ítems de la orden filtrados por vendedor si es una orden multi-vendedor
        $items = [];

        if (isset($sellerOrder)) {
            // Si es una orden multi-vendedor, obtener ítems del SellerOrder
            $items = \App\Models\OrderItem::where('seller_order_id', $sellerOrder->id)->get();
        } else {
            // Si es una orden de un solo vendedor, obtener todos los ítems
            $items = $order->items;
        }

        // Enriquecer cada ítem con datos del producto
        foreach ($items as $item) {
            $product = Product::find($item->product_id);

            $orderDetails['items'][] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $product ? $product->name : 'Producto no disponible',
                'quantity' => $item->quantity,
                'price' => $item->price,
                'subtotal' => $item->subtotal,
                'product_image' => $product ? $product->getMainImageUrl() : null,
                'product_slug' => $product ? $product->slug : null,
            ];
        }

        return $orderDetails;
    }
}
