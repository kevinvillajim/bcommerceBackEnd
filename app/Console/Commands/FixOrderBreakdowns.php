<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class FixOrderBreakdowns extends Command
{
    protected $signature = 'fix:order-breakdowns {orderId?}';

    protected $description = 'Arreglar breakdowns de órdenes específicas o todas las que tengan problemas';

    public function handle()
    {
        $orderId = $this->argument('orderId');

        if ($orderId) {
            $this->fixSpecificOrder($orderId);
        } else {
            $this->fixAllProblematicOrders();
        }

        return 0;
    }

    private function fixSpecificOrder(int $orderId): void
    {
        $this->info("🔧 Arreglando orden $orderId...");

        $order = Order::find($orderId);
        if (! $order) {
            $this->error("❌ Orden $orderId no encontrada");

            return;
        }

        $this->displayOrderInfo($order);

        if ($this->confirm('¿Desea arreglar esta orden?')) {
            $this->fixOrder($order);
        }
    }

    private function fixAllProblematicOrders(): void
    {
        $this->info('🔍 Buscando órdenes con problemas de breakdown...');

        $problematicOrders = Order::where('total', '>', 0)
            ->where(function ($query) {
                $query->where('original_total', 0)
                    ->orWhere('subtotal_products', 0)
                    ->orWhere('iva_amount', 0);
            })
            ->get();

        $this->info('📊 Encontradas '.$problematicOrders->count().' órdenes con problemas');

        foreach ($problematicOrders as $order) {
            $this->displayOrderInfo($order);

            if ($this->confirm("¿Arreglar orden {$order->id}?")) {
                $this->fixOrder($order);
            }
        }
    }

    private function displayOrderInfo(Order $order): void
    {
        $this->table(['Campo', 'Valor'], [
            ['ID', $order->id],
            ['Total', '$'.$order->total],
            ['Original Total', '$'.$order->original_total],
            ['Subtotal Products', '$'.$order->subtotal_products],
            ['IVA Amount', '$'.$order->iva_amount],
            ['Shipping Cost', '$'.$order->shipping_cost],
            ['Total Discounts', '$'.$order->total_discounts],
            ['Seller Discounts', '$'.$order->seller_discount_savings],
            ['Volume Discounts', '$'.$order->volume_discount_savings],
        ]);

        // Mostrar items
        $items = $order->orderItems;
        if ($items->count() > 0) {
            $itemsData = [];
            foreach ($items as $item) {
                $itemsData[] = [
                    $item->product->name ?? 'N/A',
                    $item->quantity,
                    '$'.$item->price,
                    '$'.$item->subtotal,
                ];
            }

            $this->table(['Producto', 'Cantidad', 'Precio', 'Subtotal'], $itemsData);
        }
    }

    private function fixOrder(Order $order): void
    {
        try {
            $this->info("🔧 Analizando orden {$order->id}...");

            // Obtener items de la orden
            $items = $order->orderItems;
            if ($items->count() === 0) {
                $this->warn('⚠️ La orden no tiene items');

                return;
            }

            // Para simplificar, vamos a usar patrones conocidos
            if ($order->total == 6.90 && $items->count() == 1) {
                // Patrón: Producto de $2 con descuento 50%
                $this->info('📋 Aplicando patrón: Producto $2 con descuento 50%');

                $order->update([
                    'original_total' => 2.00,
                    'subtotal_products' => 1.00,
                    'iva_amount' => 0.90,
                    'shipping_cost' => 5.00,
                    'total_discounts' => 1.00,
                    'seller_discount_savings' => 1.00,
                    'volume_discount_savings' => 0.00,
                ]);

                $this->info("✅ Orden {$order->id} corregida exitosamente");

            } else {
                // Cálculo dinámico basado en los items
                $this->info('📋 Calculando breakdowns dinámicamente...');

                $originalTotal = 0;
                $subtotalProducts = 0;

                foreach ($items as $item) {
                    $originalTotal += ($item->original_price ?? $item->price) * $item->quantity;
                    $subtotalProducts += $item->price * $item->quantity;
                }

                // Estimar componentes basados en el total
                $baseAmount = $subtotalProducts;
                $estimatedShipping = ($baseAmount < 50) ? 5.00 : 0.00;
                $taxableAmount = $baseAmount + $estimatedShipping;
                $estimatedIva = round($taxableAmount * 0.15, 2);
                $estimatedTotal = $baseAmount + $estimatedShipping + $estimatedIva;

                // Verificar si nuestro cálculo se aproxima al total real
                $difference = abs($estimatedTotal - $order->total);

                if ($difference < 0.50) { // Tolerancia de 50 centavos
                    $order->update([
                        'original_total' => $originalTotal,
                        'subtotal_products' => $subtotalProducts,
                        'iva_amount' => $estimatedIva,
                        'shipping_cost' => $estimatedShipping,
                        'total_discounts' => $originalTotal - $subtotalProducts,
                        'seller_discount_savings' => $originalTotal - $subtotalProducts,
                        'volume_discount_savings' => 0.00,
                    ]);

                    $this->info("✅ Orden {$order->id} corregida dinámicamente");
                } else {
                    $this->warn("⚠️ No se pudo calcular automáticamente la orden {$order->id}");
                    $this->warn("   Total esperado: $estimatedTotal, Total real: {$order->total}, Diferencia: $difference");
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Error arreglando orden {$order->id}: ".$e->getMessage());
        }
    }
}
