<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\SellerOrderEntity;
use App\Domain\Repositories\SellerOrderRepositoryInterface;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EloquentSellerOrderRepository implements SellerOrderRepositoryInterface
{
    /**
     * ✅ FUNCIÓN HELPER: Asegurar que shipping_data siempre sea un array
     */
    private function ensureShippingDataIsArray($shippingData): ?array
    {
        if ($shippingData === null) {
            return null;
        }
        
        if (is_string($shippingData)) {
            $decoded = json_decode($shippingData, true);
            return is_array($decoded) ? $decoded : null;
        }
        
        return is_array($shippingData) ? $shippingData : null;
    }

    /**
     * ✅ FUNCIÓN HELPER FINAL: Manejar JSON de imágenes correctamente
     */
    private function getProductImageById(int $productId): ?string
    {
        try {
            $product = \App\Models\Product::find($productId);

            if (! $product) {
                Log::info("❌ Producto no encontrado: {$productId}");

                return null;
            }

            // Obtener atributos raw sin accessors
            $attributes = $product->getAttributes();

            // El campo images contiene un JSON string
            $imagesJson = $attributes['images'] ?? null;

            if (empty($imagesJson)) {
                Log::info("❌ No hay imágenes para producto {$productId}");

                return null;
            }

            // Decodificar el JSON
            $imagesArray = json_decode($imagesJson, true);

            if (! is_array($imagesArray) || empty($imagesArray)) {
                Log::info("❌ Error decodificando JSON de imágenes para producto {$productId}");

                return null;
            }

            // Tomar la primera imagen
            $firstImage = $imagesArray[0];

            if (! is_array($firstImage)) {
                Log::info("❌ Primer elemento de images no es array para producto {$productId}");

                return null;
            }

            // Preferir thumbnail, luego original, luego cualquier otro
            $imagePath = $firstImage['thumbnail'] ?? $firstImage['original'] ?? $firstImage['small'] ?? $firstImage['medium'] ?? null;

            if (! $imagePath) {
                Log::info("❌ No se encontró path de imagen válido para producto {$productId}");

                return null;
            }

            // Construir URL completa
            $fullUrl = asset('storage/'.$imagePath);
            Log::info("✅ URL construida para producto {$productId}: {$fullUrl}");

            return $fullUrl;

        } catch (\Exception $e) {
            Log::error("❌ Error getting product image for ID {$productId}: ".$e->getMessage());

            return null;
        }
    }

    public function findById(int $id): ?SellerOrderEntity
    {
        $sellerOrder = SellerOrder::with([
            'items',
            'order' => function ($query) {
                $query->with('user:id,name,email');
            },
        ])->find($id);

        if (! $sellerOrder) {
            return null;
        }

        $items = [];
        foreach ($sellerOrder->items as $item) {
            // ✅ USAR LA FUNCIÓN HELPER PARA OBTENER LA IMAGEN
            $productImage = $this->getProductImageById($item->product_id);

            $items[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'productId' => $item->product_id,
                'product_name' => $item->product_name ?: 'Producto no disponible',
                'product_sku' => $item->product_sku,
                'product_image' => $productImage, // ✅ USAR LA FUNCIÓN
                'quantity' => $item->quantity,
                'price' => $item->price,
                'original_price' => $item->original_price ?? $item->price,
                'subtotal' => $item->subtotal ?? ($item->price * $item->quantity),
                'volume_discount_percentage' => $item->volume_discount_percentage ?? 0,
                'volume_savings' => $item->volume_savings ?? 0,
                'discount_label' => $item->discount_label,
                'total_savings' => $item->total_savings ?? 0,
                'has_volume_discount' => ($item->volume_discount_percentage ?? 0) > 0,
                'seller_discount' => $item->seller_discount ?? 0,
                'final_profit_per_item' => $item->final_profit_per_item ?? 0,
                'seller_id' => $item->seller_id ?? $sellerOrder->seller_id,
            ];
        }

        return SellerOrderEntity::reconstitute(
            $sellerOrder->id,
            $sellerOrder->order_id,
            $sellerOrder->seller_id,
            $sellerOrder->total,
            $sellerOrder->status,
            $this->ensureShippingDataIsArray($sellerOrder->shipping_data),
            $sellerOrder->order_number,
            $sellerOrder->created_at->format('Y-m-d H:i:s'),
            $sellerOrder->updated_at->format('Y-m-d H:i:s'),
            $items,
            null, // originalTotal
            0.0, // volumeDiscountSavings  
            false, // volumeDiscountsApplied
            0.0, // shippingCost
            $sellerOrder->payment_status ?? 'pending',
            $sellerOrder->payment_method
        );
    }

    public function findByOrderId(int $orderId): array
    {
        $sellerOrders = SellerOrder::where('order_id', $orderId)
            ->with([
                'items' => function ($query) {
                    $query->with(['product' => function ($productQuery) {
                        $productQuery->select('id', 'name', 'sku', 'images', 'slug');
                    }]);
                },
            ])
            ->get();

        $result = [];
        foreach ($sellerOrders as $sellerOrder) {
            $items = [];
            foreach ($sellerOrder->items as $item) {
                $items[] = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'productId' => $item->product_id,
                    'product_name' => $item->product_name ?: ($item->product->name ?? 'Producto no disponible'),
                    'product_sku' => $item->product_sku ?: ($item->product->sku ?? null),
                    'product_image' => $item->product_image ?: ($item->product ? $item->product->getMainImageUrl() : null),
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal ?? ($item->price * $item->quantity),
                ];
            }

            $result[] = SellerOrderEntity::reconstitute(
                $sellerOrder->id,
                $sellerOrder->order_id,
                $sellerOrder->seller_id,
                $sellerOrder->total,
                $sellerOrder->status,
                $this->ensureShippingDataIsArray($sellerOrder->shipping_data),
                $sellerOrder->order_number,
                $sellerOrder->created_at->format('Y-m-d H:i:s'),
                $sellerOrder->updated_at->format('Y-m-d H:i:s'),
                $items,
                null, // originalTotal
                0.0, // volumeDiscountSavings  
                false, // volumeDiscountsApplied
                0.0, // shippingCost
                $sellerOrder->payment_status ?? 'pending',
                $sellerOrder->payment_method
            );
        }

        return $result;
    }

    public function findBySellerId(int $sellerId, int $limit = 10, int $offset = 0): array
    {
        $sellerOrders = SellerOrder::where('seller_id', $sellerId)
            ->with([
                'items' => function ($query) {
                    $query->with(['product' => function ($productQuery) {
                        $productQuery->select('id', 'name', 'sku', 'images', 'slug');
                    }]);
                },
                'order',
            ])
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $result = [];
        foreach ($sellerOrders as $sellerOrder) {
            $items = [];
            foreach ($sellerOrder->items as $item) {
                $items[] = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'productId' => $item->product_id,
                    'product_name' => $item->product_name ?: ($item->product->name ?? 'Producto no disponible'),
                    'product_sku' => $item->product_sku ?: ($item->product->sku ?? null),
                    'product_image' => $item->product_image ?: ($item->product ? $item->product->getMainImageUrl() : null),
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal ?? ($item->price * $item->quantity),
                ];
            }

            $result[] = SellerOrderEntity::reconstitute(
                $sellerOrder->id,
                $sellerOrder->order_id,
                $sellerOrder->seller_id,
                $sellerOrder->total,
                $sellerOrder->status,
                $this->ensureShippingDataIsArray($sellerOrder->shipping_data),
                $sellerOrder->order_number,
                $sellerOrder->created_at->format('Y-m-d H:i:s'),
                $sellerOrder->updated_at->format('Y-m-d H:i:s'),
                $items,
                null, // originalTotal
                0.0, // volumeDiscountSavings  
                false, // volumeDiscountsApplied
                0.0, // shippingCost
                $sellerOrder->payment_status ?? 'pending',
                $sellerOrder->payment_method
            );
        }

        return $result;
    }

    public function getFilteredOrdersForSeller(int $sellerId, array $filters, int $limit = 10, int $offset = 0): array
    {
        $query = SellerOrder::where('seller_id', $sellerId)
            ->with([
                'items' => function ($query) {
                    $query->with(['product' => function ($productQuery) {
                        $productQuery->select('id', 'name', 'sku', 'images', 'slug');
                    }]);
                },
                'order',
            ]);

        // Aplicar filtros
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_number', 'like', "%{$search}%");
                    });
            });
        }

        $query->orderBy('created_at', 'desc');

        $sellerOrders = $query->skip($offset)->take($limit)->get();

        $result = [];
        foreach ($sellerOrders as $sellerOrder) {
            $items = [];
            foreach ($sellerOrder->items as $item) {
                $items[] = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'productId' => $item->product_id,
                    'product_name' => $item->product_name ?: ($item->product->name ?? 'Producto no disponible'),
                    'product_sku' => $item->product_sku ?: ($item->product->sku ?? null),
                    'product_image' => $item->product_image ?: ($item->product ? $item->product->getMainImageUrl() : null),
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal ?? ($item->price * $item->quantity),
                ];
            }

            $result[] = SellerOrderEntity::reconstitute(
                $sellerOrder->id,
                $sellerOrder->order_id,
                $sellerOrder->seller_id,
                $sellerOrder->total,
                $sellerOrder->status,
                $this->ensureShippingDataIsArray($sellerOrder->shipping_data),
                $sellerOrder->order_number,
                $sellerOrder->created_at->format('Y-m-d H:i:s'),
                $sellerOrder->updated_at->format('Y-m-d H:i:s'),
                $items,
                null, // originalTotal
                0.0, // volumeDiscountSavings  
                false, // volumeDiscountsApplied
                0.0, // shippingCost
                $sellerOrder->payment_status ?? 'pending',
                $sellerOrder->payment_method
            );
        }

        return $result;
    }

    // ... resto de métodos sin cambios ...
    public function create(SellerOrderEntity $sellerOrderEntity): SellerOrderEntity
    {
        return DB::transaction(function () use ($sellerOrderEntity) {
            // Obtener información de pago de la orden principal
            $mainOrder = \App\Models\Order::find($sellerOrderEntity->getOrderId());
            $paymentStatus = $mainOrder ? $mainOrder->payment_status : 'pending';
            $paymentMethod = $mainOrder ? $mainOrder->payment_method : 'datafast';
            
            $sellerOrder = new SellerOrder;
            $sellerOrder->order_id = $sellerOrderEntity->getOrderId();
            $sellerOrder->seller_id = $sellerOrderEntity->getSellerId();
            $sellerOrder->total = $sellerOrderEntity->getTotal();
            $sellerOrder->status = $sellerOrderEntity->getStatus();
            $sellerOrder->payment_status = $paymentStatus;
            $sellerOrder->payment_method = $paymentMethod;
            $sellerOrder->shipping_data = $sellerOrderEntity->getShippingData();
            $sellerOrder->order_number = $sellerOrderEntity->getOrderNumber();
            $sellerOrder->save();

            $items = $sellerOrderEntity->getItems();
            $savedItems = [];

            foreach ($items as $item) {
                $product = \App\Models\Product::find($item['product_id']);

                $orderItem = new OrderItem;
                $orderItem->order_id = $sellerOrderEntity->getOrderId();
                $orderItem->seller_order_id = $sellerOrder->id;
                $orderItem->product_id = $item['product_id'];
                $orderItem->quantity = $item['quantity'];
                $orderItem->price = $item['price'];
                $orderItem->subtotal = $item['subtotal'] ?? $item['price'] * $item['quantity'];

                $orderItem->product_name = $product ? $product->name : 'Producto no disponible';
                $orderItem->product_sku = $product ? $product->sku : null;
                $orderItem->product_image = $product ? $product->getMainImageUrl() : null;
                $orderItem->seller_id = $product ? $product->seller_id : ($item['seller_id'] ?? null);

                $orderItem->original_price = $item['base_price'] ?? $item['price'];
                $orderItem->volume_discount_percentage = $item['volume_discount_percentage'] ?? 0;
                $orderItem->volume_savings = $item['volume_discount_amount'] ?? 0;
                $orderItem->discount_label = $item['discount_label'] ?? null;

                $orderItem->save();

                $savedItems[] = [
                    'id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'productId' => $orderItem->product_id,
                    'product_name' => $orderItem->product_name,
                    'product_sku' => $orderItem->product_sku,
                    'product_image' => $orderItem->product_image,
                    'quantity' => $orderItem->quantity,
                    'price' => $orderItem->price,
                    'subtotal' => $orderItem->subtotal,
                    'original_price' => $orderItem->original_price,
                    'volume_discount_percentage' => $orderItem->volume_discount_percentage,
                    'volume_savings' => $orderItem->volume_savings,
                    'discount_label' => $orderItem->discount_label,
                    'seller_id' => $orderItem->seller_id,
                ];
            }

            return SellerOrderEntity::reconstitute(
                $sellerOrder->id,
                $sellerOrder->order_id,
                $sellerOrder->seller_id,
                $sellerOrder->total,
                $sellerOrder->status,
                $this->ensureShippingDataIsArray($sellerOrder->shipping_data),
                $sellerOrder->order_number,
                $sellerOrder->created_at->format('Y-m-d H:i:s'),
                $sellerOrder->updated_at->format('Y-m-d H:i:s'),
                $savedItems,
                null, // originalTotal
                0.0, // volumeDiscountSavings  
                false, // volumeDiscountsApplied
                0.0, // shippingCost
                $sellerOrder->payment_status ?? 'pending',
                $sellerOrder->payment_method
            );
        });
    }

    public function save(SellerOrderEntity $sellerOrderEntity): SellerOrderEntity
    {
        return DB::transaction(function () use ($sellerOrderEntity) {
            $sellerOrder = $sellerOrderEntity->getId()
                ? SellerOrder::find($sellerOrderEntity->getId())
                : new SellerOrder;

            if (! $sellerOrder) {
                $sellerOrder = new SellerOrder;
            }

            // Obtener información de pago de la orden principal si es una nueva seller_order
            if (!$sellerOrderEntity->getId()) {
                $mainOrder = \App\Models\Order::find($sellerOrderEntity->getOrderId());
                $paymentStatus = $mainOrder ? $mainOrder->payment_status : 'pending';
                $paymentMethod = $mainOrder ? $mainOrder->payment_method : 'datafast';
            } else {
                // Mantener valores existentes si es una actualización
                $paymentStatus = $sellerOrder->payment_status;
                $paymentMethod = $sellerOrder->payment_method;
            }
            
            $sellerOrder->order_id = $sellerOrderEntity->getOrderId();
            $sellerOrder->seller_id = $sellerOrderEntity->getSellerId();
            $sellerOrder->total = $sellerOrderEntity->getTotal();
            $sellerOrder->status = $sellerOrderEntity->getStatus();
            $sellerOrder->payment_status = $paymentStatus;
            $sellerOrder->payment_method = $paymentMethod;
            $sellerOrder->shipping_data = $sellerOrderEntity->getShippingData();
            $sellerOrder->order_number = $sellerOrderEntity->getOrderNumber();
            $sellerOrder->save();

            if ($sellerOrderEntity->getId() && ! empty($sellerOrderEntity->getItems())) {
                OrderItem::where('seller_order_id', $sellerOrder->id)->delete();
            }

            $items = $sellerOrderEntity->getItems();
            $savedItems = [];

            foreach ($items as $item) {
                $product = \App\Models\Product::find($item['product_id']);

                $orderItem = new OrderItem;
                $orderItem->order_id = $sellerOrderEntity->getOrderId();
                $orderItem->seller_order_id = $sellerOrder->id;
                $orderItem->product_id = $item['product_id'];
                $orderItem->quantity = $item['quantity'];
                $orderItem->price = $item['price'];
                $orderItem->subtotal = $item['subtotal'] ?? $item['price'] * $item['quantity'];

                $orderItem->product_name = $product ? $product->name : 'Producto no disponible';
                $orderItem->product_sku = $product ? $product->sku : null;
                $orderItem->product_image = $product ? $product->getMainImageUrl() : null;
                $orderItem->seller_id = $product ? $product->seller_id : ($item['seller_id'] ?? null);

                $orderItem->original_price = $item['base_price'] ?? $item['price'];
                $orderItem->volume_discount_percentage = $item['volume_discount_percentage'] ?? 0;
                $orderItem->volume_savings = $item['volume_discount_amount'] ?? 0;
                $orderItem->discount_label = $item['discount_label'] ?? null;

                $orderItem->save();

                $savedItems[] = [
                    'id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'productId' => $orderItem->product_id,
                    'product_name' => $orderItem->product_name,
                    'product_sku' => $orderItem->product_sku,
                    'product_image' => $orderItem->product_image,
                    'quantity' => $orderItem->quantity,
                    'price' => $orderItem->price,
                    'subtotal' => $orderItem->subtotal,
                    'original_price' => $orderItem->original_price,
                    'volume_discount_percentage' => $orderItem->volume_discount_percentage,
                    'volume_savings' => $orderItem->volume_savings,
                    'discount_label' => $orderItem->discount_label,
                    'seller_id' => $orderItem->seller_id,
                ];
            }

            $sellerOrder->refresh();

            return SellerOrderEntity::reconstitute(
                $sellerOrder->id,
                $sellerOrder->order_id,
                $sellerOrder->seller_id,
                $sellerOrder->total,
                $sellerOrder->status,
                $this->ensureShippingDataIsArray($sellerOrder->shipping_data),
                $sellerOrder->order_number,
                $sellerOrder->created_at->format('Y-m-d H:i:s'),
                $sellerOrder->updated_at->format('Y-m-d H:i:s'),
                $savedItems,
                null, // originalTotal
                0.0, // volumeDiscountSavings  
                false, // volumeDiscountsApplied
                0.0, // shippingCost
                $sellerOrder->payment_status ?? 'pending',
                $sellerOrder->payment_method
            );
        });
    }

    public function updateStatus(int $id, string $status): bool
    {
        $sellerOrder = SellerOrder::find($id);

        if (! $sellerOrder) {
            return false;
        }

        $sellerOrder->status = $status;

        return $sellerOrder->save();
    }

    public function updateShippingInfo(int $id, array $shippingInfo): bool
    {
        $sellerOrder = SellerOrder::find($id);

        if (! $sellerOrder) {
            return false;
        }

        $existingShippingData = $sellerOrder->shipping_data ?: [];
        $updatedShippingData = array_merge($existingShippingData, $shippingInfo);

        $sellerOrder->shipping_data = $updatedShippingData;

        if (isset($shippingInfo['tracking_number']) && $sellerOrder->status === 'processing') {
            $sellerOrder->status = 'shipped';
        }

        return $sellerOrder->save();
    }

    public function countBySellerId(int $sellerId): int
    {
        return SellerOrder::where('seller_id', $sellerId)->count();
    }

    public function countByStatus(int $sellerId, string $status): int
    {
        return SellerOrder::where('seller_id', $sellerId)
            ->where('status', $status)
            ->count();
    }

    public function getSellerOrderStats(int $sellerId): array
    {
        $totalOrders = $this->countBySellerId($sellerId);
        $pendingOrders = $this->countByStatus($sellerId, 'pending');
        $processingOrders = $this->countByStatus($sellerId, 'processing');
        $shippedOrders = $this->countByStatus($sellerId, 'shipped');
        $deliveredOrders = $this->countByStatus($sellerId, 'delivered');
        $cancelledOrders = $this->countByStatus($sellerId, 'cancelled');

        $totalSales = SellerOrder::where('seller_id', $sellerId)
            ->whereNotIn('status', ['cancelled'])
            ->sum('total');

        return [
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'processingOrders' => $processingOrders,
            'shippedOrders' => $shippedOrders,
            'deliveredOrders' => $deliveredOrders,
            'cancelledOrders' => $cancelledOrders,
            'totalSales' => $totalSales,
        ];
    }
}
