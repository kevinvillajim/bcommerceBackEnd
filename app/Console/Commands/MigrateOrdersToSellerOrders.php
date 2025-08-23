<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateOrdersToSellerOrders extends Command
{
    /**
     * El nombre y la firma del comando.
     *
     * @var string
     */
    protected $signature = 'orders:migrate-to-seller-orders';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Migra las órdenes existentes a la nueva estructura de SellerOrders';

    /**
     * Ejecutar el comando.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Iniciando migración de órdenes a órdenes de vendedor...');

        // Obtener todas las órdenes existentes
        $orders = Order::with('items')->get();
        $this->info("Se encontraron {$orders->count()} órdenes para migrar.");

        // Contador para seguimiento
        $processed = 0;
        $withMultipleSellers = 0;
        $errors = 0;

        // Procesar cada orden
        foreach ($orders as $order) {
            try {
                DB::beginTransaction();

                // Agrupar items por vendedor (usando seller_id de los productos)
                $itemsBySeller = [];
                $totalsBySeller = [];

                // 1. Organizar items por vendedor
                foreach ($order->items as $item) {
                    $product = Product::find($item->product_id);

                    if (! $product) {
                        $this->warn("   - Producto {$item->product_id} no encontrado para el item {$item->id}, saltando.");

                        continue;
                    }

                    // Determinar seller_id del producto
                    $sellerId = $product->seller_id ?? $product->user_id;

                    if (! $sellerId) {
                        $this->warn("   - No se pudo determinar seller_id para producto {$product->id}, usando user_id ({$product->user_id})");
                        $sellerId = $product->user_id;
                    }

                    // Inicializar arrays para este vendedor
                    if (! isset($itemsBySeller[$sellerId])) {
                        $itemsBySeller[$sellerId] = [];
                        $totalsBySeller[$sellerId] = 0;
                    }

                    // Agregar item a este vendedor
                    $itemsBySeller[$sellerId][] = $item;
                    $totalsBySeller[$sellerId] += $item->subtotal;
                }

                // Detectar si la orden tiene múltiples vendedores
                $hasMultipleSellers = count($itemsBySeller) > 1;
                if ($hasMultipleSellers) {
                    $withMultipleSellers++;
                    $this->info("   * Orden {$order->id} ({$order->order_number}) tiene {$order->items->count()} items de ".count($itemsBySeller).' vendedores diferentes.');
                }

                // 2. Crear SellerOrder para cada vendedor
                foreach ($itemsBySeller as $sellerId => $items) {
                    // Crear SellerOrder
                    $sellerOrder = new SellerOrder;
                    $sellerOrder->order_id = $order->id;
                    $sellerOrder->seller_id = $sellerId;
                    $sellerOrder->total = $totalsBySeller[$sellerId];
                    $sellerOrder->status = $order->status;
                    $sellerOrder->shipping_data = $order->shipping_data;
                    $sellerOrder->order_number = "{$order->order_number}-S{$sellerId}";
                    $sellerOrder->save();

                    // Actualizar referencias en OrderItems
                    foreach ($items as $item) {
                        $item->seller_order_id = $sellerOrder->id;
                        $item->save();
                    }

                    $this->line("   + Creada SellerOrder {$sellerOrder->id} para Seller {$sellerId} con ".count($items)." items y total {$sellerOrder->total}");
                }

                // Limpiar seller_id en la orden principal si tiene múltiples vendedores
                if ($hasMultipleSellers && $order->seller_id) {
                    $order->seller_id = null;
                    $order->save();
                    $this->line("   - Limpiado seller_id en orden {$order->id} por tener múltiples vendedores");
                }

                DB::commit();
                $processed++;

                // Mostrar progreso cada 10 órdenes
                if ($processed % 10 === 0) {
                    $this->info("Procesadas {$processed} órdenes de {$orders->count()}");
                }
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Error al procesar orden {$order->id}: ".$e->getMessage());
                $errors++;
            }
        }

        // Resumen final
        $this->info('Migración completada:');
        $this->info("- Total de órdenes procesadas: {$processed}");
        $this->info("- Órdenes con múltiples vendedores: {$withMultipleSellers}");
        $this->info("- Errores: {$errors}");

        return Command::SUCCESS;
    }
}
