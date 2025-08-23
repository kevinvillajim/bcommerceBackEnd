<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrderItemBreakdownController extends Controller
{
    /**
     * Obtener el desglose detallado de descuentos para los items de una orden
     */
    public function getOrderItemsBreakdown(int $orderId): JsonResponse
    {
        $userId = Auth::id();
        
        // Verificar que la orden existe y pertenece al usuario
        $order = Order::find($orderId);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada'
            ], 404);
        }
        
        if ($order->user_id != $userId) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado'
            ], 403);
        }
        
        // Obtener todos los items de la orden con el producto completo
        // Incluir también los items de seller_orders si existen
        $orderItems = OrderItem::where('order_id', $orderId)
            ->orWhereHas('sellerOrder', function($query) use ($orderId) {
                $query->where('order_id', $orderId);
            })
            ->with(['product' => function($query) {
                $query->select('id', 'name', 'price', 'discount_percentage', 'images');
            }])
            ->get();
        
        $itemsWithBreakdown = [];
        
        // Obtener datos del pricing_breakdown que ya tiene todos los cálculos correctos
        $pricingBreakdown = [];
        if ($order->pricing_breakdown) {
            $pricingBreakdown = is_string($order->pricing_breakdown) 
                ? json_decode($order->pricing_breakdown, true) 
                : $order->pricing_breakdown;
        }
        
        // Verificar si se usó cupón
        $couponPercentage = 0;
        if (isset($pricingBreakdown['feedback_discount']) && $pricingBreakdown['feedback_discount'] > 0) {
            // Calcular porcentaje del cupón desde los datos reales
            $subtotalBeforeCoupon = $pricingBreakdown['subtotal_with_discounts'] ?? 0;
            if ($subtotalBeforeCoupon > 0) {
                $couponPercentage = round(($pricingBreakdown['feedback_discount'] / $subtotalBeforeCoupon) * 100, 2);
            }
        }
        
        foreach ($orderItems as $item) {
            $product = $item->product;
            
            if (!$product) {
                continue;
            }
            
            // Obtener el precio original REAL del producto desde la BD
            // No usar el precio del item que ya tiene descuentos aplicados
            $originalPrice = floatval($product->price);
            $quantity = $item->quantity;
            
            $itemsWithBreakdown[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $product->name,
                'product_image' => $product->images[0]['thumbnail'] ?? null,
                'quantity' => $quantity,
                'breakdown_steps' => []
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $orderId,
                'items' => $itemsWithBreakdown,
                'has_coupon' => false,
                'coupon_percentage' => 0
            ]
        ]);
    }
}